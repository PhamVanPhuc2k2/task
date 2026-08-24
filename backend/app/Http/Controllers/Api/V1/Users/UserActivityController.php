<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Users;

use App\Domain\Identity\Models\User;
use App\Domain\Identity\Models\UserActivity;
use App\Http\Resources\UserActivityResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Attributes\Controllers\Authorize;

/**
 * Nhật ký thay đổi hồ sơ của một nhân viên.
 *
 * Quyền `viewAny` trên User, tức là chỉ người quản trị nhân sự đọc được —
 * **không** dùng `view` như trang chi tiết task. Khác biệt có chủ ý:
 * `UserPolicy::view` cho phép ai cũng xem hồ sơ của chính mình, mà nhật ký thì
 * chứa cả những lần bị đổi vai trò hay bị vô hiệu hoá kèm tên người ra quyết
 * định. Đó là thông tin quản trị, không phải hồ sơ cá nhân.
 *
 * Mới nhất lên đầu — câu hỏi thường gặp là "vừa có ai đổi gì", không phải
 * "tài khoản này được tạo ra thế nào".
 */
final class UserActivityController
{
    #[Authorize('viewAny', User::class)]
    public function index(Request $request, User $user): AnonymousResourceCollection
    {
        $activities = UserActivity::query()
            ->where('user_id', $user->id)
            ->with('causer')
            ->latest('id')
            ->paginate(perPage: min((int) $request->integer('per_page', 30), 100));

        return UserActivityResource::collection($activities);
    }
}
