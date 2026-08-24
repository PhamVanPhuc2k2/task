<?php

declare(strict_types=1);

namespace App\Domain\Identity\Models;

use App\Support\Concerns\HasUuid;
use Carbon\CarbonImmutable;
use Database\Factories\Identity\PositionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Chức vụ. `level` là cấp bậc — số càng lớn càng cao.
 *
 * `@property` là bắt buộc theo quy ước dự án — xem README, "Khối @property
 * trên model". Không có nó thì Larastan phải suy kiểu từ migration, và khi
 * bộ quét migration hỏng thì mọi chỗ đọc thuộc tính đều báo lỗi.
 *
 * @property int $id
 * @property string $uuid
 * @property string $name
 * @property string|null $code
 * @property int $level
 * @property bool $is_active
 * @property CarbonImmutable|null $deleted_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['name', 'code', 'level', 'is_active'])]
final class Position extends Model
{
    /** @use HasFactory<PositionFactory> */
    use HasFactory;

    use HasUuid;
    use SoftDeletes;

    /** @return HasMany<User, $this> */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'level' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
