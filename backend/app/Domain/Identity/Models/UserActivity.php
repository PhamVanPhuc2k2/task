<?php

declare(strict_types=1);

namespace App\Domain\Identity\Models;

use App\Domain\Identity\Enums\UserActivityEvent;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Một dòng nhật ký nhân sự: ai đổi gì của ai, lúc nào, từ giá trị nào sang
 * giá trị nào.
 *
 * Chỉ ghi thêm. Không sửa, không xoá, không soft delete — bản ghi kiểm toán mà
 * sửa được thì không còn là kiểm toán.
 *
 * `@property` là bắt buộc theo quy ước của dự án: thiếu thì Larastan suy kiểu
 * từ migration và hiểu sai mọi cột có cast (xem README mục 1.4).
 *
 * @property int $id
 * @property int $user_id
 * @property int|null $causer_id
 * @property UserActivityEvent $event
 * @property array<string, mixed>|null $old_values
 * @property array<string, mixed>|null $new_values
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['user_id', 'causer_id', 'event', 'old_values', 'new_values'])]
final class UserActivity extends Model
{
    /** Người bị thay đổi. */
    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Người gây ra thay đổi. Null khi thay đổi đến từ lệnh dòng lệnh. */
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
            'event' => UserActivityEvent::class,
            'old_values' => 'array',
            'new_values' => 'array',
        ];
    }
}
