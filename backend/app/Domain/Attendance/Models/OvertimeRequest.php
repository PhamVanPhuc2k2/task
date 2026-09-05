<?php

declare(strict_types=1);

namespace App\Domain\Attendance\Models;

use App\Domain\Attendance\Enums\RequestStatus;
use App\Domain\Identity\Models\User;
use App\Support\Concerns\HasUuid;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Một đơn đăng ký làm thêm giờ, cho một khoảng giờ trong một ngày.
 *
 * Duyệt TRƯỚC mới được tính — xem migration để biết vì sao không suy từ giờ
 * ngồi trước máy.
 *
 * `work_date` cast là **string**, `start_time` và `end_time` cũng vậy — cùng
 * quy ước với `LateArrivalRequest`. "20h tới 22h ngày 15/09" là những con số
 * người ta nói với nhau theo giờ Việt Nam, không phải mốc trên trục thời gian;
 * cast sang Carbon sẽ gắn múi giờ ứng dụng (UTC) và mở lại đúng cái bẫy mà quy
 * ước này sinh ra để chặn.
 *
 * @property int $id
 * @property string $uuid
 * @property int $user_id
 * @property string $work_date
 * @property string $start_time
 * @property string $end_time
 * @property int $minutes
 * @property string $reason
 * @property RequestStatus $status
 * @property int|null $rate_percent
 * @property int|null $approved_minutes
 * @property int|null $reviewed_by
 * @property CarbonImmutable|null $reviewed_at
 * @property string|null $review_note
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable([
    'user_id', 'work_date', 'start_time', 'end_time', 'minutes', 'reason',
    'status', 'rate_percent', 'approved_minutes', 'reviewed_by', 'reviewed_at', 'review_note',
])]
final class OvertimeRequest extends Model
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

    /** Mốc giờ dạng `HH:MM` để hiển thị — MySQL trả về `HH:MM:SS`. */
    public function startLabel(): string
    {
        return substr($this->start_time, 0, 5);
    }

    public function endLabel(): string
    {
        return substr($this->end_time, 0, 5);
    }

    /**
     * Số phút thật sự được tính — số người duyệt chốt, hoặc số đã đăng ký.
     *
     * Đơn chưa duyệt thì trả số đã đăng ký để màn hình cộng được một con số
     * "nếu duyệt hết thì bao nhiêu". Chỗ nào cần đúng phần ĐÃ CAM KẾT TRẢ thì
     * lọc theo trạng thái trước, đừng dựa vào hàm này.
     */
    public function effectiveMinutes(): int
    {
        return $this->approved_minutes ?? $this->minutes;
    }

    /**
     * Đơn đang chặn chỗ — chờ duyệt hoặc đã duyệt.
     *
     * Dùng cho cả phép kiểm chồng lấn lẫn ba cái trần của Điều 107. Đơn bị từ
     * chối hoặc đã rút thì trả lại chỗ.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeBlocking(Builder $query): void
    {
        $query->whereIn('status', [
            RequestStatus::Pending->value,
            RequestStatus::Approved->value,
        ]);
    }

    /**
     * Đơn còn treo trong một khoảng ngày.
     *
     * Dùng để chặn chốt sổ: chốt một kỳ còn đơn làm thêm chờ duyệt là vứt đi
     * một khoản tiền người ta đã làm ra.
     *
     * @param  Builder<$this>  $query
     */
    public function scopePendingBetween(Builder $query, string $tu, string $den): void
    {
        $query->where('status', RequestStatus::Pending->value)
            ->whereBetween('work_date', [$tu, $den]);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => RequestStatus::class,
            // Cố ý là string — xem chú thích đầu lớp.
            'work_date' => 'string',
            'start_time' => 'string',
            'end_time' => 'string',
            'minutes' => 'integer',
            'rate_percent' => 'integer',
            'approved_minutes' => 'integer',
            'reviewed_at' => 'datetime',
        ];
    }
}
