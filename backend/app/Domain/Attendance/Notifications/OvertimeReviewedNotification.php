<?php

declare(strict_types=1);

namespace App\Domain\Attendance\Notifications;

use App\Domain\Identity\Enums\NotificationType;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Notifications\PreferenceAwareNotification;
use App\Support\Time\HumanTime;
use Illuminate\Queue\SerializesModels;

/**
 * Báo cho người đăng ký biết đơn làm thêm giờ đã được xử lý.
 *
 * Trường hợp quan trọng nhất là **bị từ chối**: không báo thì người ta ở lại
 * làm hai tiếng buổi tối cho một khoản tiền sẽ không bao giờ được trả. Đây là
 * loại thông báo mà đến muộn cũng gần như đến không.
 *
 * Khi được duyệt thì nói ra **số phút người duyệt chốt** và **hệ số đã đóng
 * băng**, không phải số đã đăng ký. Hai số có thể khác nhau — "đăng ký 3 tiếng,
 * duyệt 2" là chuyện thường — và người nộp cần biết ngay chứ không phải tự đi
 * so lại.
 */
final class OvertimeReviewedNotification extends PreferenceAwareNotification
{
    use SerializesModels;

    public function __construct(
        private readonly bool $duocDuyet,
        private readonly string $ngay,
        private readonly int $soPhut,
        private readonly ?int $heSo,
        private readonly ?string $ghiChu,
    ) {}

    public function type(): NotificationType
    {
        return NotificationType::OvertimeReviewed;
    }

    public function title(): string
    {
        return $this->duocDuyet
            ? 'Đăng ký làm thêm giờ đã được duyệt'
            : 'Đăng ký làm thêm giờ bị từ chối';
    }

    public function message(User $notifiable): string
    {
        $ngay = HumanTime::ngay($this->ngay);

        if (! $this->duocDuyet) {
            return sprintf(
                'Đăng ký làm thêm ngày %s bị từ chối. Lý do: %s',
                $ngay,
                $this->ghiChu ?? 'không ghi',
            );
        }

        $cot = sprintf(
            'Ngày %s được duyệt %s làm thêm, hệ số %d%%.',
            $ngay,
            HumanTime::gioPhut($this->soPhut),
            $this->heSo ?? 0,
        );

        return $this->ghiChu === null || $this->ghiChu === ''
            ? $cot
            : $cot.' '.$this->ghiChu;
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
            'approved' => $this->duocDuyet,
            'minutes' => $this->soPhut,
            'rate_percent' => $this->heSo,
        ];
    }
}
