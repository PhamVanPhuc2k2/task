<?php

declare(strict_types=1);

namespace App\Domain\Task\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Task\Data\CreateTaskData;
use App\Domain\Task\Events\TaskAssigned;
use App\Domain\Task\Models\Task;
use App\Support\Time\IncomingDateTime;

final class CreateTaskAction
{
    public function execute(CreateTaskData $data, User $actor): Task
    {
        $task = new Task;

        $task->fill([
            'project_id' => $data->projectId,
            'parent_task_id' => $data->parentTaskId,
            'title' => $data->title,
            'description' => $data->description,
            'assignee_id' => $data->assigneeId,
            'reviewer_id' => $data->reviewerId,
            'status' => $data->status,
            'priority' => $data->priority,
            // Chuẩn hoá về UTC ngay tại đây. Cast `datetime` của Eloquent lưu
            // nguyên giờ địa phương của chuỗi vào cột — xem IncomingDateTime.
            'due_date' => IncomingDateTime::toUtc($data->dueDate),
            'estimate_hours' => $data->estimateHours,
        ]);

        // Người bấm nút luôn là người giao việc và người tạo. Không nhận từ
        // client — để client tự khai là mở đường cho việc đổ trách nhiệm.
        $task->assigner_id = $actor->id;
        $task->created_by = $actor->id;

        $task->save();

        // Người giao việc mặc định theo dõi task mình giao, để nhận thông báo
        // khi có bình luận hoặc task trễ hạn.
        if ($actor->id !== $data->assigneeId) {
            $task->watchers()->syncWithoutDetaching([$actor->id]);

            // Chỉ báo khi giao cho NGƯỜI KHÁC. Tự tạo việc cho mình mà nhận
            // thông báo "bạn được giao việc mới" là vô nghĩa.
            if ($data->assigneeId !== null) {
                TaskAssigned::dispatch($task, $actor->name, $actor->id);
            }
        }

        return $task;
    }
}
