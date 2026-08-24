<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Domain\Identity\Services\TwoFactorService;
use App\Http\Concerns\ManagesPendingLogin;
use App\Http\Requests\Auth\TwoFactorChallengeRequest;
use App\Http\Resources\UserResource;
use App\Support\Exceptions\InvalidTwoFactorCodeException;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

/**
 * Bước hai của đăng nhập: nhập mã OTP, hoặc mã khôi phục khi mất điện thoại.
 */
final class TwoFactorChallengeController
{
    use ManagesPendingLogin;

    private const int MAX_ATTEMPTS = 5;

    private const int LOCKOUT_SECONDS = 300;

    public function __invoke(TwoFactorChallengeRequest $request, TwoFactorService $twoFactor): UserResource
    {
        $user = $this->pendingUser($request);

        // Giới hạn riêng cho bước này. Không dùng chung khoá với bước mật khẩu:
        // người nhập đúng mật khẩu rồi vẫn phải bị chặn nếu dò mã OTP.
        $key = 'two-factor:'.$user->id.'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            throw new TooManyRequestsHttpException(RateLimiter::availableIn($key));
        }

        $recoveryCode = $request->string('recovery_code')->toString();

        $passed = $recoveryCode !== ''
            ? $twoFactor->consumeRecoveryCode($user, $recoveryCode)
            : $twoFactor->verifyCode($user, (string) $request->string('code'));

        if (! $passed) {
            RateLimiter::hit($key, self::LOCKOUT_SECONDS);

            throw new InvalidTwoFactorCodeException;
        }

        RateLimiter::clear($key);

        $this->completeLogin($request, $user);

        return UserResource::make(
            $user->load(['department', 'position', 'roles.permissions', 'permissions']),
        );
    }
}
