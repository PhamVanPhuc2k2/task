<?php

declare(strict_types=1);

namespace App\Domain\Report\Notifications;

use App\Domain\Identity\Enums\NotificationType;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Notifications\PreferenceAwareNotification;
use Illuminate\Queue\SerializesModels;

/**
 * Cuối ngày mà chưa nộp báo cáo.
 *
 * **Chỉ gửi cho người hôm nay thật sự có giờ làm.** Nhắc cả những người nghỉ
 * phép hay nghỉ ốm là cách nhanh nhất để mọi người coi thông báo của hệ thống
 * là tiếng ồn — và khi đó thông báo thật sự quan trọng cũng trôi qua.
 *
 * Câu chữ cố ý không mang giọng khiển trách. Đại đa số trường hợp chỉ là quên,
 * và một dòng nhắc gọn thì người ta mở lên viết; một dòng trách móc thì người
 * ta viết cho xong.
 */
final class DailyReportMissingNotification extends PreferenceAwareNotification
{
    use SerializesModels;

    public function __construct(
        private readonly string $ngay,
        private readonly int $soPhut,
    ) {}

    public function type(): NotificationType
    {
        return NotificationType::ReportMissing;
    }

    public function title(): string
    {
        return 'Hôm nay bạn chưa nộp báo cáo';
    }

    public function message(User $notifiable): string
    {
        $gio = intdiv($this->soPhut, 60);
        $phut = $this->soPhut % 60;

        $doDai = $gio > 0
            ? ($phut > 0 ? "{$gio} giờ {$phut} phút" : "{$gio} giờ")
            : "{$phut} phút";

        return "Hệ thống ghi nhận {$doDai} làm việc hôm nay. Viết vài dòng về "
            .'việc đã làm để quản lý nắm được tiến độ.';
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
        return [
            'report_date' => $this->ngay,
            'worked_minutes' => $this->soPhut,
        ];
    }
}
