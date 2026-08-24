<?php

declare(strict_types=1);

namespace App\Domain\Leave\Models;

use App\Domain\Identity\Models\User;
use App\Domain\Leave\Enums\LeaveStatus;
use App\Support\Concerns\HasUuid;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Một đơn xin đi làm muộn, cho đúng một ngày.
 *
 * Dùng chung `LeaveStatus` với đơn nghỉ, có chủ ý: hai loại đơn đi qua đúng một
 * vòng đời (chờ → duyệt / từ chối / rút), do cùng một người duyệt, với cùng một
 * quyền. Tách ra một enum riêng chỉ để có tên khác là nhân đôi chỗ phải sửa mỗi
 * khi vòng đời đổi.
 *
 * `date` cast là **string** chứ không phải `date` — cùng quy ước với
 * `work_date`, `report_date`, `start_date`. Cast sang Carbon sẽ gắn 00:00 theo
 * múi giờ ứng dụng (UTC) và mở lại đúng cái bẫy mà cột kiểu DATE sinh ra để
 * chặn.
 *
 * `expected_arrival` cũng là string: nó là giờ trên đồng hồ Việt Nam
 * (`HH:MM:SS`), không phải một mốc trên trục thời gian.
 *
 * @property int $id
 * @property string $uuid
 * @property int $user_id
 * @property string $date
 * @property string $expected_arrival
 * @property string $reason
 * @property LeaveStatus $status
 * @property int|null $reviewed_by
 * @property CarbonImmutable|null $reviewed_at
 * @property string|null $review_note
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable([
    'user_id', 'date', 'expected_arrival', 'reason',
    'status', 'reviewed_by', 'reviewed_at', 'review_note',
])]
final class LateArrivalRequest extends Model
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

    /** Giờ dự kiến đến, dạng `HH:MM` để hiển thị. */
    public function arrivalLabel(): string
    {
        return substr($this->expected_arrival, 0, 5);
    }

    /**
     * Đơn đang chặn chỗ — đã duyệt hoặc đang chờ duyệt.
     *
     * Đơn bị từ chối hoặc đã rút thì KHÔNG chặn: bị từ chối rồi nộp lại với lý
     * do rõ hơn là chuyện bình thường.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeBlocking(Builder $query): void
    {
        $query->whereIn('status', [
            LeaveStatus::Pending->value,
            LeaveStatus::Approved->value,
        ]);
    }

    /**
     * Đơn **đã duyệt** trong khoảng ngày.
     *
     * Viết một lần ở đây thay vì để mỗi chỗ dùng tự nghĩ lại — cùng lý do với
     * `LeaveRequest::scopeApprovedBetween`.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeApprovedBetween(Builder $query, string $tu, string $den): void
    {
        $query->where('status', LeaveStatus::Approved->value)
            ->whereBetween('date', [$tu, $den]);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => LeaveStatus::class,
            // Cố ý là string — xem chú thích đầu lớp.
            'date' => 'string',
            'expected_arrival' => 'string',
            'reviewed_at' => 'datetime',
        ];
    }
}
