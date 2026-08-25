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
     * Kênh email KHÔNG có gì để thiết lập, nên ai cũng coi như đã sẵn sàng.
     *
     * ── Vì sao đổi ───────────────────────────────────────────────────────────
     *
     * Bản trước trả về `two_factor_confirmed_at !== null`, nên nhân viên mới bị
     * đẩy qua một màn "Bảo vệ tài khoản" trước khi vào được. Màn đó làm đúng
     * MỘT việc: gửi mã sáu số tới email — y hệt việc mà bước nhập mã cũng làm.
     * Xong rồi còn bắt lưu một danh sách mã khôi phục.
     *
     * Với kênh TOTP thì màn đó có nghĩa: phải quét mã QR để điện thoại và máy
     * chủ cùng biết một bí mật. Với email thì **không có bí mật nào để trao
     * đổi** — địa chỉ đã nằm sẵn trên tài khoản, và việc mã tới được hộp thư
     * chính là bằng chứng địa chỉ đúng.
     *
     * Nên nó là nghi thức thuần tuý: thêm hai màn hình vào ngày đầu đi làm của
     * mọi nhân viên, đổi lại không có thêm một lớp bảo vệ nào.
     *
     * ## Điều này KHÔNG làm yếu bảo mật
     *
     * Mọi tài khoản vẫn phải nhập mã sáu số gửi tới email ở mỗi lần đăng nhập.
     * Thứ duy nhất mất đi là mã khôi phục — mà với hệ thống dùng chính email
     * làm kênh OTP, mã khôi phục gần như không cứu được ai: mất quyền vào hộp
     * thư thì cũng mất luôn đường "quên mật khẩu". Đường phục hồi thật là quản
     * trị viên đặt lại (`/users/{user}/reset-two-factor`), và nó vẫn nguyên.
     *
     * Người đã có mã khôi phục từ trước vẫn dùng được — xem
     * TwoFactorService::consumeRecoveryCode.
     */
    public function isEnrolled(User $user): bool
    {
        return true;
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
