<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Domain\Identity\Models\User;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;

/** Thông tin người dùng đang đăng nhập, kèm vai trò và quyền. */
final class MeController
{
    public function __invoke(Request $request): UserResource
    {
        /** @var User $user */
        $user = $request->user();

        return UserResource::make(
            $user->load(['department', 'position', 'roles.permissions', 'permissions']),
        );
    }
}
