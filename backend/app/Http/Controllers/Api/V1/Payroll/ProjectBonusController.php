<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Payroll;

use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Models\User;
use App\Domain\Payroll\Actions\SaveBonusPoolAction;
use App\Domain\Payroll\Models\BonusPool;
use App\Domain\Task\Models\Project;
use App\Http\Requests\Payroll\SaveBonusPoolRequest;
use App\Http\Resources\BonusPoolResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Quỹ thưởng của một dự án.
 *
 * Đây là **tầng duy nhất biết cả hai miền**: `Project` thuộc miền Task, quỹ
 * thưởng thuộc miền Payroll, và deptrac không cho hai miền gọi thẳng nhau. Nên
 * controller nhận `Project` từ route rồi chỉ truyền `project_id` xuống Action.
 *
 * **Không có endpoint nào ghi số âm.** Ba lớp chặn cho cùng một luật: validate,
 * Action, và ràng buộc `CHECK` của database. Lý do là pháp lý chứ không phải kỹ
 * thuật — Điều 127 Bộ luật Lao động 2019 cấm phạt tiền, và một khoản "trừ
 * thưởng" mang số âm chính là khoản phạt trừ vào thu nhập.
 */
final class ProjectBonusController
{
    public function show(Request $request, Project $project): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        abort_unless($actor->can(Permission::ViewAllBonus->value), Response::HTTP_FORBIDDEN);

        $quy = BonusPool::query()
            ->where('project_id', $project->id)
            ->with(['allocations.user'])
            ->first();

        return new JsonResponse([
            'data' => $quy instanceof BonusPool
                ? BonusPoolResource::make($quy)->resolve()
                : null,
            'meta' => [
                'project' => ['id' => $project->uuid, 'name' => $project->name],
                'can_manage' => $actor->can(Permission::ManageBonus->value),
            ],
        ]);
    }

    public function store(
        SaveBonusPoolRequest $request,
        Project $project,
        SaveBonusPoolAction $action,
    ): JsonResponse {
        /** @var User $actor */
        $actor = $request->user();

        abort_unless($actor->can(Permission::ManageBonus->value), Response::HTTP_FORBIDDEN);

        $quy = $action->execute(
            projectId: $project->id,
            totalAmount: (string) $request->string('total_amount'),
            conditionNote: (string) $request->string('condition_note'),
            actor: $actor,
        );

        return BonusPoolResource::make($quy->load('allocations.user'))->response();
    }
}
