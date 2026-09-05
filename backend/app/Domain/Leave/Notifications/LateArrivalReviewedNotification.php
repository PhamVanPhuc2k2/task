<?php

declare(strict_types=1);

namespace App\Domain\Leave\Notifications;

use App\Domain\Identity\Enums\NotificationType;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Notifications\PreferenceAwareNotification;
use App\Support\Time\HumanTime;
use Illuminate\Queue\SerializesModels;

/**
 * Báo cho người nộp biết đơn xin đi muộn đã được xử lý.
 *
 * Trường hợp quan trọng nhất là **bị từ chối**: người ta đinh ninh mình đã xin
 * phép xong rồi cứ thế đi muộn, và hôm sau mới biết ngày đó vẫn bị đánh dấu.
 * Nên lý do từ chối được đưa thẳng vào nội dung thông báo, không bắt mở trang
 * mới đọc được.
 */
final class LateArrivalReviewedNotification extends PreferenceAwareNotification
{
    use SerializesModels;

    public function __construct(
        private readonly bool $dongY,
        private readonly string $ngay,
        private readonly string $gioDuKien,
        private readonly ?string $ghiChu,
    ) {}

    public function type(): NotificationType
    {
        return NotificationType::LateArrivalReviewed;
    }

    public function title(): string
    {
        return $this->dongY
            ? 'Đơn xin đi muộn đã được duyệt'
            : 'Đơn xin đi muộn bị từ chối';
    }

    public function message(User $notifiable): string
    {
        $ngay = HumanTime::ngay($this->ngay);

        if (! $this->dongY) {
            return sprintf(
                'Đơn xin đi muộn ngày %s không được duyệt.%s',
                $ngay,
                $this->ghiChu === null ? '' : ' Lý do: '.$this->ghiChu,
            );
        }

        // Nhắc lại mốc giờ ngay trong thông báo: đơn chỉ bao TỚI ĐÚNG giờ đã
        // xin, và đó là thứ người nhận cần nhớ nhất.
        return sprintf(
            'Ngày %s bạn được đến muộn, tới %s. Đến sau mốc đó vẫn tính là đi muộn.',
            $ngay,
            $this->gioDuKien,
        );
    }

    public function url(): string
    {
        return '/leave';
    }

    /**
     * @return array<string, mixed>
     */
    protected function extra(): array
    {
        return ['date' => $this->ngay, 'approved' => $this->dongY];
    }
}
