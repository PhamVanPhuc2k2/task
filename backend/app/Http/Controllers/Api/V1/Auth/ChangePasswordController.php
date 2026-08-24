<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Domain\Identity\Models\User;
use App\Http\Requests\Auth\ChangePasswordRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/** Người dùng tự đổi mật khẩu của mình. */
final class ChangePasswordController
{
    public function __invoke(ChangePasswordRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $user->forceFill([
            'password' => Hash::make((string) $request->string('password')),
            // Đổi remember_token để mọi thiết bị "ghi nhớ đăng nhập" cũ bị đá ra.
            'remember_token' => Str::random(60),
        ])->save();

        $user->tokens()->delete();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
