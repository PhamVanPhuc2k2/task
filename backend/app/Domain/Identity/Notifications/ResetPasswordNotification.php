<?php

declare(strict_types=1);

namespace App\Domain\Identity\Notifications;

use App\Domain\Identity\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Link đặt lại mật khẩu.
 *
 * **Cố ý KHÔNG kế thừa `PreferenceAwareNotification`.** Lớp kia đọc tuỳ chọn
 * nhận thông báo của người dùng — mà tuỳ chọn đó chỉ có nghĩa với người đang
 * dùng hệ thống bình thường. Ở đây người ta không đăng nhập được: nếu họ từng
 * tắt email thì họ sẽ không bao giờ lấy lại được tài khoản, và không hiểu vì
 * sao. Đây là email hạ tầng, không phải thông báo nghiệp vụ.
 *
 * Cũng vì vậy nó chỉ đi qua kênh mail, không ghi vào bảng `notifications`:
 * chuông trong ứng dụng chẳng để làm gì với người chưa vào được.
 *
 * Gửi qua hàng đợi `auth` — cùng hàng với mã OTP, đứng đầu ưu tiên của Horizon.
 * Người dùng đang đứng chờ trước màn hình, và một đợt quét deadline đẩy hai
 * trăm email vào hàng sẽ khiến link này xếp sau tất cả.
 */
final class ResetPasswordNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Thử lại hai lần rồi thôi: link chỉ sống 60 phút, thử mãi là gửi một link
     * đã hết hạn cho người đang chờ.
     */
    public int $tries = 3;

    public function __construct(private readonly string $token)
    {
        $this->onQueue(config()->string('two-factor.queue'));
    }

    /**
     * @return list<string>
     */
    public function via(User $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $phut = config()->integer('auth.passwords.users.expire');

        return (new MailMessage)
            ->subject('Đặt lại mật khẩu — Explus')
            ->markdown('mail.reset-password', [
                'userName' => $notifiable->name,
                'actionUrl' => $this->duongDan($notifiable),
                'expireMinutes' => $phut,
            ]);
    }

    /**
     * Link trỏ về **giao diện**, không phải về API.
     *
     * Người dùng bấm vào email và phải thấy một trang nhập mật khẩu mới, không
     * phải một dòng JSON. Frontend cầm token rồi POST ngược lại API.
     */
    private function duongDan(User $notifiable): string
    {
        return sprintf(
            '%s/reset-password?token=%s&email=%s',
            rtrim(config()->string('app.frontend_url'), '/'),
            $this->token,
            urlencode($notifiable->email),
        );
    }
}
