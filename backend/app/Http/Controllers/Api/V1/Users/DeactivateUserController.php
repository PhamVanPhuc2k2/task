<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Users;

use App\Domain\Identity\Actions\DeactivateUserAction;
use App\Domain\Identity\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Attributes\Controllers\Authorize;

/** Vô hiệu hoá tài khoản nhân viên nghỉ việc — không xoá bản ghi. */
final class DeactivateUserController
{
    #[Authorize('deactivate', 'user')]
    public function __invoke(Request $request, User $user, DeactivateUserAction $action): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        $action->execute($user, $actor);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
