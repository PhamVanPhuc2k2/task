<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Projects;

use App\Domain\Identity\Models\User;
use App\Domain\Task\Enums\ProjectRole;
use App\Domain\Task\Models\Project;
use App\Http\Concerns\ResolvesUuids;
use App\Http\Requests\Project\StoreProjectMemberRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Routing\Attributes\Controllers\Authorize;

/**
 * Thành viên dự án.
 *
 * Vai trò ở đây (`ProjectRole`) khác vai trò hệ thống: một trưởng phòng có thể
 * chỉ là người xem ở dự án của phòng khác.
 *
 * Xem danh sách chỉ cần thấy được dự án; thêm và gỡ thì cần quyền quản lý —
 * thành viên tự kéo người quen vào dự án là mất kiểm soát phạm vi truy cập.
 */
final class ProjectMemberController
{
    use ResolvesUuids;

    #[Authorize('view', 'project')]
    public function index(Project $project): JsonResponse
    {
        $vaiTro = $project->memberRoles();

        $members = $project->members()
            ->orderBy('name')
            ->get()
            ->map(fn (User $user): array => [
                'id' => $user->uuid,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $vaiTro[$user->id] ?? ProjectRole::Member->value,
            ])
            ->all();

        return new JsonResponse(['data' => $members]);
    }

    #[Authorize('manageMembers', 'project')]
    public function store(StoreProjectMemberRequest $request, Project $project): JsonResponse
    {
        $userId = $this->resolveId(User::class, $request->input('user_id'));
        $role = ProjectRole::tryFrom((string) $request->string('role')) ?? ProjectRole::Member;

        // syncWithoutDetaching: thêm lại người đã có thì chỉ đổi vai trò, không
        // nhân đôi dòng — bảng có unique(project_id, user_id) nên attach thẳng
        // sẽ ném lỗi 500 thay vì làm đúng ý người dùng.
        $project->members()->syncWithoutDetaching([
            $userId => ['role' => $role->value],
        ]);

        return new JsonResponse(['data' => [
            'user_id' => $request->string('user_id'),
            'role' => $role->value,
        ]]);
    }

    #[Authorize('manageMembers', 'project')]
    public function destroy(Project $project, User $user): JsonResponse
    {
        $project->members()->detach($user->id);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
