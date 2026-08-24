<?php

declare(strict_types=1);

namespace App\Domain\Payroll\Enums;

/**
 * Biến cố được ghi vào nhật ký lương.
 *
 * Có cả biến cố **xem**, khác với mọi nhật ký khác trong hệ thống. Với lương,
 * "ai đã xem bảng lương phòng Kinh doanh" là câu hỏi có thật và sẽ có người
 * hỏi — mà không ghi thì không ai trả lời được.
 */
enum PayrollAuditEvent: string
{
    /** Xem bảng lương của nhiều người. `subject_id` để trống. */
    case ViewedList = 'viewed_list';

    /** Mở lịch sử lương của một người cụ thể. */
    case ViewedPerson = 'viewed_person';

    /** Đặt mức lương mới. */
    case SalaryChanged = 'salary_changed';

    public function label(): string
    {
        return match ($this) {
            self::ViewedList => 'Xem bảng lương',
            self::ViewedPerson => 'Xem lịch sử lương của một người',
            self::SalaryChanged => 'Đặt mức lương mới',
        };
    }
}
