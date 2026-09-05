<?php

declare(strict_types=1);

namespace App\Domain\Attendance\Enums;

/**
 * Trạng thái của một kỳ công.
 *
 * ## Không có kỳ nào nghĩa là kỳ đang MỞ
 *
 * Bảng `attendance_periods` chỉ có dòng cho kỳ **đã từng bị chốt**. Kỳ chưa ai
 * động tới thì không có dòng nào, và mặc định là mở.
 *
 * Cách khác là sinh sẵn một dòng cho mọi tháng — kể cả tháng chưa tới. Cách đó
 * buộc phải có một job sinh dòng, và một tháng thiếu dòng vì job không chạy sẽ
 * bị coi là "không tồn tại" thay vì "đang mở". Dòng vắng mặt nghĩa là mặc định,
 * đúng quy ước đã dùng ở `site_settings` và `work_days`.
 */
enum PeriodStatus: string
{
    /** Đã chốt sổ — không ai sửa được số liệu của kỳ này. */
    case Closed = 'closed';

    /**
     * Đã mở khoá trở lại sau khi chốt.
     *
     * Khác với "chưa từng chốt" ở chỗ nó để lại vết: kỳ này đã từng là căn cứ
     * trả lương, rồi có người mở ra sửa. Câu hỏi "vì sao số liệu tháng 9 đổi
     * sau khi đã trả lương" phải có đáp án, và đáp án nằm ở đây cùng nhật ký
     * kiểm toán.
     */
    case Open = 'open';

    public function label(): string
    {
        return match ($this) {
            self::Closed => 'Đã chốt',
            self::Open => 'Đã mở khoá lại',
        };
    }

    public function isLocked(): bool
    {
        return $this === self::Closed;
    }
}
