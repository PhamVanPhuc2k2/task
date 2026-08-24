<?php

declare(strict_types=1);

namespace App\Domain\Leave\Notifications;

use App\Domain\Identity\Enums\NotificationType;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Notifications\PreferenceAwareNotification;
use Illuminate\Queue\SerializesModels;

/**
 * Báo cho quản lý biết có đơn xin đi muộn đang chờ.
 *
 * Cùng nguyên tắc với đơn nghỉ, và vì đúng những lý do đó: gửi cho **quản lý
 * trực tiếp** (`manager_id`), không gửi cho mọi người có quyền duyệt. Bắn cho
 * cả nhóm thì bốn người cùng nhận một đơn, ba người trong đó không liên quan,
 * và chẳng ai chắc mình có phải người phải xử lý không.
 *
 * Người không có quản lý trực tiếp thì không ai được báo — đơn vẫn nằm trong
 * hộp duyệt của trang Nghỉ phép, tab Đi muộn. Đó là lưới hứng có chủ ý.
 */
final class LateArrivalRequestedNotification extends PreferenceAwareNotification
{
    use SerializesModels;

    public function __construct(
        private readonly string $tenNguoiNop,
        private readonly string $ngay,
        private readonly string $gioDuKien,
        private readonly int $soPhutMuon,
    ) {}

    public function type(): NotificationType
    {
        return NotificationType::LateArrivalRequested;
    }

    public function title(): string
    {
        return 'Có đơn xin đi muộn cần duyệt';
    }

    public function message(User $notifiable): string
    {
        return sprintf(
            '%s xin đi muộn ngày %s, dự kiến đến lúc %s (muộn %d phút).',
            $this->tenNguoiNop,
            $this->ngayViet($this->ngay),
            $this->gioDuKien,
            $this->soPhutMuon,
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
        return ['date' => $this->ngay, 'expected_arrival' => $this->gioDuKien];
    }

    private function ngayViet(string $ngay): string
    {
        return implode('/', array_reverse(explode('-', $ngay)));
    }
}
