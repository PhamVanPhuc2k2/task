<?php

declare(strict_types=1);

namespace App\Domain\Identity\Services;

use App\Domain\Identity\Contracts\SetupPayload;
use App\Domain\Identity\Contracts\TwoFactorProvider;
use App\Domain\Identity\Mail\TwoFactorCodeMail;
use App\Domain\Identity\Models\TwoFactorCode;
use App\Domain\Identity\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

/**
 * Xác thực hai lớp bằng mã 6 số gửi qua email.
 *
 * Đánh đổi so với TOTP, ghi rõ để sau này không ai tưởng đây là lựa chọn
 * mặc nhiên:
 *
 *   - Yếu hơn: ai chiếm được hộp thư là chiếm được tài khoản. Nếu email công
 *     ty cũng chỉ có mật khẩu thì lớp thứ hai không thật sự là lớp thứ hai.
 *   - Phụ thuộc mạng và nhà cung cấp mail. Thư vào spam hoặc về chậm là nhân
 *     viên không đăng nhập được.
 *   - Bù lại: không phải cài thêm ứng dụng, quen thuộc với người dùng Việt.
 *
 * Đổi sang TOTP bằng biến `TWO_FACTOR_DRIVER=totp`.
 */
final readonly class EmailOtpProvider implements TwoFactorProvider
{
    public function __construct(
        private int $expiresInMinutes = 10,
    ) {}

    public function startSetup(User $user): SetupPayload
    {
        $this->challenge($user);

        return new SetupPayload(
            // "Đang gửi", không phải "đã gửi": thư nằm ở hàng đợi lúc câu này
            // trả về. Xem TwoFactorCodeMail.
            instructions: sprintf(
                'Chúng tôi đang gửi mã gồm 6 số tới %s. Thư thường tới sau vài giây, mở hộp thư và nhập mã để hoàn tất.',
                $user->email,
            ),
            sentTo: $user->email,
        );
    }

    public function challenge(User $user): void
    {
        // Vô hiệu hoá mọi mã cũ chưa dùng. Không làm thì mọi mã từng gửi đều
        // còn sống tới lúc hết hạn — càng bấm "gửi lại" càng nhiều mã hợp lệ.
        TwoFactorCode::query()
            ->where('user_id', $user->id)
            ->usable()
            ->update(['invalidated_at' => now()]);

        $code = $this->generateCode();

        TwoFactorCode::query()->create([
            'user_id' => $user->id,
            'code_hash' => Hash::make($code),
            'sent_to' => $user->email,
            'expires_at' => now()->addMinutes($this->expiresInMinutes),
        ]);

        Mail::to($user->email)->send(new TwoFactorCodeMail(
            code: $code,
            userName: $user->name,
            expiresInMinutes: $this->expiresInMinutes,
        ));
    }

    public function verify(User $user, string $code): bool
    {
        $candidates = TwoFactorCode::query()
            ->where('user_id', $user->id)
            ->usable()
            ->latest('id')
            ->get();

        foreach ($candidates as $candidate) {
            if (! Hash::check($code, (string) $candidate->code_hash)) {
                continue;
            }

            $candidate->forceFill(['used_at' => now()])->save();

            return true;
        }

        return false;
    }

    public function reset(User $user): void
    {
        TwoFactorCode::query()->where('user_id', $user->id)->delete();
    }

    /**
     * Kênh email không lưu gì trên người dùng — mã sinh mới mỗi lần đăng nhập
     * và tra trong bảng `two_factor_codes`. Nên "đã thiết lập" ở đây đúng bằng
     * "đã xác nhận một lần".
     */
    public function isEnrolled(User $user): bool
    {
        return $user->two_factor_confirmed_at !== null;
    }

    public function supportsResend(): bool
    {
        return true;
    }

    /**
     * Mã 6 số sinh bằng nguồn ngẫu nhiên an toàn mật mã.
     *
     * `random_int` chứ không phải `rand`: mã đoán được thì lớp thứ hai vô nghĩa.
     */
    private function generateCode(): string
    {
        return str_pad((string) random_int(0, 999_999), 6, '0', STR_PAD_LEFT);
    }
}
