<?php

declare(strict_types=1);

namespace App\Domain\Payroll\Notifications;

use App\Domain\Identity\Enums\NotificationType;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Notifications\PreferenceAwareNotification;
use Illuminate\Queue\SerializesModels;

/**
 * Báo cho nhân viên khi quỹ thưởng của một dự án được chốt.
 *
 * Không có thông báo này thì cả cơ chế minh bạch mất một nửa: nhân viên xem
 * được phần của mình nhưng **không ai biết mà vào xem**.
 *
 * **Cố ý KHÔNG nói số tiền trong thông báo.** Hai lý do:
 *
 *   1. Thông báo này gửi cả qua email. Số tiền thưởng nằm trong hộp thư cá nhân
 *      là một bản sao dữ liệu nhạy cảm ngoài tầm kiểm soát của hệ thống — hộp
 *      thư chuyển tiếp, thư ký đọc hộ, máy mượn còn đăng nhập.
 *   2. Số tiền đi kèm **lý do** mới có nghĩa. Cắt lý do ra khỏi con số là mời
 *      người ta so bì trước khi hiểu.
 *
 * Nên thông báo chỉ dẫn người dùng vào `/bonus`, nơi có đủ cả hai.
 *
 * Gửi cho **mọi người trong danh sách chia**, kể cả người được 0 đồng: họ vẫn
 * cần đọc lý do. Im lặng với riêng nhóm đó là cách chắc chắn nhất để sinh tin
 * đồn.
 */
final class BonusLockedNotification extends PreferenceAwareNotification
{
    use SerializesModels;

    public function __construct(
        private readonly string $tenDuAn,
    ) {}

    public function type(): NotificationType
    {
        return NotificationType::BonusLocked;
    }

    public function title(): string
    {
        return 'Thưởng dự án đã được chốt';
    }

    public function message(User $notifiable): string
    {
        return "Quỹ thưởng dự án “{$this->tenDuAn}” đã chốt. Xem phần của bạn và lý do trong mục Thưởng dự án.";
    }

    public function url(): string
    {
        return '/bonus';
    }

    /**
     * @return array<string, mixed>
     */
    protected function extra(): array
    {
        // Chỉ tên dự án. Không có số tiền, không có id phần chia — dữ liệu thừa
        // trong bảng `notifications` là dữ liệu sẽ lọt ra ở đâu đó.
        return ['project_name' => $this->tenDuAn];
    }
}
