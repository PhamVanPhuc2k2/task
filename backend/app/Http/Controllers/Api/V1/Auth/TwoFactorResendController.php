<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Domain\Identity\Services\TwoFactorService;
use App\Http\Concerns\ManagesPendingLogin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

/**
 * Gửi lại mã xác thực.
 *
 * Giới hạn chặt: không chặn thì nút "gửi lại" thành công cụ spam hộp thư người
 * khác — chỉ cần biết mật khẩu của họ là bấm được liên tục.
 */
final class TwoFactorResendController
{
    use ManagesPendingLogin;

    public function __invoke(Request $request, TwoFactorService $twoFactor): JsonResponse
    {
        // Kiểm phiên chờ TRƯỚC khi đếm lần gửi: người chưa qua bước mật khẩu
        // không được phép làm bẩn bộ đếm của người dùng thật.
        $user = $this->pendingUser($request);

        $key = 'two-factor-resend:'.$user->id.'|'.$request->ip();
        $max = (int) config('two-factor.resend_attempts');
        $decay = (int) config('two-factor.decay_seconds');

        if (RateLimiter::tooManyAttempts($key, $max)) {
            throw new TooManyRequestsHttpException(RateLimiter::availableIn($key));
        }

        RateLimiter::hit($key, $decay);

        $twoFactor->challenge($user);

        return new JsonResponse([
            'data' => [
                'sent_to' => $this->maskEmail($user->email),
                'can_resend' => $twoFactor->supportsResend(),
            ],
        ]);
    }
}
