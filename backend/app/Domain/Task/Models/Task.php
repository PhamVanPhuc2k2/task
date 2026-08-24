<?php

declare(strict_types=1);

namespace App\Domain\Task\Models;

use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Models\User;
use App\Domain\Task\Enums\TaskPriority;
use App\Domain\Task\Enums\TaskStatus;
use App\Support\Concerns\HasUuid;
use App\Support\Time\WorkDate;
use Carbon\CarbonImmutable;
use Database\Factories\Task\TaskFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Date;

/**
 * Công việc — bảng lõi của hệ thống.
 *
 * Model chỉ khai báo quan hệ, cast và scope. Việc kiểm tra luồng chuyển trạng
 * thái, đổi hạn có lý do và ghi nhật ký đều nằm ở Action, làm ở mục 1.4.
 *
 * Khối `@property` bên dưới là bắt buộc, không phải trang trí: Larastan suy kiểu
 * thuộc tính từ migration nên thấy `status` là string chứ không thấy nó đã được
 * cast sang enum. Thiếu khối này thì phân tích tĩnh mức 8 báo sai hàng loạt và
 * người đọc mã cũng không biết `due_date` trả về Carbon hay chuỗi.
 *
 * @property int $id
 * @property string $uuid
 * @property int|null $project_id
 * @property int|null $parent_task_id
 * @property string $title
 * @property string|null $description
 * @property int|null $assignee_id
 * @property int|null $assigner_id
 * @property int|null $reviewer_id
 * @property TaskStatus $status
 * @property TaskPriority $priority
 * @property CarbonImmutable|null $due_date
 * @property CarbonImmutable|null $started_at
 * @property CarbonImmutable|null $completed_at
 * @property string|null $estimate_hours
 * @property int $progress_percent
 * @property int $due_date_change_count
 * @property CarbonImmutable|null $due_soon_notified_at
 * @property CarbonImmutable|null $overdue_notified_at
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property CarbonImmutable|null $deleted_at
 */
#[Fillable([
    'project_id', 'parent_task_id', 'title', 'description',
    'assignee_id', 'assigner_id', 'reviewer_id',
    'status', 'priority', 'due_date', 'started_at', 'completed_at',
    'estimate_hours', 'progress_percent', 'created_by', 'updated_by',
])]
final class Task extends Model
{
    /** @use HasFactory<TaskFactory> */
    use HasFactory;

