<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Dashboard;

use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Models\User;
use App\Domain\Task\Enums\ProjectStatus;
use App\Domain\Task\Enums\TaskStatus;
use App\Domain\Task\Models\Project;
use App\Domain\Task\Models\Task;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Date;

/**
 * Trang tổng quan cho người nhìn được toàn công ty.
 *
 * Trước khi có màn này, quản trị viên và giám đốc đăng nhập vào là rơi thẳng
 * vào "Hôm nay của tôi" — màn lọc theo `assignee_id` của chính họ. Mà không ai
 * giao việc cho giám đốc, nên thứ họ thấy mỗi sáng là một dòng chữ "Bạn không
 * còn việc nào đang mở". Đúng về mặt kỹ thuật, vô dụng về mặt sử dụng.
 *
 * **Vì sao là endpoint gom sẵn chứ không tính ở frontend.** `/tasks/team` phân
 * trang 25 dòng; cộng dồn từ đó ra con số "toàn công ty có bao nhiêu việc trễ"
 * sẽ luôn sai, và sai theo kiểu không ai nhận ra — nó vẫn hiện một con số trông
 * hợp lý. Mọi thống kê ở đây tính bằng `COUNT`/`GROUP BY` chạy trên database.
 *
 * **Số lượng truy vấn cố định**, không phụ thuộc số nhân sự hay số dự án: một
 * câu cho các con số chính, một câu gom tải việc theo người, một câu nạp tên
 * người, một câu gom tiến độ dự án, một câu lấy việc trễ lâu nhất. Có test khoá
 * lại điều này — đây đúng là loại màn hình dễ trở thành N+1 nhất.
 */
final class OverviewController
{
    /** Số dòng tối đa cho mỗi bảng. Phần bị cắt luôn được báo bằng `*_total`. */
    private const int GIOI_HAN_BANG = 12;

    private const int GIOI_HAN_TRE = 8;

    public function __invoke(Request $request): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        // Chỉ người nhìn được toàn công ty. Trưởng phòng có `task.view.team`
        // nhưng phạm vi của họ là phòng mình — một trang "tổng quan công ty"
        // lọc theo phòng sẽ là màn hình khác, với ý nghĩa khác.
        abort_unless($actor->can(Permission::ViewAllTasks->value), Response::HTTP_FORBIDDEN);

        // `Date::now()` chứ không `CarbonImmutable::now()`: facade là thứ
        // `travelTo()` trong test tác động vào. AppServiceProvider đã gọi
        // `Date::use(CarbonImmutable::class)` nên kiểu thật luôn là bất biến.
        /** @var CarbonImmutable $bayGio */
        $bayGio = Date::now();

