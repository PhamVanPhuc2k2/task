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
 * Gửi cho người **thuộc diện phải nộp báo cáo hôm nay** mà chưa nộp — xem
 * `RemindMissingReportsCommand` để biết diện đó gồm những ai. Người có đơn nghỉ
 * đã duyệt và ngày lễ đều đã bị loại từ trước khi tới đây.
 *
 * Câu chữ cố ý không mang giọng khiển trách. Đại đa số trường hợp chỉ là quên,
 * và một dòng nhắc gọn thì người ta mở lên viết; một dòng trách móc thì người
 * ta viết cho xong.
 *
 * ## Hai câu chữ, và vì sao KHÔNG bao giờ nói "0 phút"
 *
 * Từ khi lời nhắc không còn dựa vào giờ công, người nhận có thể là người hệ
 * thống không đo được phút nào — đi gặp khách, họp với đối tác, hướng dẫn khách
 * vận hành. Với họ, câu "Hệ thống ghi nhận 0 phút làm việc hôm nay" đọc như một
 * lời buộc tội cho đúng cái ngày họ làm việc vất vả nhất.
 *
 * Nên có hai biến thể. Có giờ thì nói ra con số, vì nó giúp người đọc nhớ lại
 * hôm nay mình đã làm gì. Không có giờ thì không nhắc tới giờ một chữ nào, và
 * nói thẳng rằng làm việc bên ngoài vẫn cần báo cáo.
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
        if ($this->soPhut <= 0) {
            return 'Hôm nay chưa thấy báo cáo của bạn. Viết vài dòng về việc đã '
                .'làm — kể cả khi hôm nay bạn làm việc bên ngoài — để quản lý '
                .'nắm được tiến độ.';
        }

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
