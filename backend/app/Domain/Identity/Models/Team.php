<?php

declare(strict_types=1);

namespace App\Domain\Identity\Models;

use App\Support\Concerns\HasUuid;
use Carbon\CarbonImmutable;
use Database\Factories\Identity\TeamFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Đội nhóm cắt ngang cơ cấu phòng ban.
 *
 * Phân quyền xem task đi theo cây `departments`, không theo đội nhóm. Đội nhóm
 * chỉ để tổ chức con người, không cấp quyền.
 *
 * `@property` là bắt buộc theo quy ước dự án — xem README, "Khối @property
 * trên model". Không có nó thì Larastan phải suy kiểu từ migration, và khi
 * bộ quét migration hỏng thì mọi chỗ đọc thuộc tính đều báo lỗi.
 *
 * @property int $id
 * @property string $uuid
 * @property string $name
 * @property string|null $code
 * @property string|null $description
 * @property int|null $leader_id
 * @property bool $is_active
 * @property CarbonImmutable|null $deleted_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['name', 'code', 'description', 'leader_id', 'is_active'])]
final class Team extends Model
{
    /** @use HasFactory<TeamFactory> */
    use HasFactory;

    use HasUuid;
    use SoftDeletes;

    /** @return BelongsTo<User, $this> */
    public function leader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'leader_id');
    }

    /** @return BelongsToMany<User, $this> */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
