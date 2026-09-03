<?php

declare(strict_types=1);

namespace App\Http\Concerns;

use App\Domain\Attendance\Data\WorkShift;
use App\Domain\Leave\Models\LateArrivalRequest;

/**
 * Tra những ngày đã được duyệt cho đi muộn.
 *
 * Ghép ở tầng Http vì đây là chỗ duy nhất biết cả hai miền: Attendance đo giờ,
 * Leave giữ đơn, và hai miền nghiệp vụ **không được gọi nhau** (README, "Quy
 * tắc phụ thuộc"). Cùng khuôn với `ResolvesApprovedLeave`, và tách khỏi nó vì
 * hai thứ trả lời hai câu khác nhau: trait kia hỏi "hôm nay có nghỉ không",
 * trait này hỏi "hôm nay được phép đến muộn tới mấy giờ".
 *
 * Một truy vấn cho cả tháng của cả phòng. Không gom thì mỗi ô trên lưới phải
 * tự hỏi "ngày này có đơn nào không" — bảng ba mươi người là chín trăm câu SQL.
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
        if ($userIds === []) {
            return [];
        }

        $khoa = [];

        $ds = LateArrivalRequest::query()
            ->whereIn('user_id', $userIds)
            ->approvedBetween($tuNgay, $denNgay)
            ->get(['user_id', 'date', 'expected_arrival']);

        foreach ($ds as $don) {
            $khoa[$don->user_id.':'.$don->date] = $don->expected_arrival;
        }

        return $khoa;
    }

    /**
     * Ngày này có được đơn đã duyệt bao không.
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
}
