<?php

declare(strict_types=1);

namespace App\Domain\Task\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Task\Enums\TaskStatus;
use App\Domain\Task\Models\Task;
use App\Support\Exceptions\InvalidStatusTransitionException;

/**
 * Đổi trạng thái task, kèm các mốc thời gian đi theo.
 *
 * Mốc thời gian đặt ở đây chứ không để người dùng gửi lên: `started_at` và
 * `completed_at` về sau dùng để tính thời gian thực làm và tỉ lệ đúng hạn —
 * để client tự khai là mở đường cho số liệu sai.
 */
final class ChangeTaskStatusAction
{
    public function execute(Task $task, TaskStatus $moi, User $actor): Task
    {
        $hienTai = $task->status;

        if ($hienTai === $moi) {
            return $task;
        }

        if (! $hienTai->canTransitionTo($moi)) {
            throw new InvalidStatusTransitionException($hienTai->label(), $moi->label());
        }

        $thayDoi = ['status' => $moi, 'updated_by' => $actor->id];

        // Lần đầu bắt tay vào làm — không ghi đè nếu task từng bị tạm dừng rồi
        // quay lại, vì mốc "bắt đầu" là lần đầu tiên.
        if ($moi === TaskStatus::InProgress && $task->started_at === null) {
            $thayDoi['started_at'] = now();
        }

        if ($moi === TaskStatus::Done) {
            $thayDoi['completed_at'] = now();
            $thayDoi['progress_percent'] = 100;
        }

        $task->forceFill($thayDoi)->save();

        return $task;
    }
}
