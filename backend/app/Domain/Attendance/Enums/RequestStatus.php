<?php

declare(strict_types=1);

namespace App\Domain\Attendance\Enums;

/**
 * Trạng thái một đơn của miền Attendance — giải trình công và đăng ký làm thêm
 * giờ dùng chung.
 *
 * Luồng một chiều: `pending` → `approved` hoặc `rejected`. Người nộp tự rút
 * được khi còn `pending` (`cancelled`).
 *
 * ## MỘT enum cho cả miền, không phải mỗi loại đơn một bản
 *
 * Hai loại đơn đi qua đúng một vòng đời, do cùng một người duyệt, với cùng một
 * quyền. Tách ra chỉ để có tên khác là nhân đôi chỗ phải sửa mỗi khi vòng đời
 * đổi — cùng lý do đã ghi ở `LateArrivalRequest`, và đó là lý do enum này mang
 * tên chung thay vì tên của loại đơn đầu tiên dùng nó.
 *
 * ## Vì sao không dùng lại `LeaveStatus`
 *
 * Vòng đời giống hệt, nhưng `LeaveStatus` nằm ở miền Leave và luật tầng cấm
 * Attendance phụ thuộc Leave. Đây cũng đã là nếp của dự án: `BonusPoolStatus`
 * và `DailyReportStatus` cùng bốn trạng thái này, mỗi miền một bản.
 *
 * Cái giá của hướng ngược lại — một enum dùng chung ở tầng Support — là mọi
 * miền cùng phụ thuộc vào một thứ mà không miền nào sở hữu, và lần đầu một miền
 * cần thêm trạng thái riêng thì cả bốn miền cùng thấy nó.
 *
 * **Đã duyệt thì không rút lại được.** Đơn giải trình được duyệt đã ghi một
 * dòng vào `work_days`; đơn làm thêm giờ được duyệt là một khoản tiền đã hứa
 * trả. Rút ngược lại là để một con số trong quá khứ đổi mà không ai biết. Cần
 * sửa thì người duyệt sửa thẳng, và vết cũ giữ nguyên.
 */
enum RequestStatus: string
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
     * Đơn này có đang chặn chỗ không.
     *
     * Đơn bị từ chối hoặc đã rút thì KHÔNG chặn: bị từ chối rồi nộp lại cho rõ
     * hơn là chuyện bình thường, và cấm điều đó nghĩa là người ta phải đi nhắn
     * tin cho quản lý — đúng cái việc mà những module này sinh ra để thay.
     */
    public function isBlocking(): bool
    {
        return $this === self::Pending || $this === self::Approved;
    }
}
