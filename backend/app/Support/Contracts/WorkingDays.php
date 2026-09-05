<?php

declare(strict_types=1);

namespace App\Support\Contracts;

/**
 * Đếm ngày công trong một khoảng ngày.
 *
 * ## Vì sao là một giao diện ở tầng Support
 *
 * Quỹ phép năm nằm ở miền Leave, nhưng câu hỏi *"khoảng này có bao nhiêu ngày
 * công"* chỉ trả lời được bằng lịch tuần và bảng ngày lễ — cả hai đều thuộc
 * miền Attendance. Luật tầng cấm Leave gọi thẳng Attendance.
 *
 * Nên câu hỏi được khai ở tầng dưới cùng, nơi cả hai miền đều với tới được, còn
 * câu trả lời do Attendance cài đặt và `AppServiceProvider` ghép vào. Leave chỉ
 * biết tới giao diện này.
 *
 * ## Vì sao trả về số thực
 *
 * Công ty làm sáng thứ bảy. Nghỉ một ngày thứ bảy là tiêu **nửa** ngày công,
 * không phải một ngày — tính đủ một ngày là ăn gian của người lao động, tính 0
 * là ăn gian của công ty.
 *
 * Mọi giá trị đều là bội của 0,5 nên biểu diễn được chính xác bằng số thực nhị
 * phân. Đây là lý do đếm ngày công dùng `float` được, còn tiền thì không.
 */
interface WorkingDays
{
    /**
     * Số ngày công trong khoảng `[$tuNgay, $denNgay]`, tính cả hai đầu.
     *
     * Ngày làm cả ngày tính 1, ngày nửa buổi tính 0,5. Ngày nghỉ hằng tuần và
     * ngày lễ tính 0 — nghỉ phép trùng ngày lễ thì không tiêu ngày phép nào,
     * đó là điểm chính của phép đếm này.
     *
     * Ngày lễ đếm theo **ngày thực nghỉ**, không theo ngày lễ danh nghĩa: lễ
     * trùng ngày nghỉ hằng tuần thì nghỉ bù sang ngày kế tiếp (Điều 112 Bộ luật
     * Lao động 2019), và ngày nghỉ bù mới là ngày người ta thật sự không đi làm.
     */
    public function countBetween(string $tuNgay, string $denNgay): float;
}
