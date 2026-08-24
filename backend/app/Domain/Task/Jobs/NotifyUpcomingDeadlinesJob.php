<?php

declare(strict_types=1);

namespace App\Domain\Task\Jobs;

use App\Domain\Identity\Models\User;
use App\Domain\Task\Enums\TaskStatus;
use App\Domain\Task\Models\Task;
use App\Domain\Task\Notifications\TaskDueSoonNotification;
use App\Domain\Task\Notifications\TaskOverdueNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Notification;

/**
 * Quét task sắp tới hạn và task đã quá hạn, báo cho người thực hiện.
 *
 * **Mỗi task chỉ được nhắc một lần cho mỗi mốc.** Job chạy mỗi giờ trong giờ
 * hành chính; không đánh dấu đã nhắc thì cùng một task sẽ báo chín lần một
 * ngày, và người dùng tắt thông báo ngay hôm đầu tiên. Dấu vết nằm ở hai cột
 * `due_soon_notified_at` và `overdue_notified_at`, và bị xoá khi task được dời
 * hạn để hạn mới được nhắc lại.
 *
 * Chạy theo lô 200 task một lần: một công ty vài trăm người có thể có hàng
 * nghìn task quá hạn, nạp hết vào bộ nhớ là job chết vì hết RAM chứ không phải
 * vì logic sai.
 */
final class NotifyUpcomingDeadlinesJob implements ShouldQueue
{
    use Queueable;

    /** Nhắc trước bao nhiêu giờ. */
    private const int TRUOC_GIO = 24;

    private const int LO = 200;

    public function handle(): void
    {
        $this->quet(
            fn (Builder $q) => $q
                ->whereNull('due_soon_notified_at')
                ->whereBetween('due_date', [now(), now()->addHours(self::TRUOC_GIO)]),
            'due_soon_notified_at',
            fn (Task $task) => new TaskDueSoonNotification($task),
        );

        $this->quet(
            fn (Builder $q) => $q
                ->whereNull('overdue_notified_at')
                ->where('due_date', '<', now()),
            'overdue_notified_at',
            fn (Task $task) => new TaskOverdueNotification($task),
        );
    }

    /**
     * @param  callable(Builder<Task>): Builder<Task>  $dieuKien
     * @param  callable(Task): mixed  $taoThongBao
     */
    private function quet(callable $dieuKien, string $cotDanhDau, callable $taoThongBao): void
    {
        Task::query()
            ->tap($dieuKien)
            // Task đã xong hoặc đã huỷ thì hạn không còn ý nghĩa.
            ->whereNotIn('status', [TaskStatus::Done->value, TaskStatus::Cancelled->value])
            ->whereNotNull('due_date')
            ->whereNotNull('assignee_id')
            ->with('assignee.notificationSettings')
            ->chunkById(self::LO, function (Collection $tasks) use ($cotDanhDau, $taoThongBao): void {
                foreach ($tasks as $task) {
                    $nguoiLam = $task->assignee;

                    if ($nguoiLam instanceof User && $nguoiLam->is_active) {
                        Notification::send($nguoiLam, $taoThongBao($task));
                    }

                    // Đánh dấu kể cả khi không gửi được cho ai (người làm đã
                    // nghỉ việc): nếu không, lượt quét sau lại nhặt đúng task
                    // đó lên và lặp mãi.
                    $task->forceFill([$cotDanhDau => now()])->saveQuietly();
                }
            });
    }
}
