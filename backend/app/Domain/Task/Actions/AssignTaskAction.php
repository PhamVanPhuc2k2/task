<?php

declare(strict_types=1);

namespace App\Domain\Task\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Task\Events\TaskAssigned;
use App\Domain\Task\Models\Task;

/**
 * Giao lại task cho người khác.
 *
 * Tách khỏi controller ở mục 1.6: việc "người bấm nút trở thành người giao việc
 * mới" là luật nghiệp vụ, không phải chuyện điều phối HTTP — xem README, "Http
 * không chứa nghiệp vụ".
 */
final class AssignTaskAction
{
    public function execute(Task $task, ?int $nguoiLamMoi, User $actor): Task
    {
        $nguoiLamCu = $task->assignee_id;

        $task->forceFill([
            'assignee_id' => $nguoiLamMoi,
            // Người bấm nút trở thành người giao việc mới.
            'assigner_id' => $actor->id,
            'updated_by' => $actor->id,
        ])->save();

        // Chỉ báo khi người làm thực sự đổi. Bấm lưu mà giữ nguyên người cũ thì
        // gửi thông báo là làm phiền vô cớ, và người nhận sẽ học cách bỏ qua.
        if ($nguoiLamMoi !== null && $nguoiLamMoi !== $nguoiLamCu) {
            TaskAssigned::dispatch($task, $actor->name, $actor->id);
        }

        return $task;
    }
}
