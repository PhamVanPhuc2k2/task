<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Tasks;

use App\Domain\Identity\Models\User;
use App\Domain\Task\Actions\ChangeTaskDueDateAction;
use App\Domain\Task\Models\Task;
use App\Http\Requests\Task\ChangeDueDateRequest;
use App\Http\Resources\TaskResource;
use Illuminate\Routing\Attributes\Controllers\Authorize;

/**
 * Dời hạn task — luôn kèm lý do, luôn để lại vết.
 *
 * Chỉ người có quyền `task.due_date.change` được gọi (trưởng phòng trở lên).
 * Người làm chỉ được đề nghị; nếu ai cũng tự dời hạn khi sắp trễ thì mọi chỉ
 * số đúng hạn ở đợt 5 đều vô nghĩa.
 */
final class ChangeTaskDueDateController
{
    #[Authorize('changeDueDate', 'task')]
    public function __invoke(
        ChangeDueDateRequest $request,
        Task $task,
        ChangeTaskDueDateAction $action,
    ): TaskResource {
        /** @var User $actor */
        $actor = $request->user();

        $action->execute(
            task: $task,
            hanMoi: $request->filled('due_date') ? (string) $request->string('due_date') : null,
            lyDo: (string) $request->string('reason'),
            actor: $actor,
        );

        return TaskResource::make($task->load(['project', 'assignee', 'assigner']));
    }
}
