<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Tasks;

use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Models\User;
use App\Domain\Task\Models\Task;
use App\Http\Resources\TaskResource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * "Việc của đội" — task của mọi người thuộc phòng mình và các phòng trực thuộc.
 *
 * Dành cho quản lý. Nhân viên thường không có quyền `task.view.team` nên bị
 * chặn ngay từ đây.
 */
final class TeamTasksController
{
    public function __invoke(Request $request): AnonymousResourceCollection
    {
        /** @var User $actor */
        $actor = $request->user();

        abort_unless(
            $actor->canAny([Permission::ViewTeamTasks->value, Permission::ViewAllTasks->value]),
            Response::HTTP_FORBIDDEN,
        );

        $phamVi = $actor->department?->subtreeIds() ?? [];

        $tasks = Task::query()
            ->visibleTo($actor)
            ->when(
                ! $actor->can(Permission::ViewAllTasks->value),
                fn (Builder $q) => $q->whereHas(
                    'assignee',
                    fn (Builder $u) => $u->whereIn('department_id', $phamVi),
                ),
            )
            ->with(['project', 'assignee', 'assigner', 'labels'])
            ->orderByRaw('due_date IS NULL, due_date')
            ->paginate(perPage: min((int) $request->integer('per_page', 25), 100))
            ->withQueryString();

        return TaskResource::collection($tasks);
    }
}
