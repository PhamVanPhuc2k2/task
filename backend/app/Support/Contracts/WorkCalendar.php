<?php

declare(strict_types=1);

namespace App\Support\Contracts;

use App\Support\Enums\DayKind;

/**
 * Lịch làm việc của công ty, hỏi từ ngoài miền Attendance.
 *
 * ## Vì sao là một giao diện ở tầng Support
 *
 * Hai câu hỏi dưới đây chỉ trả lời được bằng lịch tuần và bảng ngày lễ — cả hai
 * thuộc miền Attendance. Nhưng miền Leave cần chúng để tính quỹ phép, và miền
 * Payroll sẽ cần để tính hệ số làm thêm giờ. Luật tầng cấm các miền gọi nhau.
 *
 * Nên câu hỏi khai ở tầng dưới cùng, nơi mọi miền đều với tới được; câu trả lời
 * do Attendance cài đặt, và `AppServiceProvider` ghép hai đầu.
 *
 * ## Vì sao `countBetween` trả về số thực
 *
 * Công ty làm sáng thứ bảy. Nghỉ một ngày thứ bảy là tiêu **nửa** ngày công,
 * không phải một ngày — tính đủ một ngày là ăn gian của người lao động, tính 0
 * là ăn gian của công ty.
 *
 * Mọi giá trị đều là bội của 0,5 nên biểu diễn được chính xác bằng số thực nhị
 * phân. Đây là lý do đếm ngày công dùng `float` được, còn tiền thì không.
 */
interface WorkCalendar
{
    /**
     * Số ngày công trong khoảng `[$tuNgay, $denNgay]`, tính cả hai đầu.
     *
     * Ngày làm cả ngày tính 1, ngày nửa buổi tính 0,5. Ngày nghỉ hằng tuần và
     * ngày lễ tính 0 — nghỉ phép trùng ngày lễ thì không tiêu ngày phép nào,
     * đó là điểm chính của phép đếm này.
     */
    public function countBetween(string $tuNgay, string $denNgay): float;

    /**
     * Ngày này thuộc loại nào: ngày làm, ngày nghỉ tuần, hay ngày lễ.
     *
     * Dùng để chọn hệ số tiền lương làm thêm giờ (Điều 98 Bộ luật Lao động
     * 2019). Ngày lễ đếm theo **ngày thực nghỉ** chứ không theo ngày lễ danh
     * nghĩa: lễ trùng ngày nghỉ hằng tuần thì nghỉ bù sang ngày kế tiếp (Điều
     * 112), và ngày nghỉ bù mới là ngày người ta thật sự không đi làm.
     */
    public function kindOf(string $ngay): DayKind;
}