    use HasUuid;
    use SoftDeletes;

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<self, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_task_id');
    }

    /** @return HasMany<self, $this> */
    public function subtasks(): HasMany
    {
        return $this->hasMany(self::class, 'parent_task_id');
    }

    /** @return BelongsTo<User, $this> */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    /** @return BelongsTo<User, $this> */
    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigner_id');
    }

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasMany<TaskComment, $this> */
    public function comments(): HasMany
    {
        return $this->hasMany(TaskComment::class);
    }

    /**
     * Người theo dõi task — nhận thông báo dù không phải người làm.
     *
     * @return BelongsToMany<User, $this>
     */
    public function watchers(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    /** @return BelongsToMany<TaskLabel, $this> */
    public function labels(): BelongsToMany
    {
        return $this->belongsToMany(TaskLabel::class);
    }

    /** @return HasMany<TaskActivity, $this> */
    public function activities(): HasMany
    {
        return $this->hasMany(TaskActivity::class);
    }

    /** @return HasMany<TaskDueDateChange, $this> */
    public function dueDateChanges(): HasMany
    {
        return $this->hasMany(TaskDueDateChange::class);
    }

    /**
     * Giới hạn danh sách theo phạm vi người dùng được phép xem.
     *
     * Đây là ràng buộc bảo mật quan trọng nhất của API Task. Lộ task của phòng
     * khác cho nhân viên thường là rò rỉ thông tin nội bộ — lương thưởng, khách
     * hàng, kế hoạch đều nằm trong mô tả công việc.
     *
     * Ba mức, theo quyền:
     *   task.view.all  — toàn công ty, không lọc gì
     *   task.view.team — thêm task của người thuộc phòng mình và mọi phòng
     *                    trực thuộc bên dưới, ở mọi độ sâu
     *   task.view.own  — task mình làm, mình giao, mình tạo, hoặc mình theo dõi
     *
     * Không có quyền nào thì không thấy gì, chứ không phải thấy tất cả.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if ($user->can(Permission::ViewAllTasks->value)) {
            return;
        }

        $query->where(function (Builder $scope) use ($user): void {
            if ($user->can(Permission::ViewOwnTasks->value)) {
                $scope->where('assignee_id', $user->id)
                    ->orWhere('assigner_id', $user->id)
                    ->orWhere('reviewer_id', $user->id)
                    ->orWhere('created_by', $user->id)
                    ->orWhereHas('watchers', fn (Builder $w) => $w->whereKey($user->id));
            }

            if ($user->can(Permission::ViewTeamTasks->value) && $user->department_id !== null) {
                $phamVi = $user->department?->subtreeIds() ?? [];

                $scope->orWhereHas(
                    'assignee',
                    fn (Builder $nguoiLam) => $nguoiLam->whereIn('department_id', $phamVi),
                );
            }

            // Không quyền nào khớp: chặn sạch thay vì để lọt.
            if (! $user->can(Permission::ViewOwnTasks->value)
                && ! $user->can(Permission::ViewTeamTasks->value)) {
                $scope->whereRaw('1 = 0');
            }
        });
    }

    /** Người dùng có được xem task cụ thể này không. */
    public function isVisibleTo(User $user): bool
    {
        return self::query()->whereKey($this->getKey())->visibleTo($user)->exists();
    }

    /**
     * Task đã qua hạn mà chưa kết thúc.
     *
     * Task đã hoàn thành hoặc đã huỷ thì dù quá hạn cũng không còn là việc cần
     * nhắc — loại chúng ra ngay ở tầng truy vấn.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeOverdue(Builder $query): void
    {
        $query->whereNotNull('due_date')
            ->where('due_date', '<', now())
            ->whereNotIn('status', [
                TaskStatus::Done->value,
                TaskStatus::Cancelled->value,
            ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Bốn scope dùng chung giữa trang Tổng quan và trang Công việc
    |--------------------------------------------------------------------------
    |
    | Trang Tổng quan hiện con số, bấm vào thì mở trang Công việc đã lọc sẵn.
    | Hai chỗ đó **bắt buộc phải dùng chung một định nghĩa** — nếu mỗi bên tự
    | viết truy vấn thì ô ghi "12 việc quá hạn" mà danh sách ra 9 dòng, và
    | không ai biết bên nào đúng.
    |
    | Đó là lý do bốn phép lọc này nằm ở model chứ không nằm trong controller.
    */

    /**
     * Việc chưa kết thúc.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeOpen(Builder $query): void
    {
        $query->whereNotIn('status', TaskStatus::closedValues());
    }

    /**
     * Việc chưa giao cho ai.
     *
     * Đây là nhóm dễ trôi nhất của cả hệ thống: không ai nhận thông báo nhắc
     * hạn, và nó không xuất hiện trong "việc của tôi" của bất kỳ ai.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeUnassigned(Builder $query): void
    {
        $query->open()->whereNull('assignee_id');
    }

    /**
     * Việc tới hạn trong ngày HÔM NAY THEO GIỜ VIỆT NAM.
     *
     * Bản trước tính bằng `now()->endOfDay()` — tức cuối ngày theo **UTC**, vì
     * ứng dụng chạy múi giờ UTC theo quy ước dữ liệu. Lệch bảy tiếng: lúc 8 giờ
     * sáng giờ Việt Nam, khoảng đó kéo tới 06:59 sáng HÔM SAU, nên việc tới hạn
     * rạng sáng mai bị đếm nhầm vào hôm nay. Con số vẫn ra một số hợp lý nên
     * không ai nhận thấy.
     *
     * `WorkDate` sinh ra đúng để chặn loại lỗi này — xem README, "Bẫy múi giờ".
     *
     * @param  Builder<$this>  $query
     */
    public function scopeDueToday(Builder $query): void
    {
        $homNay = WorkDate::from(Date::now());

        $query->open()
            ->whereNotNull('due_date')
            ->whereBetween('due_date', [
                WorkDate::startOfDayUtc($homNay),
                WorkDate::endOfDayUtc($homNay),
            ]);
    }

    /**
     * Việc đã xong trong tuần này, tính theo tuần ở giờ Việt Nam.
     *
     * Cùng cái bẫy với `dueToday`: `startOfWeek()` trên mốc UTC cho ra 00:00
     * thứ Hai theo UTC, tức 07:00 sáng thứ Hai giờ Việt Nam — bảy tiếng đầu
     * tuần bị bỏ ra ngoài.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeCompletedThisWeek(Builder $query): void
    {
        $dauTuan = CarbonImmutable::instance(Date::now())
            ->setTimezone(WorkDate::timezone())
            ->startOfWeek()
            ->utc();

        $query->where('status', TaskStatus::Done->value)
            ->whereNotNull('completed_at')
            ->where('completed_at', '>=', $dauTuan);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => TaskStatus::class,
            'priority' => TaskPriority::class,
            'due_date' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            // decimal:2 trả về chuỗi, giữ nguyên độ chính xác của DECIMAL.
            'estimate_hours' => 'decimal:2',
            'progress_percent' => 'integer',
            'due_date_change_count' => 'integer',
            'due_soon_notified_at' => 'datetime',
            'overdue_notified_at' => 'datetime',
        ];
    }
}
