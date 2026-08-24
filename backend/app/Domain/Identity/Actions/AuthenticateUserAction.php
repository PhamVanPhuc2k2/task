<?php

declare(strict_types=1);

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Data\LoginCredentialsData;
use App\Domain\Identity\Models\LoginAttempt;
use App\Domain\Identity\Models\User;
use App\Support\Exceptions\AccountDisabledException;
use App\Support\Exceptions\InvalidCredentialsException;
use Illuminate\Support\Facades\Hash;

/**
 * Kiểm tra thông tin đăng nhập và ghi nhật ký.
 *
 * Chỉ *xác thực* — việc tạo phiên (`Auth::login`) do tầng Http làm, vì tầng
 * Domain không được biết tới session. Action trả về User, controller đăng nhập.
 */
final class AuthenticateUserAction
{
    public function execute(LoginCredentialsData $credentials): User
    {
        $user = User::query()->where('email', $credentials->email)->first();

        // Vẫn băm một lần khi không tìm thấy user, để thời gian phản hồi của
        // "email không tồn tại" và "sai mật khẩu" gần như nhau. Chênh lệch thời
        // gian là một cách liệt kê tài khoản có thật.
        $passwordMatches = $user instanceof User
            ? Hash::check($credentials->password, (string) $user->password)
            : Hash::check($credentials->password, '$2y$12$'.str_repeat('0', 53));

        if (! $user instanceof User || ! $passwordMatches) {
            $this->record($credentials, $user, successful: false, reason: 'invalid_credentials');

            throw new InvalidCredentialsException;
        }

        if ($user->is_active !== true) {
            $this->record($credentials, $user, successful: false, reason: 'account_disabled');

            throw new AccountDisabledException;
        }

        $this->record($credentials, $user, successful: true);

        return $user;
    }

    /** Ghi lại một lần thử bị chặn vì quá số lần cho phép. */
    public function recordThrottled(LoginCredentialsData $credentials): void
    {
        $this->record($credentials, null, successful: false, reason: 'too_many_attempts');
    }

    private function record(
        LoginCredentialsData $credentials,
        ?User $user,
        bool $successful,
        ?string $reason = null,
    ): void {
        LoginAttempt::query()->create([
            'user_id' => $user?->id,
            'email' => $credentials->email,
            'successful' => $successful,
            'failure_reason' => $reason,
            'ip_address' => $credentials->ipAddress,
            'user_agent' => $credentials->userAgent,
        ]);
    }
}
