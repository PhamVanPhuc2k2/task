<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Tasks;

use App\Domain\Identity\Models\User;
use App\Domain\Task\Actions\AssignTaskAction;
use App\Domain\Task\Models\Task;
use App\Http\Concerns\ResolvesUuids;
use App\Http\Resources\TaskResource;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\Validation\Rule;

/** Giao lại task cho người khác. */
final class AssignTaskController
{
    use ResolvesUuids;

    #[Authorize('assign', 'task')]
    public function __invoke(Request $request, Task $task, AssignTaskAction $action): TaskResource
    {
        $request->validate([
            'assignee_id' => ['nullable', 'uuid', Rule::exists('users', 'uuid')],
        ]);

        /** @var User $actor */
        $actor = $request->user();

        $task = $action->execute(
            $task,
            $this->resolveId(User::class, $request->input('assignee_id')),
            $actor,
        );

        return TaskResource::make($task->load(['project', 'assignee', 'assigner']));
    }
}
