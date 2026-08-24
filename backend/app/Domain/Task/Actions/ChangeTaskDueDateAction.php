<?php

declare(strict_types=1);

namespace App\Domain\Task\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Task\Models\Task;
use App\Domain\Task\Models\TaskDueDateChange;
use App\Support\Time\IncomingDateTime;
use Illuminate\Support\Facades\DB;

/**
 * Dời hạn task, luôn kèm lý do và luôn để lại vết.
 *
 * Đây là ràng buộc nghiệp vụ quan trọng nhất của đợt 1. Toàn bộ đánh giá đúng
 * hạn ở đợt 5 dựa trên deadline — nếu ai cũng tự dời hạn khi sắp trễ thì mọi
 * chỉ số về sau đều vô nghĩa.
 *
 * Ghi lịch sử và tăng bộ đếm trong cùng một giao dịch: đếm mà không có vết,
 * hoặc có vết mà không đếm, đều là dữ liệu nói dối.
 */
final class ChangeTaskDueDateAction
{
    public function execute(
        Task $task,
        ?string $hanMoi,
        string $lyDo,
        User $actor,
    ): Task {
        $hanCu = $task->due_date;
        // Chuẩn hoá về UTC ngay tại biên — xem IncomingDateTime để biết vì sao
        // không giao việc này cho cast của Eloquent.
        $han = IncomingDateTime::toUtc($hanMoi);

        DB::transaction(function () use ($task, $hanCu, $han, $lyDo, $actor): void {
            TaskDueDateChange::query()->create([
                'task_id' => $task->id,
                'old_due_date' => $hanCu,
                'new_due_date' => $han,
                'reason' => $lyDo,
                'requested_by' => $actor->id,
                // Người có quyền đổi hạn tự duyệt luôn. Luồng "người làm đề
                // nghị, cấp trên duyệt" làm ở mục sau khi có thông báo.
                'approved_by' => $actor->id,
                'approved_at' => now(),
            ]);

            $task->forceFill([
                'due_date' => $han,
                'updated_by' => $actor->id,
                // Xoá dấu đã nhắc để hạn MỚI được nhắc lại. Không xoá thì dời
                // hạn ra xa rồi tới gần sẽ im lặng hoàn toàn — đúng lúc người
                // ta cần nhắc nhất.
                'due_soon_notified_at' => null,
                'overdue_notified_at' => null,
            ])->save();

            $task->increment('due_date_change_count');
        });

        return $task->refresh();
    }
}
