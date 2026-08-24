<?php

declare(strict_types=1);

namespace App\Domain\Payroll\Models;

use App\Domain\Identity\Models\User;
use App\Support\Concerns\HasUuid;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Phần thưởng của một người trong một quỹ.
 *
 * `amount` **không bao giờ âm** — ràng buộc `CHECK (amount >= 0)` ở database,
 * không chỉ ở code. Đây là điều khiến hệ thống này không thể biến thành công cụ
 * phạt tiền, thứ mà Điều 127 Bộ luật Lao động 2019 nghiêm cấm. Xem migration.
 *
 * @property int $id
 * @property string $uuid
 * @property int $pool_id
 * @property int $user_id
 * @property string $amount
 * @property string $reason
 * @property int|null $decided_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['pool_id', 'user_id', 'amount', 'reason', 'decided_by'])]
final class BonusAllocation extends Model
{
    use HasUuid;

    /** @return BelongsTo<BonusPool, $this> */
    public function pool(): BelongsTo
    {
        return $this->belongsTo(BonusPool::class, 'pool_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<User, $this> */
    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }
}
