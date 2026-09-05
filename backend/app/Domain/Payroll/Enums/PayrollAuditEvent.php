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

    /**
     * Chốt sổ và mở khoá một kỳ công.
     *
     * Nằm ở nhật ký PAYROLL chứ không phải nhật ký nhân sự, vì đây là hành vi
     * quyết định số liệu dùng để trả lương — cùng họ với việc đổi mức lương.
     * Câu hỏi "vì sao số liệu tháng 9 đổi sau khi đã trả lương" phải có đáp án,
     * và đáp án nằm ở đây.
     */
    case PeriodClosed = 'period_closed';
    case PeriodReopened = 'period_reopened';

    /**
     * Sửa quỹ phép năm của một người.
     *
     * Cũng ở nhật ký PAYROLL, và vì đúng lý do đó: phép chưa nghỉ hết phải được
     * thanh toán khi thôi việc (Điều 113 khoản 4), nên cộng thêm một ngày phép
     * là cộng thêm một khoản tiền công ty có thể phải trả.
     */
    case LeaveBalanceChanged = 'leave_balance_changed';

    public function label(): string
    {
        return match ($this) {
            self::ViewedList => 'Xem bảng lương',
            self::ViewedPerson => 'Xem lịch sử lương của một người',
            self::SalaryChanged => 'Đặt mức lương mới',
            self::PeriodClosed => 'Chốt sổ kỳ công',
            self::PeriodReopened => 'Mở khoá kỳ công',
            self::LeaveBalanceChanged => 'Sửa quỹ phép năm',
        };
    }
}
