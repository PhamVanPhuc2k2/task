<?php

declare(strict_types=1);

namespace App\Domain\Task\Models;

use App\Domain\Identity\Models\User;
use Carbon\CarbonImmutable;
use Database\Factories\Task\TaskDueDateChangeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Một lần dời hạn của task.
 *
 * `reason` là NOT NULL ở tầng database. Đây là ràng buộc nghiệp vụ quan trọng
 * nhất của đợt 1: toàn bộ đánh giá đúng hạn ở đợt 5 dựa trên deadline, nên
 * deadline phải là thứ không dời được trong im lặng.
 *
 * Chỉ ghi thêm, không sửa, không xoá.
 *
 * `@property` là bắt buộc theo quy ước dự án — xem README, "Khối @property
 * trên model". Không có nó thì Larastan phải suy kiểu từ migration, và khi
 * bộ quét migration hỏng thì mọi chỗ đọc thuộc tính đều báo lỗi.
 *
 * @property int $id
 * @property int $task_id
 * @property CarbonImmutable|null $old_due_date
 * @property CarbonImmutable|null $new_due_date
 * @property string $reason
 * @property int|null $requested_by
 * @property int|null $approved_by
 * @property CarbonImmutable|null $approved_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable([
    'task_id', 'old_due_date', 'new_due_date', 'reason',
    'requested_by', 'approved_by', 'approved_at',
])]
final class TaskDueDateChange extends Model
{
    /** @use HasFactory<TaskDueDateChangeFactory> */
    use HasFactory;

    /** @return BelongsTo<Task, $this> */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    /** @return BelongsTo<User, $this> */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /** @return BelongsTo<User, $this> */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'old_due_date' => 'datetime',
            'new_due_date' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }
}
