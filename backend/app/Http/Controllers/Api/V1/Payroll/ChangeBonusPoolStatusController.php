<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Payroll;

use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Models\User;
use App\Domain\Payroll\Actions\ChangeBonusPoolStatusAction;
use App\Domain\Payroll\Enums\BonusPoolStatus;
use App\Domain\Payroll\Models\BonusPool;
use App\Domain\Task\Models\Project;
use App\Http\Resources\BonusPoolResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Chốt quỹ thưởng, rồi đánh dấu đã chi. Một chiều.
 *
 * Chốt là mốc quan trọng nhất của cả tính năng: từ giây đó nhân viên xem được
 * phần của mình, và không ai sửa được nữa — kể cả để tăng. Sửa con số sau khi
 * đã báo cho nhân viên là thứ phá niềm tin nhanh nhất.
 *
 * Chỉ nhận `locked` và `distributed`; không có đường quay về `draft`.
 */
final class ChangeBonusPoolStatusController
{
    public function __invoke(
        Request $request,
        Project $project,
        ChangeBonusPoolStatusAction $action,
    ): JsonResponse {
        /** @var User $actor */
        $actor = $request->user();

        abort_unless($actor->can(Permission::ManageBonus->value), Response::HTTP_FORBIDDEN);

        $validated = $request->validate([
            'status' => ['required', 'string', 'in:locked,distributed'],
        ]);

        $quy = BonusPool::query()->where('project_id', $project->id)->firstOrFail();

        // Tên dự án truyền từ đây xuống: Action thuộc miền Payroll và không
        // được tham chiếu tới `Project` của miền Task.
        $daDoi = $action->execute(
            $quy,
            BonusPoolStatus::from((string) $validated['status']),
            $project->name,
        );

        return BonusPoolResource::make($daDoi->load('allocations.user'))->response();
    }
}
