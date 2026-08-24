<?php

declare(strict_types=1);

namespace App\Domain\Payroll\Models;

use App\Domain\Identity\Models\User;
use App\Domain\Payroll\Enums\BonusPoolStatus;
use App\Support\Concerns\HasUuid;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Quỹ thưởng của một dự án.
 *
 * **Không có quan hệ tới model `Project`**, dù cột `project_id` là khoá ngoại
 * thật ở database. deptrac chỉ cho `Payroll → Identity, Support`; tầng Http là
 * nơi duy nhất biết cả hai miền và nó tự ghép tên dự án vào — cùng cách đã dùng
 * cho bảng lương ghép với `User`.
 *
 * @property int $id
 * @property string $uuid
 * @property int $project_id
 * @property string $total_amount
 * @property string $currency
 * @property BonusPoolStatus $status
 * @property string $condition_note
 * @property int|null $created_by
 * @property CarbonImmutable|null $locked_at
 * @property CarbonImmutable|null $distributed_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable([
    'project_id', 'total_amount', 'currency', 'status',
    'condition_note', 'created_by', 'locked_at', 'distributed_at',
])]
final class BonusPool extends Model
{
    use HasUuid;

    protected $table = 'project_bonus_pools';

    /** @return HasMany<BonusAllocation, $this> */
    public function allocations(): HasMany
    {
        return $this->hasMany(BonusAllocation::class, 'pool_id');
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Tổng đã chia.
     *
     * `bcadd` chứ không `sum()`: `sum()` của Collection cộng bằng toán tử số
     * học và ép mọi thứ sang float. Với mười dòng tiền thì sai số bắt đầu hiện
     * ra ở hàng xu, và đây là con số dùng để kiểm "có vượt quỹ không".
     */
    public function allocatedTotal(): string
    {
        $tong = '0.00';

        foreach ($this->allocations as $phan) {
            /** @var numeric-string $so */
            $so = $phan->amount;
            /** @var numeric-string $tong */
            $tong = bcadd($tong, $so, 2);
        }

        return $tong;
    }

    /** Phần quỹ chưa chia. Âm là không thể — Action chặn trước khi tới đây. */
    public function remaining(): string
    {
        /** @var numeric-string $tong */
        $tong = $this->total_amount;

        /** @var numeric-string $daChia */
        $daChia = $this->allocatedTotal();

        return bcsub($tong, $daChia, 2);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => BonusPoolStatus::class,
            'total_amount' => 'decimal:2',
            'locked_at' => 'datetime',
            'distributed_at' => 'datetime',
        ];
    }
}
