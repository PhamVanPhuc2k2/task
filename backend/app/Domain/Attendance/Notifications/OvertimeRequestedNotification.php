<?php

declare(strict_types=1);

namespace App\Domain\Attendance\Notifications;

use App\Domain\Identity\Enums\NotificationType;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Notifications\PreferenceAwareNotification;
use App\Support\Time\HumanTime;
use Illuminate\Queue\SerializesModels;

/**
 * Báo cho quản lý biết có đăng ký làm thêm giờ đang chờ.
 *
 * Gửi cho **quản lý trực tiếp** (`manager_id`), không bắn cho mọi người có
 * quyền duyệt — cùng lý do đã ghi ở các loại đơn khác.
 *
 * Nội dung nói ra **hệ số** chứ không chỉ nói số giờ: 2 tiếng ngày lễ và 2
 * tiếng ngày thường là hai khoản tiền chênh nhau gấp đôi, và người duyệt cần
 * biết mình đang duyệt cái nào trước khi mở ứng dụng ra.
 */
final class OvertimeRequestedNotification extends PreferenceAwareNotification
{
    use SerializesModels;

    public function __construct(
        private readonly string $tenNguoiNop,
        private readonly string $ngay,
        private readonly string $tuGio,
        private readonly string $denGio,
        private readonly int $soPhut,
        private readonly int $heSo,
    ) {}

    public function type(): NotificationType
    {
        return NotificationType::OvertimeRequested;
    }

    public function title(): string
    {
        return 'Có đăng ký làm thêm giờ cần duyệt';
    }

    public function message(User $notifiable): string
    {
        return sprintf(
            '%s đăng ký làm thêm ngày %s, từ %s đến %s (%s, hệ số %d%%).',
            $this->tenNguoiNop,
            HumanTime::ngay($this->ngay),
            $this->tuGio,
            $this->denGio,
            HumanTime::gioPhut($this->soPhut),
            $this->heSo,
        );
    }

    public function url(): string
    {
        return '/attendance';
    }

    /**
     * @return array<string, mixed>
     */
    protected function extra(): array
    {
        return [
            'work_date' => $this->ngay,
            'minutes' => $this->soPhut,
            'rate_percent' => $this->heSo,
        ];
    }
}
