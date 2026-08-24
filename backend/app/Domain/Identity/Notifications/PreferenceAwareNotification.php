<?php

declare(strict_types=1);

namespace App\Domain\Identity\Notifications;

use App\Domain\Identity\Enums\NotificationChannel;
use App\Domain\Identity\Enums\NotificationType;
use App\Domain\Identity\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Lớp nền cho mọi thông báo của hệ thống.
 *
 * Hai việc gom về đây:
 *
 * 1. **Chọn kênh theo tuỳ chọn của người nhận.** Mỗi thông báo con chỉ khai
 *    mình thuộc loại nào; việc quyết định gửi qua kênh nào là chuyện chung.
 *    Để từng lớp con tự làm là sớm muộn có lớp quên đọc tuỳ chọn, và người
 *    dùng tắt thông báo rồi vẫn nhận — thứ làm mất niềm tin vào cả trang cài
 *    đặt.
 *
 * 2. **Cấu trúc dữ liệu lưu vào bảng `notifications`.** Frontend đọc đúng một
 *    bộ khoá cho mọi loại, nên thêm loại mới không phải sửa giao diện.
 *
 * Đặt ở miền Identity vì tuỳ chọn nhận thông báo là thuộc tính của người dùng.
 * Identity là shared kernel nên các miền khác kế thừa được — xem README, "Quy
 * tắc phụ thuộc".
 */
abstract class PreferenceAwareNotification extends Notification implements ShouldQueue
{
    use Queueable;

    abstract public function type(): NotificationType;

    /** Tiêu đề ngắn, hiện trong chuông và làm tiêu đề email. */
    abstract public function title(): string;

    /** Một câu mô tả chuyện gì vừa xảy ra. */
    abstract public function message(User $notifiable): string;

    /** Đường dẫn trong ứng dụng để bấm vào từ thông báo. */
    abstract public function url(): string;

    /**
     * @return list<string>
     */
    public function via(User $notifiable): array
    {
        $kenh = [];

        foreach (NotificationChannel::cases() as $channel) {
            if ($notifiable->wantsNotification($this->type(), $channel)) {
                $kenh[] = $channel->laravelChannel();
            }
        }

        return $kenh;
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(User $notifiable): array
    {
        return [
            'type' => $this->type()->value,
            'title' => $this->title(),
            'message' => $this->message($notifiable),
            'url' => $this->url(),
            ...$this->extra(),
        ];
    }

    public function toMail(User $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->title().' — Explus')
            ->markdown('mail.notification', [
                'userName' => $notifiable->name,
                'title' => $this->title(),
                'message' => $this->message($notifiable),
                'actionUrl' => rtrim(config()->string('app.frontend_url'), '/').$this->url(),
                'settingsLabel' => $this->type()->label(),
            ]);
    }

    /**
     * Dữ liệu riêng của từng loại, trộn thêm vào phần chung.
     *
     * @return array<string, mixed>
     */
    protected function extra(): array
    {
        return [];
    }
}
