<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Models\User;
use App\Domain\Task\Models\Task;

/**
 * Quyền thao tác trên từng task.
 *
 * Quyền (`Permission`) trả lời "được làm loại việc này không". Policy trả lời
 * "được đụng vào đúng task này không". Xem README, bảng pattern.
 */
final class TaskPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->canAny([
            Permission::ViewOwnTasks->value,
            Permission::ViewTeamTasks->value,
            Permission::ViewAllTasks->value,
        ]);
    }

    /** Xem chi tiết: phải nằm trong phạm vi, không chỉ có quyền chung chung. */
    public function view(User $actor, Task $task): bool
    {
        return $task->isVisibleTo($actor);
    }

    public function create(User $actor): bool
    {
        return $actor->can(Permission::CreateTask->value);
    }

    public function update(User $actor, Task $task): bool
    {
        return $actor->can(Permission::UpdateTask->value)
            && $task->isVisibleTo($actor);
    }

    public function delete(User $actor, Task $task): bool
    {
        return $actor->can(Permission::DeleteTask->value)
            && $task->isVisibleTo($actor);
    }

    /** Giao việc cho người khác. */
    public function assign(User $actor, Task $task): bool
    {
        return $actor->can(Permission::AssignTask->value)
            && $task->isVisibleTo($actor);
    }

    /**
     * Đổi hạn.
     *
     * Chỉ người giao việc hoặc cấp trên được đổi; người làm chỉ được đề nghị.
     * Nếu ai cũng tự dời hạn khi sắp trễ thì mọi chỉ số đúng hạn ở đợt 5 đều
     * vô nghĩa — xem README mục 1.3.
     */
    public function changeDueDate(User $actor, Task $task): bool
    {
        return $actor->can(Permission::ChangeTaskDueDate->value)
            && $task->isVisibleTo($actor);
    }

    /** Đổi trạng thái: người làm cũng được, đó là công việc hằng ngày của họ. */
    public function changeStatus(User $actor, Task $task): bool
    {
        if (! $task->isVisibleTo($actor)) {
            return false;
        }

        return $actor->can(Permission::UpdateTask->value)
            || $task->assignee_id === $actor->id;
    }
}
