<?php

declare(strict_types=1);

namespace App\Domain\Report\Models;

use App\Domain\Identity\Models\User;
use App\Domain\Report\Enums\DailyReportStatus;
use App\Support\Concerns\HasUuid;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Báo cáo tiến độ của một người trong một ngày.
 *
 * **Không có quan hệ tới model `Task`** dù bảng con giữ khoá ngoại `task_id`:
 * deptrac chỉ cho `Report → Identity, Support`. Tầng Http ghép tên task vào —
 * cùng cách đã dùng cho quỹ thưởng ghép với dự án.
 *
 * `@property` là bắt buộc theo quy ước dự án: thiếu thì Larastan suy kiểu từ
 * migration và hiểu sai mọi cột có cast (xem README mục 1.4).
 *
 * @property int $id
 * @property string $uuid
 * @property int $user_id
 * @property string $report_date
 * @property string $content
 * @property DailyReportStatus $status
 * @property CarbonImmutable|null $submitted_at
 * @property int|null $reviewed_by
 * @property CarbonImmutable|null $reviewed_at
 * @property string|null $review_note
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable([
    'user_id', 'report_date', 'content', 'status',
    'submitted_at', 'reviewed_by', 'reviewed_at', 'review_note',
])]
final class DailyReport extends Model
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

    /** @return HasMany<DailyReportTask, $this> */
    public function tasks(): HasMany
    {
        return $this->hasMany(DailyReportTask::class);
    }

    /**
     * Báo cáo đã nộp — bản nháp không tính.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeSubmitted(Builder $query): void
    {
        $query->whereIn('status', [
            DailyReportStatus::Submitted->value,
            DailyReportStatus::Reviewed->value,
        ]);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => DailyReportStatus::class,
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
            // Nhãn ngày, không phải mốc giờ — cùng lý do với
            // `work_sessions.work_date`. Cast sang `date` sẽ gắn thêm 00:00
            // theo múi giờ ứng dụng và mở lại đúng cái bẫy cột này chặn.
            'report_date' => 'string',
        ];
    }
}
