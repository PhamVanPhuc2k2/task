<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Projects;

use App\Domain\Identity\Models\Department;
use App\Domain\Identity\Models\User;
use App\Domain\Task\Actions\CreateProjectAction;
use App\Domain\Task\Data\CreateProjectData;
use App\Domain\Task\Enums\ProjectStatus;
use App\Domain\Task\Models\Project;
use App\Http\Concerns\ResolvesUuids;
use App\Http\Requests\Project\StoreProjectRequest;
use App\Http\Requests\Project\UpdateProjectRequest;
use App\Http\Resources\ProjectResource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Routing\Attributes\Controllers\Authorize;

/**
 * Dự án — danh sách, chi tiết, tạo, sửa, xoá.
 *
 * Mọi truy vấn đi qua scope `visibleTo` của Project. Đây là ràng buộc bảo mật:
 * dự án lộ tên khách hàng và kế hoạch kinh doanh.
 */
final class ProjectController
{
    use ResolvesUuids;

    #[Authorize('viewAny', Project::class)]
    public function index(Request $request): AnonymousResourceCollection
    {
        /** @var User $actor */
        $actor = $request->user();

        $projects = Project::query()
            ->visibleTo($actor)
            ->when(
                $request->filled('status'),
                fn (Builder $q) => $q->where('status', $request->string('status')),
            )
            ->when(
                $request->boolean('open'),
                fn (Builder $q) => $q->whereIn('status', ProjectStatus::openValues()),
            )
            ->when($request->filled('search'), function (Builder $q) use ($request): void {
                $tuKhoa = '%'.$request->string('search').'%';

                $q->where(fn (Builder $s) => $s
                    ->where('name', 'like', $tuKhoa)
                    ->orWhere('code', 'like', $tuKhoa));
            })
            ->with(['owner', 'department'])
            ->withCount(['tasks', 'members'])
            ->orderBy('name')
            ->paginate(perPage: min((int) $request->integer('per_page', 25), 100))
            ->withQueryString();

        return ProjectResource::collection($projects);
    }

    #[Authorize('view', 'project')]
    public function show(Project $project): ProjectResource
    {
        return ProjectResource::make(
            $project->load(['owner', 'department'])->loadCount(['tasks', 'members']),
        );
    }

    #[Authorize('create', Project::class)]
    public function store(StoreProjectRequest $request, CreateProjectAction $action): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        $project = $action->execute(
            new CreateProjectData(
                name: (string) $request->string('name'),
                code: $request->filled('code') ? (string) $request->string('code') : null,
                description: $request->filled('description') ? (string) $request->string('description') : null,
                ownerId: $this->resolveId(User::class, $request->input('owner_id')),
                departmentId: $this->resolveId(Department::class, $request->input('department_id')),
                status: ProjectStatus::tryFrom((string) $request->string('status')) ?? ProjectStatus::Planning,
                startDate: $request->filled('start_date') ? (string) $request->string('start_date') : null,
                endDate: $request->filled('end_date') ? (string) $request->string('end_date') : null,
            ),
            $actor,
        );

        return ProjectResource::make($project->load(['owner', 'department']))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    #[Authorize('update', 'project')]
    public function update(UpdateProjectRequest $request, Project $project): ProjectResource
    {
        /** @var User $actor */
        $actor = $request->user();

        $project->fill($request->only([
            'name', 'code', 'description', 'start_date', 'end_date',
        ]));

        if ($request->filled('status')) {
            $project->status = ProjectStatus::from((string) $request->string('status'));
        }

        if ($request->has('owner_id')) {
            $project->owner_id = $this->resolveId(User::class, $request->input('owner_id'));
        }

        if ($request->has('department_id')) {
            $project->department_id = $this->resolveId(Department::class, $request->input('department_id'));
        }

        $project->updated_by = $actor->id;
        $project->save();

        return ProjectResource::make($project->load(['owner', 'department']));
    }

    #[Authorize('delete', 'project')]
    public function destroy(Project $project): JsonResponse
    {
        // Xoá mềm: dự án đã chạy là lịch sử công việc của cả đội, không phải
        // một dòng dữ liệu bỏ đi.
        $project->delete();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