        return new JsonResponse([
            'data' => [
                'summary' => $this->conSoChinh($bayGio),
                'workload' => $this->taiViecTheoNguoi($bayGio),
                'projects' => $this->tienDoDuAn($bayGio),
                'most_overdue' => $this->treLauNhat($bayGio),
            ],
        ]);
    }

    /**
     * @return array<string, int>
     */
    private function conSoChinh(CarbonImmutable $bayGio): array
    {
        $dongMo = Task::query()->whereNotIn('status', TaskStatus::closedValues());

        return [
            'open_tasks' => (clone $dongMo)->count(),
            'overdue_tasks' => (clone $dongMo)->whereNotNull('due_date')
                ->where('due_date', '<', $bayGio)->count(),
            // Dùng scope của model chứ không viết lại truy vấn ở đây: trang
            // Công việc lọc bằng đúng scope đó, nên con số và danh sách không
            // thể lệch nhau. Scope cũng là chỗ đã sửa bẫy múi giờ — bản trước
            // ở đây dùng `endOfDay()` theo UTC, lệch bảy tiếng.
            'due_today' => Task::query()->dueToday()->count(),

            // Việc không có người làm là thứ dễ trôi nhất: không ai nhận thông
            // báo nhắc hạn, không xuất hiện trong "việc của tôi" của bất kỳ ai.
            'unassigned_tasks' => Task::query()->unassigned()->count(),

            'completed_this_week' => Task::query()->completedThisWeek()->count(),

            'active_projects' => Project::query()
                ->whereIn('status', ProjectStatus::openValues())->count(),

            'active_employees' => User::query()->where('is_active', true)->count(),
        ];
    }

    /**
     * Ai đang ôm bao nhiêu việc, và bao nhiêu trong đó đã trễ.
     *
     * Gom ở database bằng `GROUP BY assignee_id` rồi mới nạp tên trong đúng một
     * câu nữa. Duyệt từng người rồi đếm việc của họ là công thức chuẩn để sinh
     * ra N+1 — với hai trăm nhân sự là hai trăm câu truy vấn.
     *
     * @return array{rows: list<array<string, mixed>>, total: int}
     */
    private function taiViecTheoNguoi(CarbonImmutable $bayGio): array
    {
        $gom = Task::query()
            ->selectRaw('assignee_id')
            ->selectRaw('COUNT(*) as open_count')
            ->selectRaw(
                'SUM(CASE WHEN due_date IS NOT NULL AND due_date < ? THEN 1 ELSE 0 END) as overdue_count',
                [$bayGio],
            )
            ->whereNotNull('assignee_id')
            ->whereNotIn('status', TaskStatus::closedValues())
            ->groupBy('assignee_id')
            ->orderByDesc('overdue_count')
            ->orderByDesc('open_count')
            ->get();

        $topN = $gom->take(self::GIOI_HAN_BANG);

        /** @var Collection<int, User> $nguoi */
        $nguoi = User::query()
            ->whereIn('id', $topN->pluck('assignee_id')->all())
            ->with('department')
            ->get()
            ->keyBy('id');

        $rows = [];

        foreach ($topN as $dong) {
            $u = $nguoi->get($dong->getAttribute('assignee_id'));

            if ($u === null) {
                continue;
            }

            $rows[] = [
                'id' => $u->uuid,
                'name' => $u->name,
                'department' => $u->department?->name,
                'open' => (int) $dong->getAttribute('open_count'),
                'overdue' => (int) $dong->getAttribute('overdue_count'),
            ];
        }

        return ['rows' => $rows, 'total' => $gom->count()];
    }

    /**
     * Tiến độ từng dự án: xong bao nhiêu trên tổng, và đang trễ mấy việc.
     *
     * Dự án đã huỷ bị loại hẳn; dự án đã hoàn thành vẫn hiện, vì "vừa xong
     * tuần trước" là thông tin có ích trên màn tổng quan.
     *
     * @return array{rows: list<array<string, mixed>>, total: int}
     */
    private function tienDoDuAn(CarbonImmutable $bayGio): array
    {
        $duAn = Project::query()
            ->where('status', '!=', ProjectStatus::Cancelled->value)
            ->withCount([
                'tasks',
                // Tên cột có tiền tố bảng vì `projects` cũng có cột `status`
                // — bỏ tiền tố ra là SQL mơ hồ.
                'tasks as done_count' => fn (Builder $q) => $q->where(
                    'tasks.status',
                    TaskStatus::Done->value,
                ),
                'tasks as overdue_count' => fn (Builder $q) => $q
                    ->whereNotIn('tasks.status', TaskStatus::closedValues())
                    ->whereNotNull('tasks.due_date')
                    ->where('tasks.due_date', '<', $bayGio),
            ])
            ->orderByDesc('overdue_count')
            ->orderByDesc('tasks_count')
            ->get();

        $rows = [];

        foreach ($duAn->take(self::GIOI_HAN_BANG) as $d) {
            $tong = (int) $d->getAttribute('tasks_count');
            $xong = (int) $d->getAttribute('done_count');

            $rows[] = [
                'id' => $d->uuid,
                'name' => $d->name,
                'status' => ['value' => $d->status->value, 'label' => $d->status->label()],
                'total' => $tong,
                'done' => $xong,
                'overdue' => (int) $d->getAttribute('overdue_count'),
                // Dự án chưa có việc nào thì tiến độ là 0, không phải 100. Chia
                // cho 0 ở đây sẽ thành "hoàn thành" trên màn hình.
                'progress_percent' => $tong === 0 ? 0 : (int) round($xong / $tong * 100),
            ];
        }

        return ['rows' => $rows, 'total' => $duAn->count()];
    }

    /**
     * Việc trễ lâu nhất, cũ nhất lên đầu.
     *
     * @return list<array<string, mixed>>
     */
    private function treLauNhat(CarbonImmutable $bayGio): array
    {
        $dong = Task::query()
            ->whereNotIn('status', TaskStatus::closedValues())
            ->whereNotNull('due_date')
            ->where('due_date', '<', $bayGio)
            ->with(['assignee', 'project'])
            ->orderBy('due_date')
            ->limit(self::GIOI_HAN_TRE)
            ->get()
            ->map(fn (Task $t): array => [
                'id' => $t->uuid,
                'title' => $t->title,
                'assignee' => $t->assignee?->name,
                'project' => $t->project?->name,
                'due_date' => $t->due_date?->toIso8601String(),
                // Tính ở backend chứ không để frontend trừ ngày: chỉ có backend
                // biết chắc múi giờ hiển thị của công ty.
                'days_overdue' => $t->due_date === null
                    ? 0
                    : (int) $t->due_date->startOfDay()->diffInDays($bayGio->startOfDay()),
            ])
            ->all();

        // `array_values` để kiểu trả về đúng là `list` — Collection::all() giữ
        // nguyên khoá, và phân tích tĩnh không chấp nhận đó là danh sách liền.
        return array_values($dong);
    }
}
