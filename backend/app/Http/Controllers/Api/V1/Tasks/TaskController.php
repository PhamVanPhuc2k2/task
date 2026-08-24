<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Tasks;

use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Models\User;
use App\Domain\Task\Actions\CreateTaskAction;
use App\Domain\Task\Data\CreateTaskData;
use App\Domain\Task\Enums\TaskPriority;
use App\Domain\Task\Models\Project;
use App\Domain\Task\Models\Task;
use App\Http\Concerns\ResolvesUuids;
use App\Http\Requests\Task\StoreTaskRequest;
use App\Http\Resources\TaskResource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Routing\Attributes\Controllers\Authorize;

/**
 * Công việc — danh sách, chi tiết, tạo, sửa, xoá.
 *
 * Mọi truy vấn đều đi qua scope `visibleTo`. Đây là ràng buộc bảo mật, không
 * phải bộ lọc tiện ích: quên gọi một lần là lộ task toàn công ty.
 */
final class TaskController
{
    use ResolvesUuids;

    #[Authorize('viewAny', Task::class)]
    public function index(Request $request): AnonymousResourceCollection
    {
        /** @var User $actor */
        $actor = $request->user();

        $tasks = Task::query()
            ->visibleTo($actor)
            ->when(
                $request->filled('status'),
                fn (Builder $q) => $q->where('status', $request->string('status')),
            )
            ->when(
                $request->filled('priority'),
                fn (Builder $q) => $q->where('priority', $request->string('priority')),
            )
            // Đổi uuid sang khoá chính rồi lọc thẳng trên cột khoá ngoại, thay
            // vì whereHas: dùng đúng index (assignee_id, status, due_date) và
            // không phải nối bảng chỉ để đối chiếu một uuid.
            ->when(
                $request->filled('assignee_id'),
                fn (Builder $q) => $q->where(
                    'assignee_id',
                    $this->resolveId(User::class, $request->input('assignee_id')),
                ),
            )
            ->when(
                $request->filled('project_id'),
                fn (Builder $q) => $q->where(
                    'project_id',
                    $this->resolveId(Project::class, $request->input('project_id')),
                ),
            )
            /*
            | Bốn phép lọc dưới đây khớp 1-1 với bốn ô số ở trang Tổng quan, và
            | đều gọi **cùng một scope** mà trang đó dùng để đếm.
            |
            | Đây là điều kiện để việc bấm vào con số có nghĩa: ô ghi "12 việc
            | quá hạn" thì danh sách mở ra phải đúng 12 dòng. Mỗi bên tự viết
            | truy vấn riêng là sớm muộn lệch nhau, và người dùng mất niềm tin
            | vào cả hai con số chứ không riêng con số sai.
            */
            ->when($request->boolean('overdue'), fn (Builder $q) => $q->overdue())
            ->when($request->boolean('open'), fn (Builder $q) => $q->open())
            ->when($request->boolean('unassigned'), fn (Builder $q) => $q->unassigned())
            ->when($request->boolean('due_today'), fn (Builder $q) => $q->dueToday())
            ->when(
                $request->boolean('completed_this_week'),
                fn (Builder $q) => $q->completedThisWeek(),
            )
            ->when($request->filled('due_from'), fn (Builder $q) => $q->where('due_date', '>=', $request->date('due_from')))
            ->when($request->filled('due_to'), fn (Builder $q) => $q->where('due_date', '<=', $request->date('due_to')))
            ->when($request->filled('search'), function (Builder $q) use ($request): void {
                $tuKhoa = '%'.$request->string('search').'%';

                $q->where(fn (Builder $s) => $s
                    ->where('title', 'like', $tuKhoa)
                    ->orWhere('description', 'like', $tuKhoa));
            })
            ->with(['project', 'assignee', 'assigner', 'labels'])
            ->withCount(['subtasks', 'comments'])
            // Việc chưa có hạn xếp cuối — chúng không gấp bằng việc có hạn.
            ->orderByRaw('due_date IS NULL, due_date')
            ->paginate(perPage: min((int) $request->integer('per_page', 25), 100))
            ->withQueryString();

        return TaskResource::collection($tasks);
    }

    #[Authorize('view', 'task')]
    public function show(Task $task): TaskResource
    {
        return TaskResource::make($task->load([
            'project', 'assignee', 'assigner', 'reviewer', 'labels',
        ])->loadCount(['subtasks', 'comments']));
    }

    #[Authorize('create', Task::class)]
    public function store(StoreTaskRequest $request, CreateTaskAction $action): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        $assigneeId = $this->resolveId(User::class, $request->input('assignee_id'));

        // Giao việc cho người khác cần quyền riêng. Tự tạo việc cho mình thì
        // không — đó là việc hằng ngày của nhân viên.
        if ($assigneeId !== null && $assigneeId !== $actor->id) {
            $this->authorizeAssign($actor);
        }

        $task = $action->execute(
            new CreateTaskData(
                title: (string) $request->string('title'),
                description: $request->filled('description') ? (string) $request->string('description') : null,
                projectId: $this->resolveId(Project::class, $request->input('project_id')),
                parentTaskId: $this->resolveId(Task::class, $request->input('parent_task_id')),
                assigneeId: $assigneeId,
                reviewerId: $this->resolveId(User::class, $request->input('reviewer_id')),
                priority: TaskPriority::tryFrom((string) $request->string('priority')) ?? TaskPriority::Normal,
                dueDate: $request->filled('due_date') ? (string) $request->string('due_date') : null,
                estimateHours: $request->filled('estimate_hours') ? (string) $request->string('estimate_hours') : null,
            ),
            $actor,
        );

        return TaskResource::make($task->load(['project', 'assignee', 'assigner']))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    #[Authorize('update', 'task')]
    public function update(StoreTaskRequest $request, Task $task): TaskResource
    {
        /** @var User $actor */
        $actor = $request->user();

        $task->fill($request->only(['title', 'description']));

        if ($request->filled('priority')) {
            $task->priority = TaskPriority::from((string) $request->string('priority'));
        }

        if ($request->has('progress_percent')) {
            $task->progress_percent = min(100, max(0, (int) $request->integer('progress_percent')));
        }

        $task->updated_by = $actor->id;
        $task->save();

        return TaskResource::make($task->load(['project', 'assignee', 'assigner']));
    }

    #[Authorize('delete', 'task')]
    public function destroy(Task $task): JsonResponse
    {
        // Xoá mềm: task đã giao là một phần lịch sử làm việc của nhân viên.
        $task->delete();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /** Chỉ dùng cho nhánh "giao việc cho người khác" lúc tạo task. */
    private function authorizeAssign(User $actor): void
    {
        abort_unless(
            $actor->can(Permission::AssignTask->value),
            Response::HTTP_FORBIDDEN,
        );
    }
}
