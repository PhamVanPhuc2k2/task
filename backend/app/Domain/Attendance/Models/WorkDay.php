<?php

declare(strict_types=1);

namespace App\Domain\Attendance\Models;

use App\Domain\Attendance\Enums\AttendanceDecision;
use App\Domain\Identity\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Quyết định của con người trên một ngày công.
 *
 * Bảng thưa — chỉ tồn tại khi có người can thiệp. Ngày bình thường không sinh
 * dòng nào và số giờ suy thẳng từ `work_sessions`.
 *
 * @property int $id
 * @property int $user_id
 * @property string $work_date
 * @property AttendanceDecision $decision
 * @property int|null $adjusted_minutes
 * @property string $reason
 * @property int|null $reviewed_by
 * @property CarbonImmutable $reviewed_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable([
    'user_id', 'work_date', 'decision', 'adjusted_minutes',
    'reason', 'reviewed_by', 'reviewed_at',
])]
final class WorkDay extends Model
{
    /** Người bị quyết định. */
    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Người ra quyết định. */
    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'decision' => AttendanceDecision::class,
            'adjusted_minutes' => 'integer',
            'reviewed_at' => 'datetime',
            // Cùng lý do với WorkSession: nhãn ngày công, không phải mốc giờ.
            'work_date' => 'string',
        ];
    }
}
