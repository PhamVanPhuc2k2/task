<?php

declare(strict_types=1);

namespace App\Http\Concerns;

use App\Domain\Attendance\Data\DailyAttendance;
use App\Domain\Leave\Models\LeaveRequest;
use Carbon\CarbonImmutable;

/**
 * Tra những ngày đã được duyệt nghỉ.
 *
 * Tách khỏi `ReconcilesWithDailyReports` chứ không nhét chung: trait kia ghép
 * báo cáo, trait này ghép đơn nghỉ. Gộp lại thì tên của nó nói sai một nửa việc
 * nó làm, và chỗ dùng phải đọc mã mới biết mình đang lấy về cái gì.
 *
 * Ghép ở tầng Http vì đây là chỗ duy nhất biết cả hai miền: Attendance đo giờ,
 * Leave giữ đơn nghỉ, và hai miền nghiệp vụ **không được gọi nhau** (README,
 * "Quy tắc phụ thuộc").
 */
trait ResolvesApprovedLeave
{
    /**
     * Tập khoá `userId:ngày` của mọi ngày nghỉ **đã duyệt** trong khoảng.
     *
     * Một truy vấn cho cả tháng của cả phòng, rồi bung khoảng ngày ra trong
     * PHP. Không bung thì mỗi ô trên lưới phải tự hỏi "ngày này có nằm trong
     * đơn nào không" — bảng ba mươi người là chín trăm câu SQL.
     *
     * Bung trong PHP an toàn vì đơn nghỉ bị chặn tối đa
     * `leave.max_days_per_request` ngày; không có mốc đó thì một đơn gõ nhầm
     * năm sẽ sinh ra ba trăm sáu mươi sáu khoá cho một người.
     *
     * @param  list<int>  $userIds
     * @return array<string, true>
     */
    protected function approvedLeaveDays(array $userIds, string $tuNgay, string $denNgay): array
    {
        if ($userIds === []) {
            return [];
        }

        $khoa = [];

        $dsDon = LeaveRequest::query()
            ->whereIn('user_id', $userIds)
            ->approvedBetween($tuNgay, $denNgay)
            ->get(['user_id', 'start_date', 'end_date']);

        foreach ($dsDon as $don) {
            // Cắt về đúng khoảng đang xem: đơn nghỉ vắt qua hai tháng thì chỉ
            // lấy phần rơi vào tháng này, tránh sinh khoá cho ngày không hiển
            // thị.
            $ngay = CarbonImmutable::parse(max($don->start_date, $tuNgay));
            $cuoi = CarbonImmutable::parse(min($don->end_date, $denNgay));

            while ($ngay->lessThanOrEqualTo($cuoi)) {
                $khoa[$don->user_id.':'.$ngay->toDateString()] = true;
                $ngay = $ngay->addDay();
            }
        }

        return $khoa;
    }

    /**
     * @param  array<string, true>  $ngayNghi  Kết quả của approvedLeaveDays().
     */
    protected function isOnApprovedLeave(array $ngayNghi, int $userId, string $ngay): bool
    {
        return isset($ngayNghi[$userId.':'.$ngay]);
    }

    /**
     * Những ngày nghỉ đã duyệt mà **không có phiên làm việc nào**.
     *
     * Đây là trường hợp THƯỜNG GẶP, không phải ngoại lệ: người nghỉ phép thì
     * đúng là không đụng vào hệ thống, nên không có phiên nào cả. Mà ô trên
     * lưới lại được dựng từ bảng `work_sessions`.
     *
     * Không có hàm này thì ngày nghỉ đã duyệt vẫn để lại một ô trống y hệt
     * ngày vắng mặt không lý do — và cả tính năng mất sạch ý nghĩa: quản lý
     * vẫn phải bấm tay để dọn, đúng thứ nó sinh ra để bỏ.
     *
     * Cùng khuôn với `reportOnlyDays`: trả về `DailyAttendance` 0 phút để đi
     * qua đúng một hàm dựng ô với mọi ngày khác.
     *
     * @param  array<string, true>  $ngayNghi
     * @param  array<string, mixed>  $daCoO  Khoá là ngày đã có ô.
     * @return list<DailyAttendance>
     */
    protected function leaveOnlyDays(array $ngayNghi, int $userId, array $daCoO): array
    {
        $them = [];
        $tienTo = $userId.':';

        foreach (array_keys($ngayNghi) as $khoa) {
            if (! str_starts_with($khoa, $tienTo)) {
                continue;
            }

            $ngay = substr($khoa, strlen($tienTo));

            if (isset($daCoO[$ngay])) {
                continue;
            }

            $them[] = new DailyAttendance(
                userId: $userId,
                workDate: $ngay,
                measuredMinutes: 0,
                sessionCount: 0,
                firstSeenAt: null,
                lastSeenAt: null,
            );
        }

        return $them;
    }
}
