<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Domain\Identity\Services\TwoFactorService;
use App\Http\Concerns\ManagesPendingLogin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Bắt đầu thiết lập xác thực hai lớp.
 *
 * Kênh email: gửi mã đầu tiên tới hộp thư.
 * Kênh TOTP: trả mã QR để quét.
 *
 * Gọi được ở trạng thái "đã qua bước mật khẩu nhưng chưa vào" — vì nhân viên
 * mới buộc phải thiết lập trước khi dùng được app.
 */
final class TwoFactorSetupController
{
    use ManagesPendingLogin;

    public function __invoke(Request $request, TwoFactorService $twoFactor): JsonResponse
    {
        $user = $this->pendingUser($request);

        $payload = $twoFactor->startSetup($user);

        return new JsonResponse([
            'data' => [
                'channel' => config('two-factor.driver'),
                'instructions' => $payload->instructions,
                'secret' => $payload->secret,
                'qr_code_svg' => $payload->qrCodeSvg,
                'sent_to' => $payload->sentTo,
                'can_resend' => $twoFactor->supportsResend(),
            ],
        ]);
    }
}
