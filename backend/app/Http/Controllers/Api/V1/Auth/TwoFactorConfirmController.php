<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Domain\Identity\Services\TwoFactorService;
use App\Http\Concerns\ManagesPendingLogin;
use App\Http\Requests\Auth\TwoFactorChallengeRequest;
use App\Support\Exceptions\InvalidTwoFactorCodeException;
use Illuminate\Http\JsonResponse;

/**
 * Xác nhận thiết lập lần đầu, rồi đăng nhập luôn.
 *
 * Nhập sai thì KHÔNG bật 2FA — người dùng quét QR hỏng mà hệ thống đã bật thì
 * họ bị khoá ngoài vĩnh viễn.
 */
final class TwoFactorConfirmController
{
    use ManagesPendingLogin;

    public function __invoke(TwoFactorChallengeRequest $request, TwoFactorService $twoFactor): JsonResponse
    {
        $user = $this->pendingUser($request);

        $recoveryCodes = $twoFactor->confirm($user, (string) $request->string('code'));

        if ($recoveryCodes === null) {
            throw new InvalidTwoFactorCodeException;
        }

        $this->completeLogin($request, $user);

        return new JsonResponse([
            'data' => [
                // Hiện đúng một lần. Người dùng phải lưu lại ngay.
                'recovery_codes' => $recoveryCodes,
            ],
        ]);
    }
}
