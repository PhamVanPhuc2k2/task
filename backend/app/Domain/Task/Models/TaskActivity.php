<?php

declare(strict_types=1);

namespace App\Domain\Task\Models;

use App\Domain\Identity\Models\User;
use Carbon\CarbonImmutable;
use Database\Factories\Task\TaskActivityFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Nhật ký thay đổi của một task: ai đổi gì, lúc nào, từ giá trị nào sang
 * giá trị nào.
 *
 * Chỉ ghi thêm. Không sửa, không xoá, không soft delete — bản ghi kiểm toán mà
 * sửa được thì không còn là kiểm toán.
 *
 * @property int $id
 * @property int $task_id
 * @property int|null $causer_id
 * @property string $event
 * @property array<string, mixed>|null $old_values
 * @property array<string, mixed>|null $new_values
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['task_id', 'causer_id', 'event', 'old_values', 'new_values'])]
final class TaskActivity extends Model
{
    /** @use HasFactory<TaskActivityFactory> */
    use HasFactory;

    /** @return BelongsTo<Task, $this> */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    /** @return BelongsTo<User, $this> */
    public function causer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'causer_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
        ];
    }
}
