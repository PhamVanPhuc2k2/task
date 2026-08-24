<?php

declare(strict_types=1);

namespace App\Domain\Task\Observers;

use App\Domain\Task\Models\Task;
use App\Domain\Task\Models\TaskActivity;
use BackedEnum;
use DateTimeInterface;

/**
 * Tự ghi nhật ký mọi thay đổi của task.
 *
 * Dùng Observer chứ không gọi tay trong từng Action: nhật ký phải sinh ra bất
 * kể ai sửa và sửa từ đâu. Gọi tay là sớm muộn cũng có chỗ quên, và mất dấu
 * vết đúng ở chỗ đó — thứ chỉ phát hiện khi cần tra lại thì đã muộn.
 *
 * `causer_id` lấy từ cột `updated_by`/`created_by` mà Action đã đặt, chứ không
 * gọi Auth ở đây: Observer thuộc tầng Domain, không được biết tới phiên đăng
 * nhập. Xem README, "Quy tắc phụ thuộc".
 */
final class TaskActivityObserver
{
    /** Không ghi vào nhật ký những cột tự đổi hoặc không mang ý nghĩa nghiệp vụ. */
    private const array BO_QUA = ['updated_at', 'created_at', 'updated_by', 'uuid'];

    public function created(Task $task): void
    {
        TaskActivity::query()->create([
            'task_id' => $task->id,
            'causer_id' => $task->created_by,
            'event' => 'created',
            'old_values' => null,
            'new_values' => [
                'title' => $task->title,
                'status' => $task->status->value,
                'priority' => $task->priority->value,
            ],
        ]);
    }

    public function updated(Task $task): void
    {
        $cot = collect($task->getChanges())->except(self::BO_QUA)->keys();

        if ($cot->isEmpty()) {
            return;
        }

        // Đọc qua getAttribute/getOriginal($key) chứ không lấy thẳng giá trị
        // trong getChanges(): mảng thuộc tính giữ ngày dưới dạng chuỗi thô
        // 'Y-m-d H:i:s' không kèm múi giờ, và nhánh DateTimeInterface bên dưới
        // sẽ không bao giờ chạy. Chuỗi đó tới trình duyệt bị đọc thành giờ máy
        // — lệch bảy tiếng với người dùng ở Việt Nam. Hai hàm này áp cast nên
        // trả về Carbon, và nhật ký luôn ghi ISO 8601 kèm offset.
        $moi = $cot
            ->mapWithKeys(fn (string $ten): array => [
                $ten => $this->phang($task->getAttribute($ten)),
            ])
            ->all();

        $cu = $cot
            ->mapWithKeys(fn (string $ten): array => [
                $ten => $this->phang($task->getOriginal($ten)),
            ])
            ->all();

        TaskActivity::query()->create([
            'task_id' => $task->id,
            'causer_id' => $task->updated_by,
            'event' => 'updated',
            'old_values' => $cu,
            'new_values' => $moi,
        ]);
    }

    public function deleted(Task $task): void
    {
        // Xoá cứng thì dòng task đã biến mất thật, khoá ngoại của
        // task_activities không còn chỗ bám — ghi tiếp là vi phạm ràng buộc và
        // ném 500. Xoá cứng vốn cũng đã kéo theo toàn bộ nhật ký cũ, nên không
        // có gì để giữ lại.
        if ($task->isForceDeleting()) {
            return;
        }

        TaskActivity::query()->create([
            'task_id' => $task->id,
            'causer_id' => $task->updated_by,
            'event' => 'deleted',
            'old_values' => ['title' => $task->title],
            'new_values' => null,
        ]);
    }

    /** Đưa enum và ngày giờ về dạng lưu được trong JSON. */
    private function phang(mixed $value): mixed
    {
        return match (true) {
            $value instanceof BackedEnum => $value->value,
            $value instanceof DateTimeInterface => $value->format(DATE_ATOM),
            default => $value,
        };
    }
}
