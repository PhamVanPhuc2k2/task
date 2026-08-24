<?php

declare(strict_types=1);

namespace App\Domain\Task\Models;

use App\Support\Concerns\HasUuid;
use Carbon\CarbonImmutable;
use Database\Factories\Task\TaskLabelFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Nhãn dán lên task. `color` là mã hex 7 ký tự, ví dụ `#94a3b8`.
 *
 * `@property` là bắt buộc theo quy ước dự án — xem README, "Khối @property
 * trên model". Không có nó thì Larastan phải suy kiểu từ migration, và khi
 * bộ quét migration hỏng thì mọi chỗ đọc thuộc tính đều báo lỗi.
 *
 * @property int $id
 * @property string $uuid
 * @property string $name
 * @property string $color
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['name', 'color'])]
final class TaskLabel extends Model
{
    /** @use HasFactory<TaskLabelFactory> */
    use HasFactory;

    use HasUuid;

    /** @return BelongsToMany<Task, $this> */
    public function tasks(): BelongsToMany
    {
        return $this->belongsToMany(Task::class);
    }
}
