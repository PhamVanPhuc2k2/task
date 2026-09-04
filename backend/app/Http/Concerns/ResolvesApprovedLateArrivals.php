<?php

declare(strict_types=1);

namespace App\Http\Concerns;

use App\Domain\Attendance\Data\WorkShift;
use App\Domain\Leave\Enums\AttendanceExceptionType;
use App\Domain\Leave\Models\LateArrivalRequest;

/**
 * Tra những ngày đã được duyệt cho đi muộn hoặc về sớm.
 *
 * Ghép ở tầng Http vì đây là chỗ duy nhất biết cả hai miền: Attendance đo giờ,
 * Leave giữ đơn, và hai miền nghiệp vụ **không được gọi nhau** (README, "Quy
 * tắc phụ thuộc"). Cùng khuôn với `ResolvesApprovedLeave`, và tách khỏi nó vì
 * hai thứ trả lời hai câu khác nhau: trait kia hỏi "hôm nay có nghỉ không",
 * trait này hỏi "hôm nay được phép lệch giờ tới đâu".
 *
 * Một truy vấn cho cả tháng của cả phòng. Không gom thì mỗi ô trên lưới phải
 * tự hỏi "ngày này có đơn nào không" — bảng ba mươi người là chín trăm câu SQL.
 *
 * ## Hai chiều đối xứng nhưng KHÔNG giống hệt
 *
 * Đi muộn so `first_seen_at` với giờ vào ca; về sớm so `last_seen_at` với giờ
 * tan ca. Điểm khác quan trọng: `first_seen_at` đứng yên ngay từ nhịp tim đầu,
 * còn `last_seen_at` vẫn lớn dần chừng nào người ta còn mở máy — nên con số về
 * sớm chỉ có nghĩa khi nhìn lại một ngày đã qua.
 */
trait ResolvesApprovedLateArrivals
{
    /**
     * Khoá `userId:ngày` → giờ đã duyệt cho đến muộn.
     *
     * @param  list<int>  $userIds
     * @return array<string, string>
     */
    protected function approvedLateArrivals(array $userIds, string $tuNgay, string $denNgay): array
    {
        return $this->mocDaDuyet($userIds, $tuNgay, $denNgay, AttendanceExceptionType::Late);
    }

    /**
     * Khoá `userId:ngày` → giờ đã duyệt cho về sớm.
     *
     * @param  list<int>  $userIds
     * @return array<string, string>
     */
    protected function approvedEarlyLeaves(array $userIds, string $tuNgay, string $denNgay): array
    {
        return $this->mocDaDuyet($userIds, $tuNgay, $denNgay, AttendanceExceptionType::Early);
    }

    /**
     * Ngày này có được đơn ĐI MUỘN đã duyệt bao không.
     *
     * Hai điều kiện, và **cả hai đều cần**: có đơn đã duyệt cho đúng ngày đó,
     * VÀ người ta đến kịp giờ đã hẹn. Thiếu điều kiện thứ hai thì đơn thành
     * giấy thông hành cho cả ngày.
     *
     * @param  array<string, string>  $daDuyet  Kết quả của approvedLateArrivals().
     */
    protected function isLateExcused(
        array $daDuyet,
        ?WorkShift $ca,
        int $userId,
        string $ngay,
        ?string $firstSeenAtUtc,
    ): bool {
        // Ngày nghỉ không có ca, nên không có cái gì để miễn. Trả false chứ
        // không true: "được miễn đi muộn" trên một ngày không tính đi muộn là
        // một nhãn vô nghĩa hiện lên bảng công.
        if ($ca === null) {
            return false;
        }

        $gioHen = $daDuyet[$userId.':'.$ngay] ?? null;

        if ($gioHen === null) {
            return false;
        }

        return $ca->arrivedBy($firstSeenAtUtc, $gioHen);
    }

    /**
     * Ngày này có được đơn VỀ SỚM đã duyệt bao không.
     *
     * Đối xứng với `isLateExcused()`: đơn chỉ bao **từ đúng giờ đã xin** trở đi.
     * Xin về lúc 16h mà 14h đã tắt máy thì phần sớm hơn vẫn là về sớm — bỏ luật
     * này thì một đơn duy nhất biến thành giấy thông hành cho cả buổi chiều.
     *
     * @param  array<string, string>  $daDuyet  Kết quả của approvedEarlyLeaves().
     */
    protected function isEarlyLeaveExcused(
        array $daDuyet,
        ?WorkShift $ca,
        int $userId,
        string $ngay,
        ?string $lastSeenAtUtc,
    ): bool {
        if ($ca === null) {
            return false;
        }

        $gioHen = $daDuyet[$userId.':'.$ngay] ?? null;

        if ($gioHen === null) {
            return false;
        }

        return $ca->stayedUntil($lastSeenAtUtc, $gioHen);
    }

    /**
     * Bản đồ mốc giờ đã duyệt của một loại đơn.
     *
     * Bỏ qua dòng có mốc giờ rỗng thay vì để `null` lọt vào bản đồ. Ràng buộc
     * CHECK ở database đã bảo đảm đúng một cột được điền theo `type`, nên
     * trường hợp này không xảy ra — nhưng một `null` lọt vào đây sẽ đi thẳng
     * tới `arrivedBy()` và biến thành so sánh với chuỗi rỗng, im lặng.
     *
     * @param  list<int>  $userIds
     * @return array<string, string>
     */
    private function mocDaDuyet(
        array $userIds,
        string $tuNgay,
        string $denNgay,
        AttendanceExceptionType $loai,
    ): array {
        if ($userIds === []) {
            return [];
        }

        $khoa = [];

        $ds = LateArrivalRequest::query()
            ->whereIn('user_id', $userIds)
            ->where('type', $loai->value)
            ->approvedBetween($tuNgay, $denNgay)
            ->get(['user_id', 'date', 'type', 'expected_arrival', 'expected_departure']);

        foreach ($ds as $don) {
            $moc = $don->timeLabel();

            if ($moc === '') {
                continue;
            }

            $khoa[$don->user_id.':'.$don->date] = $moc;
        }

        return $khoa;
    }
}
