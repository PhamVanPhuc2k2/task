<?php

declare(strict_types=1);

namespace App\Domain\Task\Listeners;

use App\Domain\Identity\Models\User;
use App\Domain\Task\Events\TaskAssigned;
use App\Domain\Task\Notifications\TaskAssignedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;

/**
 * Báo cho người vừa được giao việc.
 *
 * Chạy ở hàng đợi: gửi email trong request đồng bộ nghĩa là nút "Giao việc"
 * treo cho tới khi máy chủ SMTP trả lời, và SMTP chậm thì người dùng bấm lại.
 */
final class SendTaskAssignedNotification implements ShouldQueue
{
    public function handle(TaskAssigned $event): void
    {
        $nguoiLam = $event->task->assignee_id;

        // Tự nhận việc của mình thì không cần ai báo.
        if ($nguoiLam === null || $nguoiLam === $event->nguoiGiaoId) {
            return;
        }

        $nguoiNhan = User::query()
            ->with('notificationSettings')
            ->where('is_active', true)
            ->find($nguoiLam);

        if (! $nguoiNhan instanceof User) {
            return;
        }

        Notification::send(
            $nguoiNhan,
            new TaskAssignedNotification($event->task, $event->nguoiGiao),
        );
    }
}
