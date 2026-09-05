<?php

declare(strict_types=1);

namespace App\Support\Time;

/**
 * Định dạng ngày và khoảng thời gian theo cách người Việt đọc.
 *
 * Sáu lớp thông báo từng có bản sao riêng của `ngayViet()` — sáu chỗ phải sửa
 * nếu công ty đổi cách viết ngày, và không có gì bắt được nếu chỉ sửa năm chỗ.
 * Người nhận thông báo sẽ thấy hai định dạng ngày khác nhau trong cùng một hộp
 * thư, im lặng.
 *
 * Cố ý ở tầng Support: cả Attendance, Leave lẫn Report đều cần, và tầng này
 * không phụ thuộc gì nên miền nào cũng gọi được.
 *
 * Không dùng Carbon: hai hàm này nhận chuỗi và số, không nhận mốc thời gian.
 * Dựng một đối tượng thời gian chỉ để đảo ba mảnh chuỗi là mở đường cho lệch
 * múi giờ ở đúng chỗ không cần tới nó — cùng lý do đã ghi ở `WorkDate`.
 */
final class HumanTime
{
    /** `2026-08-20` thành `20/08/2026`. */
    public static function ngay(string $ymd): string
    {
        return implode('/', array_reverse(explode('-', $ymd)));
    }

    /** `2026-09` thành `tháng 09/2026` — cách người ta đọc một kỳ công. */
    public static function ky(string $ky): string
    {
        [$nam, $thang] = array_pad(explode('-', $ky), 2, '');

        return $thang === '' ? $ky : sprintf('tháng %s/%s', $thang, $nam);
    }

    /** `480` thành `8 giờ`, `450` thành `7 giờ 30 phút`, `45` thành `45 phút`. */
    public static function gioPhut(int $phut): string
    {
        $gio = intdiv($phut, 60);
        $du = $phut % 60;

        if ($gio === 0) {
            return sprintf('%d phút', $du);
        }

        return $du === 0
            ? sprintf('%d giờ', $gio)
            : sprintf('%d giờ %d phút', $gio, $du);
    }
}
