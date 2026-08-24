<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Users;

use App\Domain\Identity\Actions\ActivateUserAction;
use App\Domain\Identity\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Attributes\Controllers\Authorize;

/**
 * Mở lại tài khoản đã vô hiệu hoá.
 *
 * Dùng chung quyền `deactivate` với đường ngược lại: ai cho nghỉ được thì nhận
 * lại được. Tách thành hai quyền riêng chỉ tạo ra tình huống có người khoá
 * được mà không mở được.
 *
 * Người đã vô hiệu hoá **không** nằm trong danh sách nhân sự mặc định, nên
 * giao diện phải bật `include_inactive=1` mới thấy để bấm nút này.
 */
final class ActivateUserController
{
    #[Authorize('deactivate', 'user')]
    public function __invoke(Request $request, User $user, ActivateUserAction $action): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        $action->execute($user, $actor);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
