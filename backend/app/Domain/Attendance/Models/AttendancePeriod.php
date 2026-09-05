<?php

declare(strict_types=1);

namespace App\Domain\Attendance\Models;

use App\Domain\Attendance\Enums\PeriodStatus;
use App\Domain\Identity\Models\User;
use App\Support\Concerns\HasUuid;
use App\Support\Time\WorkDate;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Date;

/**
 * Một kỳ công đã từng bị chốt sổ.
 *
 * Bảng thưa: kỳ chưa ai động tới thì không có dòng và mặc định là mở. Xem
 * `PeriodStatus` để biết vì sao không sinh sẵn dòng cho mọi tháng.
 *
 * `period` là chuỗi `YYYY-MM`, cùng quy ước với `work_date` và `report_date` —
 * đây là một nhãn kỳ, không phải mốc trên trục thời gian.
 *
 * @property int $id
 * @property string $uuid
 * @property string $period
 * @property PeriodStatus $status
 * @property CarbonImmutable $closed_at
 * @property int|null $closed_by
 * @property CarbonImmutable|null $reopened_at
 * @property int|null $reopened_by
 * @property string|null $reopen_reason
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable([
    'period', 'status', 'closed_at', 'closed_by',
    'reopened_at', 'reopened_by', 'reopen_reason',
])]
final class AttendancePeriod extends Model
{
    use HasUuid;

    /**
     * Kỳ công chứa một ngày.
     *
     * Nhận `YYYY-MM-DD` và cắt lấy `YYYY-MM`. Cắt chuỗi chứ không dựng `Date`:
     * ngày công vốn đã là ngày theo giờ Việt Nam, và đi qua một đối tượng thời
     * gian là mở đường cho lệch múi giờ ở đúng chỗ không cần tới nó.
     */
    public static function periodOf(string $workDate): string
    {
        return substr($workDate, 0, 7);
    }

    /**
     * Kỳ này đã kết thúc chưa — tính theo **ngày công hôm nay giờ Việt Nam**.
     *
     * Không dùng `now()` ở UTC: từ 00:00 tới 07:00 giờ Việt Nam mỗi ngày,
     * `now()` của Laravel vẫn đang ở hôm trước, nên chốt lúc 1h sáng ngày 01/10
     * sẽ bị từ chối nhầm.
     *
     * Ở trên model chứ không nằm riêng trong `ClosePeriodAction` vì hai chỗ cần
     * cùng câu trả lời: hành động chốt, và tầng Http khi quyết định hiện lỗi
     * nào trước — "kỳ chưa kết thúc" phải thắng "kỳ còn đơn treo", vì kỳ chưa
     * kết thúc thì đơn treo là chuyện đương nhiên.
     */
    public static function daKetThuc(string $ky): bool
    {
        $cuoiKy = CarbonImmutable::parse($ky.'-01', WorkDate::timezone())
            ->endOfMonth()
            ->toDateString();

        return $cuoiKy < WorkDate::from(Date::now());
    }

    /** @return BelongsTo<User, $this> */
    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    /** @return BelongsTo<User, $this> */
    public function reopenedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reopened_by');
    }

    public function isLocked(): bool
    {
        return $this->status->isLocked();
    }

    /**
     * Chỉ những kỳ đang bị khoá.
     *
     * Có scope riêng để không chỗ nào phải nhớ rằng `Open` cũng là một dòng
     * trong bảng này — quên điều đó thì kỳ đã mở khoá lại bị coi là đang khoá,
     * và không ai sửa được số liệu mà giám đốc vừa cố ý mở ra.
     *
     * @param  Builder<self>  $query
     */
    public function scopeLocked(Builder $query): void
    {
        $query->where('status', PeriodStatus::Closed->value);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PeriodStatus::class,
            // Cố ý là string — xem chú thích đầu lớp.
            'period' => 'string',
            'closed_at' => 'datetime',
            'reopened_at' => 'datetime',
        ];
    }
}
