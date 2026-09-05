<?php

declare(strict_types=1);

namespace App\Domain\Leave\Notifications;

use App\Domain\Identity\Enums\NotificationType;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Notifications\PreferenceAwareNotification;
use App\Domain\Leave\Enums\AttendanceExceptionType;
use App\Support\Time\HumanTime;
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
        private readonly int $soPhutLech,
        /**
         * Đi muộn hay về sớm.
         *
         * Dùng chung một loại thông báo cho cả hai: người duyệt nhận cùng một
         * việc phải làm, và tách thành hai loại thì họ phải bật/tắt hai ô trong
         * Cài đặt thông báo cho cùng một thứ.
         */
        private readonly AttendanceExceptionType $loai = AttendanceExceptionType::Late,
    ) {}

    public function type(): NotificationType
    {
        return NotificationType::LateArrivalRequested;
    }

    public function title(): string
    {
        return sprintf('Có đơn xin %s cần duyệt', mb_strtolower($this->loai->label()));
    }

    public function message(User $notifiable): string
    {
        return $this->loai === AttendanceExceptionType::Early
            ? sprintf(
                '%s xin về sớm ngày %s, dự kiến rời lúc %s (sớm %d phút).',
                $this->tenNguoiNop,
                HumanTime::ngay($this->ngay),
                $this->gioDuKien,
                $this->soPhutLech,
            )
            : sprintf(
                '%s xin đi muộn ngày %s, dự kiến đến lúc %s (muộn %d phút).',
                $this->tenNguoiNop,
                HumanTime::ngay($this->ngay),
                $this->gioDuKien,
                $this->soPhutLech,
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
        return [
            'date' => $this->ngay,
            'type' => $this->loai->value,
            'expected_time' => $this->gioDuKien,
        ];
    }
}
