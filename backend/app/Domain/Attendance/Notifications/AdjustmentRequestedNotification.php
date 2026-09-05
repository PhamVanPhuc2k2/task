<?php

declare(strict_types=1);

namespace App\Domain\Attendance\Notifications;

use App\Domain\Identity\Enums\NotificationType;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Notifications\PreferenceAwareNotification;
use App\Support\Time\HumanTime;
use Illuminate\Queue\SerializesModels;

/**
 * Báo cho quản lý biết có đơn giải trình công đang chờ.
 *
 * Gửi cho **quản lý trực tiếp** (`manager_id`), không bắn cho mọi người có
 * quyền duyệt — cùng lý do đã ghi ở `LateArrivalRequestedNotification`: bắn cho
 * cả nhóm thì bốn người cùng nhận một đơn, ba người trong đó không liên quan,
 * và chẳng ai chắc mình có phải người phải xử lý không.
 *
 * Loại thông báo này gấp hơn hai loại đơn kia ở một điểm: nó có **hạn chót
 * cứng**. Chốt sổ kỳ công rồi thì đơn không duyệt được nữa, kể cả bởi giám đốc.
 * Nên nội dung nói thẳng ngày công đang nói tới, để người duyệt biết mình còn
 * bao lâu.
 */
final class AdjustmentRequestedNotification extends PreferenceAwareNotification
{
    use SerializesModels;

    public function __construct(
        private readonly string $tenNguoiNop,
        private readonly string $ngay,
        private readonly ?int $soPhutDeNghi,
    ) {}

    public function type(): NotificationType
    {
        return NotificationType::AdjustmentRequested;
    }

    public function title(): string
    {
        return 'Có đơn giải trình công cần duyệt';
    }

    public function message(User $notifiable): string
    {
        return $this->soPhutDeNghi === null
            ? sprintf(
                '%s giải trình ngày công %s và xin bỏ qua ngày đó.',
                $this->tenNguoiNop,
                HumanTime::ngay($this->ngay),
            )
            : sprintf(
                '%s giải trình ngày công %s, đề nghị ghi nhận %s.',
                $this->tenNguoiNop,
                HumanTime::ngay($this->ngay),
                HumanTime::gioPhut($this->soPhutDeNghi),
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
            'requested_minutes' => $this->soPhutDeNghi,
        ];
    }
}
