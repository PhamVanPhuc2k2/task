<?php

declare(strict_types=1);

namespace App\Domain\Attendance\Models;

use App\Domain\Attendance\Enums\AdjustmentStatus;
use App\Domain\Identity\Models\User;
use App\Support\Concerns\HasUuid;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Một đơn giải trình công, cho đúng một ngày.
 *
 * Nhân viên tự khai vì sao ngày đó hệ thống đo thiếu. Duyệt thì ghi một dòng
 * `work_days`; từ chối thì không ghi gì, nhưng đơn vẫn nằm lại làm vết.
 *
 * `work_date` cast là **string** chứ không phải `date` — cùng quy ước với
 * `WorkDay`, `LateArrivalRequest` và `DailyReport`. Đây là nhãn ngày công theo
 * lịch Việt Nam, không phải một mốc trên trục thời gian; cast sang Carbon sẽ
 * gắn 00:00 theo múi giờ ứng dụng (UTC) và mở lại đúng cái bẫy mà quy ước này
 * sinh ra để chặn.
 *
 * @property int $id
 * @property string $uuid
 * @property int $user_id
 * @property string $work_date
 * @property string $reason
 * @property int|null $requested_minutes
 * @property AdjustmentStatus $status
 * @property int|null $approved_minutes
 * @property int|null $reviewed_by
 * @property CarbonImmutable|null $reviewed_at
 * @property string|null $review_note
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable([
    'user_id', 'work_date', 'reason', 'requested_minutes',
    'status', 'approved_minutes', 'reviewed_by', 'reviewed_at', 'review_note',
])]
final class AttendanceAdjustment extends Model
{
    use HasUuid;

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * Đơn đang chặn chỗ — chờ duyệt hoặc đã duyệt.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeBlocking(Builder $query): void
    {
        $query->whereIn('status', [
            AdjustmentStatus::Pending->value,
            AdjustmentStatus::Approved->value,
        ]);
    }

    /**
     * Đơn còn treo trong một khoảng ngày.
     *
     * Dùng để chặn chốt sổ: chốt một kỳ còn đơn chờ duyệt là vứt đơn của người
     * ta đi mà không nói gì — họ đã làm đúng phần việc của mình rồi.
     *
     * @param  Builder<$this>  $query
     */
    public function scopePendingBetween(Builder $query, string $tu, string $den): void
    {
        $query->where('status', AdjustmentStatus::Pending->value)
            ->whereBetween('work_date', [$tu, $den]);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => AdjustmentStatus::class,
            // Cố ý là string — xem chú thích đầu lớp.
            'work_date' => 'string',
            'requested_minutes' => 'integer',
            'approved_minutes' => 'integer',
            'reviewed_at' => 'datetime',
        ];
    }
}
