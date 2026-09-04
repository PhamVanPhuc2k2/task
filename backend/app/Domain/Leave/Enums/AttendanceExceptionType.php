<?php

declare(strict_types=1);

namespace App\Domain\Leave\Enums;

/**
 * Loại đơn xin ngoại lệ chấm công: đi muộn hay về sớm.
 *
 * ## Vì sao một cột `type` chứ không phải hai bảng
 *
 * Hai loại đi qua **đúng một vòng đời** — chờ → duyệt / từ chối / rút — do cùng
 * một người duyệt, với cùng một quyền `leave.approve`, cùng một bộ thông báo.
 * Tách thành hai bảng là nhân đôi controller, action, notification, policy và
 * giao diện cho một luồng giống nhau tới chín phần mười.
 *
 * Cột này cũng chừa sẵn chỗ cho **muộn buổi chiều** ở đợt sau: thêm một case,
 * không thêm bảng.
 *
 * ## Tên bảng đang nói dối, và đó là món nợ có tên
 *
 * Bảng vẫn tên `late_arrival_requests` nhưng từ nay chứa cả đơn về sớm. Đổi tên
 * bảng trên hệ thống đang chạy phải tách **hai lần deploy** theo quy ước dự án
 * — `deploy.sh` chạy migration TRƯỚC khi đổi container, nên đổi tên ngay bây
 * giờ thì image cũ đang chạy sẽ truy vấn một bảng không còn tồn tại.
 *
 * Trả nợ khi làm "muộn buổi chiều": lúc đó đổi tên một lần cho cả ba loại.
 */
enum AttendanceExceptionType: string
{
    /** Đến sau giờ vào ca buổi sáng. */
    case Late = 'late';

    /** Rời trước giờ tan ca. */
    case Early = 'early';

    public function label(): string
    {
        return match ($this) {
            self::Late => 'Đi muộn',
            self::Early => 'Về sớm',
        };
    }

    /**
     * Tên cột giữ mốc giờ người dùng xin.
     *
     * Hai loại dùng hai cột khác nhau vì chúng là hai thứ khác nhau — "tôi sẽ
     * tới lúc 9h30" và "tôi sẽ về lúc 16h" không phải cùng một dữ liệu. Nhét
     * chung một cột thì tên cột nói dối một nửa số dòng, và mọi chỗ đọc nó phải
     * kiểm `type` trước mới hiểu con số nghĩa là gì.
     */
    public function timeColumn(): string
    {
        return match ($this) {
            self::Late => 'expected_arrival',
            self::Early => 'expected_departure',
        };
    }
}
