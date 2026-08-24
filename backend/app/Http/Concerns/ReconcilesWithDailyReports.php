<?php

declare(strict_types=1);

namespace App\Http\Concerns;

use App\Domain\Attendance\Data\DailyAttendance;
use App\Domain\Report\Models\DailyReport;
use App\Support\Enums\ReportMatch;

/**
 * Ghép giờ công với báo cáo ngày.
 *
 * Việc ghép nằm ở tầng Http vì đây là **chỗ duy nhất biết cả hai miền**: miền
 * Attendance và miền Report không được gọi nhau (README, "Quy tắc phụ thuộc").
 * Cùng khuôn với `PresentsDailyReports` — trait đó ghép tên task vào báo cáo.
 *
 * Một truy vấn cho cả tháng của cả phòng, không phải một truy vấn mỗi ô: bảng
 * tháng ba mươi người là chín trăm ô, và N+1 ở đây là chín trăm câu SQL.
 */
trait ReconcilesWithDailyReports
{
    /**
     * Tập khoá `userId:workDate` của những ngày **đã nộp** báo cáo.
     *
     * Bản nháp không tính: viết dở rồi bỏ đó thì quản lý không đọc được gì, và
     * coi đó là "đã báo cáo" sẽ giấu mất đúng ngày cần nhìn.
     *
     * @param  list<int>  $userIds
     * @return array<string, true>
     */
    protected function submittedReportKeys(array $userIds, string $tuNgay, string $denNgay): array
    {
        if ($userIds === []) {
            return [];
        }

        /** @var array<string, true> $khoa */
        $khoa = DailyReport::query()
            ->whereIn('user_id', $userIds)
            ->whereBetween('report_date', [$tuNgay, $denNgay])
            ->submitted()
            ->get(['user_id', 'report_date'])
            ->mapWithKeys(
                fn (DailyReport $r): array => [$r->user_id.':'.$r->report_date => true],
            )
            ->all();

        return $khoa;
    }

    /**
     * @param  array<string, true>  $daBaoCao  Kết quả của submittedReportKeys().
     */
    protected function hasSubmittedReport(array $daBaoCao, int $userId, string $workDate): bool
    {
        return isset($daBaoCao[$userId.':'.$workDate]);
    }

    /**
     * Những ngày **có báo cáo nhưng không có phiên làm việc nào**.
     *
     * Cần hàm này vì ô ngày được dựng từ bảng `work_sessions`: ngày không có
     * phiên thì không có ô, và người họp cả ngày rồi vẫn ngồi viết báo cáo sẽ
     * biến mất khỏi bảng công. Đó đúng là tình huống mà cột đối chiếu sinh ra
     * để hiện — bỏ sót nó thì trạng thái `ReportOnly` gần như không bao giờ
     * xuất hiện, và người quản lý nhìn ô trắng rồi tưởng hôm đó không ai làm gì.
     *
     * Trả về `DailyAttendance` với 0 phút thay vì một dạng ô riêng: cùng đi qua
     * đúng một hàm dựng ô với mọi ngày khác, nên không có nhánh nào lệch.
     *
     * @param  array<string, true>  $daBaoCao
     * @param  array<string, mixed>  $daCoO  Khoá là ngày đã có ô.
     * @return list<DailyAttendance>
     */
    protected function reportOnlyDays(array $daBaoCao, int $userId, array $daCoO): array
    {
        $them = [];
        $tienTo = $userId.':';

        foreach (array_keys($daBaoCao) as $khoa) {
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

    /**
     * @param  array<string, true>  $daBaoCao
     * @param  array<string, string>  $ngayLe  Khoá là ngày, giá trị là tên ngày lễ.
     * @param  array<string, true>  $ngayNghi  Kết quả của approvedLeaveDays().
     */
    protected function reconcile(
        int $userId,
        string $workDate,
        int $minutes,
        array $daBaoCao,
        array $ngayLe,
        array $ngayNghi = [],
    ): ReportMatch {
        return ReportMatch::for(
            minutes: $minutes,
            hasReport: $this->hasSubmittedReport($daBaoCao, $userId, $workDate),
            minWorkedMinutes: config()->integer('attendance.min_worked_minutes'),
            isHoliday: isset($ngayLe[$workDate]),
            onLeave: isset($ngayNghi[$userId.':'.$workDate]),
        );
    }
}
