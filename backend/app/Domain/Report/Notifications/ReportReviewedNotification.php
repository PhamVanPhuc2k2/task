<?php

declare(strict_types=1);

namespace App\Domain\Report\Notifications;

use App\Domain\Identity\Enums\NotificationType;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Notifications\PreferenceAwareNotification;
use Illuminate\Queue\SerializesModels;

/**
 * Quản lý viết nhận xét trên báo cáo ngày.
 *
 * Chỉ gửi khi **có nhận xét thật**. Đánh dấu đã đọc mà cũng báo thì mỗi sáng
 * nhân viên nhận một thông báo không cần làm gì — cách nhanh nhất để họ tắt hết
 * thông báo của hệ thống, kể cả loại quan trọng.
 */
final class ReportReviewedNotification extends PreferenceAwareNotification
{
    use SerializesModels;

    public function __construct(
        private readonly string $ngay,
        private readonly string $nhanXet,
    ) {}

    public function type(): NotificationType
    {
        return NotificationType::ReportReviewed;
    }

    public function title(): string
    {
        return 'Quản lý có nhận xét về báo cáo của bạn';
    }

    public function message(User $notifiable): string
    {
        return "Báo cáo ngày {$this->ngay}: {$this->nhanXet}";
    }

    public function url(): string
    {
        return '/reports';
    }

    /**
     * @return array<string, mixed>
     */
    protected function extra(): array
    {
        return ['report_date' => $this->ngay];
    }
}
