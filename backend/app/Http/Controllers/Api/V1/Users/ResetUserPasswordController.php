<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Users;

use App\Domain\Identity\Actions\ResetUserPasswordAction;
use App\Domain\Identity\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Authorize;

/** Quản trị viên đặt lại mật khẩu hộ, nhận về mật khẩu tạm hiện một lần. */
final class ResetUserPasswordController
{
    #[Authorize('resetPassword', 'user')]
    public function __invoke(Request $request, User $user, ResetUserPasswordAction $action): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        return new JsonResponse([
            'data' => [
                // Hiện đúng một lần. Không lưu lại, không gửi email.
                'temporary_password' => $action->execute($user, $actor),
            ],
        ]);
    }
}
