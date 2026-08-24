<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Domain\Identity\Actions\AuthenticateUserAction;
use App\Domain\Identity\Data\LoginCredentialsData;
use App\Domain\Identity\Services\TwoFactorService;
use App\Http\Concerns\ManagesPendingLogin;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Throwable;

/**
 * Bước một của đăng nhập: kiểm email và mật khẩu.
 *
 * Đúng mật khẩu KHÔNG có nghĩa là đã đăng nhập. Hệ thống bắt buộc xác thực hai
 * lớp với mọi tài khoản, nên bước này chỉ ghi tạm người dùng vào session rồi
 * trả về việc cần làm tiếp:
 *
 *   - `two_factor_required`       → đã bật OTP, sang bước nhập mã
 *   - `two_factor_setup_required` → chưa bật, buộc thiết lập trước khi vào được
 *
 * Không phát token, không tạo phiên đăng nhập ở bước này.
 */
final class LoginController
{
    use ManagesPendingLogin;

    /** Số lần sai liên tiếp trước khi khoá tạm, và thời gian khoá (giây). */
    private const int MAX_ATTEMPTS = 5;

    private const int LOCKOUT_SECONDS = 300;

    public function __invoke(
        LoginRequest $request,
        AuthenticateUserAction $action,
        TwoFactorService $twoFactor,
    ): JsonResponse {
        $credentials = new LoginCredentialsData(
            email: (string) $request->string('email'),
            password: (string) $request->string('password'),
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
        );

        if (RateLimiter::tooManyAttempts($request->throttleKey(), self::MAX_ATTEMPTS)) {
            $action->recordThrottled($credentials);

            throw new TooManyRequestsHttpException(
                RateLimiter::availableIn($request->throttleKey()),
            );
        }

        try {
            $user = $action->execute($credentials);
        } catch (Throwable $e) {
            RateLimiter::hit($request->throttleKey(), self::LOCKOUT_SECONDS);

            throw $e;
        }

        RateLimiter::clear($request->throttleKey());

        $this->rememberPendingLogin($request, $user);

        /*
        | Hỏi KÊNH ĐANG DÙNG, không hỏi cột `two_factor_confirmed_at`.
        |
        | Cột đó chỉ nói "người này đã xác nhận một lần nào đó", không nói xác
        | nhận bằng kênh nào. Đổi `TWO_FACTOR_DRIVER` từ email sang totp trên
        | hệ thống đang chạy thì mọi người vẫn có `confirmed_at` — nhưng không
        | ai có `two_factor_secret`, nên nếu tin cột đó, cả công ty bị đẩy sang
        | màn nhập mã TOTP mà không có mã nào để nhập. Kể cả quản trị viên.
        |
        | Hỏi provider thì người dùng kênh cũ được đưa đi thiết lập lại — quét
        | mã QR một lần rồi vào bình thường. Xem TwoFactorProvider::isEnrolled.
        */
        if (! $twoFactor->isEnrolled($user)) {
            // Chưa bật: buộc thiết lập. Mã sẽ gửi khi họ gọi /two-factor/setup,
            // không gửi ở đây — tránh gửi thừa nếu họ bỏ dở ngay.
            return new JsonResponse([
                'data' => ['two_factor_setup_required' => true],
            ]);
        }

        // Đã bật: phát thử thách ngay. Với kênh email nghĩa là gửi mã đi luôn,
        // để người dùng mở hộp thư trong lúc màn nhập mã đang hiện ra.
        $twoFactor->challenge($user);

        return new JsonResponse([
            'data' => [
                'two_factor_required' => true,
                'channel' => config('two-factor.driver'),
                'can_resend' => $twoFactor->supportsResend(),
                'sent_to' => $twoFactor->supportsResend()
                    ? $this->maskEmail($user->email)
                    : null,
            ],
        ]);
    }
}
