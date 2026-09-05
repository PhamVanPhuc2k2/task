<?php

declare(strict_types=1);

namespace App\Support\Enums;

/**
 * Một ngày trên lịch công ty thuộc loại nào.
 *
 * Ở tầng Support vì hai miền cùng cần: Attendance biết câu trả lời (nó giữ lịch
 * tuần và bảng ngày lễ), còn Leave và Payroll cần dùng — mà các miền không được
 * gọi nhau. Xem `App\Support\Contracts\WorkCalendar`.
 *
 * ## Ngày nửa buổi vẫn là NGÀY LÀM VIỆC
 *
 * Sáng thứ bảy có ca, nên thứ bảy là ngày làm việc bình thường. Hệ quả với tiền
 * lương làm thêm giờ: làm thêm chiều thứ bảy hưởng hệ số **ngày thường**, không
 * phải hệ số ngày nghỉ hằng tuần — ngày nghỉ hằng tuần của công ty là chủ nhật.
 *
 * Điều 111 Bộ luật Lao động 2019 chỉ đòi mỗi tuần nghỉ ít nhất một ngày, nên
 * cách xếp này hợp lệ. Nhưng nó là một quyết định về tiền, nên phải nói ra chứ
 * không để người sau tự suy.
 */
enum DayKind: string
{
    /** Ngày có ca làm — cả ngày hoặc nửa buổi. */
    case Working = 'working';

    /** Ngày nghỉ hằng tuần. */
    case WeeklyRest = 'weekly_rest';

    /**
     * Ngày nghỉ lễ, tết.
     *
     * Thắng cả hai loại trên: lễ trùng ngày nghỉ hằng tuần thì nghỉ bù sang
     * ngày kế tiếp (Điều 112), và ngày nghỉ bù mới là ngày mang tính chất lễ.
     */
    case Holiday = 'holiday';

    public function label(): string
    {
        return match ($this) {
            self::Working => 'Ngày làm việc',
            self::WeeklyRest => 'Ngày nghỉ hằng tuần',
            self::Holiday => 'Ngày nghỉ lễ',
        };
    }
}
