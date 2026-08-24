<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Tasks;

use App\Domain\Identity\Models\User;
use App\Domain\Task\Actions\ChangeTaskStatusAction;
use App\Domain\Task\Enums\TaskStatus;
use App\Domain\Task\Models\Task;
use App\Http\Requests\Task\ChangeStatusRequest;
use App\Http\Resources\TaskResource;
use Illuminate\Routing\Attributes\Controllers\Authorize;

/**
 * Đổi trạng thái task.
 *
 * Người làm tự đổi được — đó là công việc hằng ngày của họ, không cần xin phép.
 * Luồng hợp lệ do TaskStatus quyết định, không cho nhảy tuỳ tiện.
 */
final class ChangeTaskStatusController
{
    #[Authorize('changeStatus', 'task')]
    public function __invoke(
        ChangeStatusRequest $request,
        Task $task,
        ChangeTaskStatusAction $action,
    ): TaskResource {
        /** @var User $actor */
        $actor = $request->user();

        $action->execute($task, TaskStatus::from((string) $request->string('status')), $actor);

        return TaskResource::make($task->load(['project', 'assignee', 'assigner']));
    }
}
