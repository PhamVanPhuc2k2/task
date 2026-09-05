<?php

declare(strict_types=1);

namespace App\Domain\Attendance\Notifications;

use App\Domain\Identity\Enums\NotificationType;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Notifications\PreferenceAwareNotification;
use App\Support\Time\HumanTime;
use Illuminate\Queue\SerializesModels;

/**
 * Báo cho người nộp biết đơn giải trình đã được xử lý.
 *
 * Trường hợp quan trọng nhất là **bị từ chối**: không báo thì người ta đinh
 * ninh đã giải trình xong, và chỉ phát hiện ra khi bảng lương tháng đó về —
 * lúc kỳ công đã chốt và không sửa lại được nữa.
 *
 * Khi được duyệt thì nói ra CON SỐ NGƯỜI DUYỆT CHỐT, không phải con số đã xin.
 * Hai số có thể khác nhau, và người nộp cần biết ngay chứ không phải tự đi so
 * lại bảng công.
 */
final class AdjustmentReviewedNotification extends PreferenceAwareNotification
{
    use SerializesModels;

    public function __construct(
        private readonly bool $duocDuyet,
        private readonly string $ngay,
        private readonly ?int $soPhutDuyet,
        private readonly ?string $ghiChu,
    ) {}

    public function type(): NotificationType
    {
        return NotificationType::AdjustmentReviewed;
    }

    public function title(): string
    {
        return $this->duocDuyet
            ? 'Đơn giải trình công đã được duyệt'
            : 'Đơn giải trình công bị từ chối';
    }

    public function message(User $notifiable): string
    {
        $ngay = HumanTime::ngay($this->ngay);

        if (! $this->duocDuyet) {
            return sprintf(
                'Đơn giải trình ngày %s bị từ chối. Lý do: %s',
                $ngay,
                $this->ghiChu ?? 'không ghi',
            );
        }

        $cot = $this->soPhutDuyet === null
            ? sprintf('Ngày %s đã được bỏ qua, giữ nguyên số giờ hệ thống đo được.', $ngay)
            : sprintf('Ngày %s được ghi nhận %s.', $ngay, HumanTime::gioPhut($this->soPhutDuyet));

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
            'approved_minutes' => $this->soPhutDuyet,
        ];
    }
}
