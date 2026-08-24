<?php

declare(strict_types=1);

namespace App\Domain\Identity\Services;

use App\Domain\Identity\Contracts\SetupPayload;
use App\Domain\Identity\Contracts\TwoFactorProvider;
use App\Domain\Identity\Models\User;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use PragmaRX\Google2FA\Google2FA;

/**
 * Mã OTP theo chuẩn TOTP (RFC 6238) — Google Authenticator, Microsoft
 * Authenticator, Authy đều dùng chuẩn này.
 *
 * Chạy hoàn toàn offline: không tốn tiền mỗi lần đăng nhập, không phụ thuộc
 * nhà mạng hay nhà cung cấp mail, và mạnh hơn email OTP.
 *
 * Không phải kênh mặc định — công ty chọn email OTP để nhân viên không phải
 * cài thêm ứng dụng. Bật lại bằng `TWO_FACTOR_DRIVER=totp`.
 */
final readonly class TotpProvider implements TwoFactorProvider
{
    public function __construct(
        private Google2FA $google2fa,
        private string $issuer,
    ) {}

    public function startSetup(User $user): SetupPayload
    {
        $secret = $this->google2fa->generateSecretKey();

        // CHƯA bật 2FA ở đây — chỉ bật sau khi người dùng nhập đúng mã đầu
        // tiên. Bật ngay mà họ quét QR hỏng là khoá họ ra ngoài vĩnh viễn.
        $user->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_confirmed_at' => null,
        ])->save();

        $url = $this->google2fa->getQRCodeUrl($this->issuer, $user->email, $secret);

        $writer = new Writer(
            new ImageRenderer(new RendererStyle(220), new SvgImageBackEnd),
        );

        return new SetupPayload(
            instructions: 'Mở ứng dụng Google Authenticator hoặc Microsoft Authenticator, '
                .'quét mã QR bên dưới, rồi nhập 6 số mà ứng dụng hiển thị.',
            secret: $secret,
            qrCodeSvg: $writer->writeString($url),
        );
    }

    /** TOTP không cần phát gì — mã đã có sẵn trên điện thoại người dùng. */
    public function challenge(User $user): void
    {
        //
    }

    public function verify(User $user, string $code): bool
    {
        $secret = $user->two_factor_secret;

        if ($secret === null) {
            return false;
        }

        // window: 1 — chấp nhận mã của chu kỳ liền trước và liền sau (±30 giây).
        // Đồng hồ điện thoại lệch vài giây là chuyện thường; không nới thì
        // nhân viên sẽ liên tục báo "nhập đúng mà vẫn sai".
        //
        // verifyKey trả về false hoặc vị trí chu kỳ khớp (số nguyên, có thể là
        // 0 — nên phải so sánh !== false chứ không ép kiểu bool trực tiếp).
        return $this->google2fa->verifyKey($secret, $code, window: 1) !== false;
    }

    public function reset(User $user): void
    {
        $user->forceFill(['two_factor_secret' => null])->save();
    }

    /**
     * TOTP bắt buộc phải có secret. Người từng bật OTP qua email có
     * `confirmed_at` nhưng secret rỗng — với kênh này họ CHƯA thiết lập, và
     * phải được đưa đi quét mã QR chứ không phải đưa vào màn nhập mã.
     */
    public function isEnrolled(User $user): bool
    {
        return $user->two_factor_confirmed_at !== null
            && $user->two_factor_secret !== null;
    }

    public function supportsResend(): bool
    {
        return false;
    }
}
