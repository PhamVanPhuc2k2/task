<?php

declare(strict_types=1);

namespace App\Domain\Leave\Enums;

/**
 * Trạng thái đơn nghỉ.
 *
 * Luồng một chiều: `pending` → `approved` hoặc `rejected`. Người nộp tự rút
 * được đơn khi còn `pending` (`cancelled`).
 *
 * **Đã duyệt thì không quay lại được.** Ngày nghỉ đã duyệt là căn cứ để bảng
 * công miễn chấm; rút ngược lại sau đó nghĩa là bảng công của một ngày trong
 * quá khứ đổi nghĩa mà không ai biết. Cần sửa thì nộp đơn mới, giữ nguyên vết
 * cũ — cùng nguyên tắc với quỹ thưởng đã chốt.
 */
enum LeaveStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Chờ duyệt',
            self::Approved => 'Đã duyệt',
            self::Rejected => 'Từ chối',
            self::Cancelled => 'Đã rút',
        };
    }

    /** Đơn còn sửa hoặc rút được không. */
    public function isEditable(): bool
    {
        return $this === self::Pending;
    }

    /** Đơn này có làm ngày nghỉ được miễn chấm công không. */
    public function exemptsAttendance(): bool
    {
        return $this === self::Approved;
    }
}
