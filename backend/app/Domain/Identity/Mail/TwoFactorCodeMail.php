<?php

declare(strict_types=1);

namespace App\Domain\Identity\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Email chứa mã xác thực đăng nhập.
 *
 * **Gửi qua hàng đợi, trên một hàng đợi riêng.**
 *
 * Bản đầu cố tình gửi đồng bộ, với lý do: người dùng đang đứng chờ trước màn
 * hình nhập mã, hàng đợi tắc là họ bấm "gửi lại" liên tục. Lý do đó vẫn đúng —
 * nhưng cách giải quyết thì sai. Gửi đồng bộ nghĩa là request đăng nhập ôm
 * trọn vòng đi-về SMTP với Gmail: bắt tay TLS, xác thực, gửi, đóng. Đo thật
 * trên máy dev (POST /api/v1/auth/login, SMTP Gmail thật):
 *
 *   đồng bộ  4,0 – 4,7 giây
 *   hàng đợi 0,49 – 0,52 giây
 *
 * Đó chính là thứ ta đang cố tránh, chỉ là dời sang chỗ khác và tệ hơn, vì nó
 * xảy ra ở MỌI lần đăng nhập chứ không phải khi hàng đợi tắc.
 *
 * Nỗi lo cũ được xử lý bằng hàng đợi RIÊNG (`two-factor.queue`, mặc định
 * `auth`) đứng đầu danh sách ưu tiên của Horizon. Không có nó, một đợt quét
 * deadline đẩy hai trăm email vào hàng sẽ khiến mã OTP của người đang đứng ở
 * màn đăng nhập xếp sau tất cả — đúng kịch bản xấu mà bản đầu lo ngại.
 *
 * Mã chỉ tồn tại trong bộ nhớ — không lưu vào database dạng rõ, không ghi log.
 * Job trong hàng đợi có mang mã ở dạng rõ, nên hàng đợi phải là Redis nội bộ,
 * không phải dịch vụ bên thứ ba.
 */
final class TwoFactorCodeMail extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    /** Thử lại hai lần rồi bỏ: mã chỉ sống 10 phút, thử mãi là gửi mã đã hết hạn. */
    public int $tries = 3;

    public function __construct(
        public readonly string $code,
        public readonly string $userName,
        public readonly int $expiresInMinutes,
    ) {
        $this->onQueue(config()->string('two-factor.queue'));
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            // Đưa mã lên tiêu đề: nhiều người đọc được ngay ở thông báo đẩy
            // mà không cần mở email.
            subject: sprintf('%s là mã đăng nhập Explus của bạn', $this->code),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.two-factor-code',
            with: [
                'code' => $this->code,
                'userName' => $this->userName,
                'expiresInMinutes' => $this->expiresInMinutes,
            ],
        );
    }
}
