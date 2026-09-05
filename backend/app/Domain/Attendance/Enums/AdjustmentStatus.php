<?php

declare(strict_types=1);

namespace App\Domain\Attendance\Enums;

/**
 * Trạng thái một đơn giải trình công.
 *
 * Luồng một chiều: `pending` → `approved` hoặc `rejected`. Người nộp tự rút
 * được khi còn `pending` (`cancelled`).
 *
 * ## Vì sao không dùng lại `LeaveStatus`
 *
 * Vòng đời giống hệt, nhưng `LeaveStatus` nằm ở miền Leave và luật tầng cấm
 * Attendance phụ thuộc Leave — muốn báo qua miền khác thì bắn Event, không gọi
 * thẳng (xem `deptrac.yaml`). Đây cũng đã là nếp của dự án: `BonusPoolStatus`
 * và `DailyReportStatus` cùng bốn trạng thái này, mỗi miền một bản.
 *
 * Cái giá là bốn enum có thể lệch nhau. Cái giá của hướng ngược lại — một enum
 * dùng chung ở tầng Support — là mọi miền cùng phụ thuộc vào một thứ mà không
 * miền nào sở hữu, và lần đầu một miền cần thêm trạng thái riêng thì cả bốn
 * miền cùng thấy nó.
 *
 * **Đã duyệt thì không rút lại được.** Đơn được duyệt đã ghi một dòng vào
 * `work_days` — số giờ của một ngày trong quá khứ đã đổi. Rút ngược lại là để
 * số đó đổi lần nữa mà không ai biết. Cần sửa thì người duyệt sửa thẳng ngày
 * công, và vết cũ giữ nguyên.
 */
enum AdjustmentStatus: string
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

    /**
     * Đơn này có đang chặn chỗ của (người, ngày) không.
     *
     * Đơn bị từ chối hoặc đã rút thì KHÔNG chặn: bị từ chối rồi giải trình lại
     * cho rõ hơn là chuyện bình thường, và cấm điều đó nghĩa là người ta phải
     * đi nhắn tin cho quản lý — đúng cái việc mà module này sinh ra để thay.
     */
    public function isBlocking(): bool
    {
        return $this === self::Pending || $this === self::Approved;
    }
}
