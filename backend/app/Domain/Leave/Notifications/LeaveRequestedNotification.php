<?php

declare(strict_types=1);

namespace App\Domain\Leave\Notifications;

use App\Domain\Identity\Enums\NotificationType;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Notifications\PreferenceAwareNotification;
use Illuminate\Queue\SerializesModels;

/**
 * Báo cho quản lý biết có đơn nghỉ đang chờ.
 *
 * Gửi cho **quản lý trực tiếp** (`manager_id`), không gửi cho mọi người có
 * quyền duyệt. Bắn cho cả nhóm thì bốn người cùng nhận một đơn, ba người trong
 * đó không liên quan, và chẳng ai chắc mình có phải người phải xử lý không.
 *
 * Người không có quản lý trực tiếp thì không ai được báo — đơn vẫn nằm trong
 * hộp duyệt của trang Nghỉ phép. Đó là lưới hứng, và nó cũng là lý do hộp duyệt
 * phải hiện số đơn đang chờ ngay trên thanh điều hướng.
 */
final class LeaveRequestedNotification extends PreferenceAwareNotification
{
    use SerializesModels;

    public function __construct(
        private readonly string $tenNguoiNop,
        private readonly string $loai,
        private readonly string $tuNgay,
        private readonly string $denNgay,
        private readonly int $soNgay,
    ) {}

    public function type(): NotificationType
    {
        return NotificationType::LeaveRequested;
    }

    public function title(): string
    {
        return 'Có đơn nghỉ cần duyệt';
    }

    public function message(User $notifiable): string
    {
        return sprintf(
            '%s xin %s %d ngày, từ %s đến %s.',
            $this->tenNguoiNop,
            mb_strtolower($this->loai),
            $this->soNgay,
            $this->ngayViet($this->tuNgay),
            $this->ngayViet($this->denNgay),
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
        return ['start_date' => $this->tuNgay, 'end_date' => $this->denNgay];
    }

    private function ngayViet(string $ngay): string
    {
        return implode('/', array_reverse(explode('-', $ngay)));
    }
}
