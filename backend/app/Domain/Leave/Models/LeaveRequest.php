<?php

declare(strict_types=1);

namespace App\Domain\Leave\Models;

use App\Domain\Identity\Models\User;
use App\Domain\Leave\Enums\LeaveStatus;
use App\Domain\Leave\Enums\LeaveType;
use App\Support\Concerns\HasUuid;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Một đơn xin nghỉ, cho một khoảng ngày liên tục.
 *
 * `start_date` và `end_date` cast là **string** chứ không phải `date` — cùng
 * quy ước với `work_date` và `report_date`. Cast sang Carbon sẽ gắn thêm 00:00
 * theo múi giờ ứng dụng (UTC) và mở lại đúng cái bẫy mà cột kiểu DATE sinh ra
 * để chặn: ngày nghỉ là ngày trên lịch Việt Nam, không phải một mốc thời gian.
 *
 * `@property` là bắt buộc theo quy ước dự án: thiếu thì Larastan suy kiểu từ
 * migration và hiểu sai mọi cột có cast.
 *
 * @property int $id
 * @property string $uuid
 * @property int $user_id
 * @property LeaveType $type
 * @property string $start_date
 * @property string $end_date
 * @property string $reason
 * @property LeaveStatus $status
 * @property int|null $reviewed_by
 * @property CarbonImmutable|null $reviewed_at
 * @property string|null $review_note
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable([
    'user_id', 'type', 'start_date', 'end_date', 'reason',
    'status', 'reviewed_by', 'reviewed_at', 'review_note',
])]
final class LeaveRequest extends Model
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
     * Số ngày trên lịch mà đơn này phủ.
     *
     * Đếm ngày lịch, KHÔNG trừ cuối tuần và ngày lễ — có chủ ý ở phạm vi này.
     * Trừ được thì phải biết ngày nghỉ hằng tuần của từng người và tra bảng
     * `holidays`, mà cả hai thứ đó chỉ có nghĩa khi đã có quỹ phép để trừ vào.
     * Con số ở đây dùng để hiển thị ("nghỉ 3 ngày"), không dùng để tính lương.
     */
    public function dayCount(): int
    {
        return (int) CarbonImmutable::parse($this->start_date)
            ->diffInDays(CarbonImmutable::parse($this->end_date)) + 1;
    }

    /** Đơn này có phủ ngày đó không. */
    public function covers(string $ngay): bool
    {
        return $ngay >= $this->start_date && $ngay <= $this->end_date;
    }

    /**
     * Đơn đang có hiệu lực chặn chỗ — đã duyệt hoặc đang chờ duyệt.
     *
     * Dùng để chặn nộp đơn chồng lấn. Đơn bị từ chối hoặc đã rút thì không
     * chặn gì: người ta bị từ chối rồi nộp lại với lý do rõ hơn là chuyện
     * bình thường.
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
     * Đơn đã duyệt phủ lên khoảng ngày này.
     *
     * Đây là truy vấn nóng nhất của bảng: bảng công tháng gọi nó một lần cho
     * cả phòng, mỗi lần mở trang. Hai khoảng giao nhau khi và chỉ khi
     * `start <= den` VÀ `end >= tu` — viết đúng một lần ở đây thay vì để mỗi
     * chỗ dùng tự nghĩ lại, vì đảo nhầm một dấu là lọc ra tập rỗng và không có
     * gì báo.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeApprovedBetween(Builder $query, string $tu, string $den): void
    {
        $query->where('status', LeaveStatus::Approved->value)
            ->where('start_date', '<=', $den)
            ->where('end_date', '>=', $tu);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => LeaveType::class,
            'status' => LeaveStatus::class,
            // Cố ý là string — xem chú thích đầu lớp.
            'start_date' => 'string',
            'end_date' => 'string',
            'reviewed_at' => 'datetime',
        ];
    }
}
