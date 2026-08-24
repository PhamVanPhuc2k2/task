<?php

declare(strict_types=1);

namespace App\Domain\Task\Models;

use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Models\Department;
use App\Domain\Identity\Models\User;
use App\Domain\Task\Enums\ProjectStatus;
use App\Support\Concerns\HasUuid;
use Carbon\CarbonImmutable;
use Database\Factories\Task\ProjectFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

/**
 * Dự án — nhóm nhiều task lại theo một mục tiêu.
 *
 * Khối `@property` là bắt buộc: Larastan suy kiểu từ migration nên thấy
 * `status` là string chứ không thấy nó đã cast sang enum. Xem chú thích cùng
 * loại ở Task.
 *
 * @property int $id
 * @property string $uuid
 * @property string $name
 * @property string|null $code
 * @property string|null $description
 * @property int|null $owner_id
 * @property int|null $department_id
 * @property ProjectStatus $status
 * @property CarbonImmutable|null $start_date
 * @property CarbonImmutable|null $end_date
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property CarbonImmutable|null $deleted_at
 */
#[Fillable([
    'name', 'code', 'description', 'owner_id', 'department_id',
    'status', 'start_date', 'end_date', 'created_by', 'updated_by',
])]
final class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory;

    use HasUuid;
    use SoftDeletes;

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /** @return BelongsTo<Department, $this> */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /** @return BelongsToMany<User, $this> */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot('role')
            ->withTimestamps();
    }

    /** @return HasMany<Task, $this> */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    /**
     * Giới hạn truy vấn về những dự án người này được thấy.
     *
     * Ràng buộc bảo mật, không phải bộ lọc tiện ích: dự án lộ tên khách hàng và
     * kế hoạch kinh doanh. Mặc định là không thấy — phải có lý do cụ thể.
     *
     * Người được giao task trong dự án cũng thấy dự án: không thì họ nhìn task
     * mà không hiểu mình đang làm cho việc gì.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if ($user->can(Permission::ManageProjects->value) || $user->can(Permission::ViewAllTasks->value)) {
            return;
        }

        // Viết nhánh "có task của tôi" bằng truy vấn con thay vì orWhereHas:
        // trong closure của whereHas, tham số chỉ còn là Builder chung nên phân
        // tích tĩnh không kiểm được tên cột. Ở đây Task::query() giữ nguyên kiểu
        // nên gõ sai tên cột là lỗi ngay lúc phân tích.
        $duAnCoViecCuaToi = Task::query()
            ->select('project_id')
            ->where('assignee_id', $user->id);

        $query->where(function (Builder $scope) use ($user, $duAnCoViecCuaToi): void {
            $scope->where('owner_id', $user->id)
                ->orWhereHas('members', fn (Builder $m) => $m->whereKey($user->id))
                ->orWhereIn('id', $duAnCoViecCuaToi);
        });
    }

    public function isVisibleTo(User $user): bool
    {
        return self::query()->whereKey($this->getKey())->visibleTo($user)->exists();
    }

    /**
     * Vai trò của từng thành viên, khoá theo id người dùng.
     *
     * Đọc thẳng bảng nối thay vì `$user->pivot->role`: thuộc tính pivot không
     * khai báo được kiểu nên phân tích tĩnh không kiểm được tên cột. Cách này
     * trả về đúng một mảng int => string trong một truy vấn, và giữ tên bảng
     * nối nằm trong miền Task thay vì rò lên tầng Http.
     *
     * @return array<int, string>
     */
    public function memberRoles(): array
    {
        $rows = DB::table('project_user')
            ->where('project_id', $this->id)
            ->get(['user_id', 'role']);

        $vaiTro = [];

        foreach ($rows as $row) {
            $vaiTro[(int) $row->user_id] = (string) $row->role;
        }

        return $vaiTro;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ProjectStatus::class,
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }
}
