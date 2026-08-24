<?php

declare(strict_types=1);

namespace App\Domain\Leave\Notifications;

use App\Domain\Identity\Enums\NotificationType;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Notifications\PreferenceAwareNotification;
use Illuminate\Queue\SerializesModels;

/**
 * Báo cho người nộp biết đơn đã được xử lý.
 *
 * Gửi cho CẢ hai kết quả — duyệt lẫn từ chối. Chỉ báo khi từ chối thì im lặng
 * trở thành tín hiệu mơ hồ: người ta không biết đơn đã được duyệt hay còn đang
 * nằm đâu đó, và sẽ đi hỏi lại bằng Zalo. Đúng thứ tính năng này muốn bỏ.
 */
final class LeaveReviewedNotification extends PreferenceAwareNotification
{
    use SerializesModels;

    public function __construct(
        private readonly bool $dongY,
        private readonly string $tuNgay,
        private readonly string $denNgay,
        private readonly ?string $ghiChu,
    ) {}

    public function type(): NotificationType
    {
        return NotificationType::LeaveReviewed;
    }

    public function title(): string
    {
        return $this->dongY ? 'Đơn nghỉ đã được duyệt' : 'Đơn nghỉ bị từ chối';
    }

    public function message(User $notifiable): string
    {
        $khoang = sprintf(
            '%s – %s',
            $this->ngayViet($this->tuNgay),
            $this->ngayViet($this->denNgay),
        );

        $cau = $this->dongY
            ? "Đơn nghỉ {$khoang} đã được duyệt."
            : "Đơn nghỉ {$khoang} không được duyệt.";

        return $this->ghiChu === null || $this->ghiChu === ''
            ? $cau
            : $cau.' '.$this->ghiChu;
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
        return ['approved' => $this->dongY, 'start_date' => $this->tuNgay];
    }

    private function ngayViet(string $ngay): string
    {
        return implode('/', array_reverse(explode('-', $ngay)));
    }
}
