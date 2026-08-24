<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Users;

use App\Domain\Identity\Actions\CreateUserAction;
use App\Domain\Identity\Actions\UpdateUserAction;
use App\Domain\Identity\Data\CreateUserData;
use App\Domain\Identity\Data\UpdateUserData;
use App\Domain\Identity\Enums\Role;
use App\Domain\Identity\Models\Department;
use App\Domain\Identity\Models\Position;
use App\Domain\Identity\Models\User;
use App\Http\Requests\Auth\StoreUserRequest;
use App\Http\Requests\Auth\UpdateUserRequest;
use App\Http\Resources\UserResource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Routing\Attributes\Controllers\Authorize;

/**
 * Quản trị người dùng. Controller chỉ điều phối.
 *
 * `#[Authorize]` là attribute mới của Laravel 13 — phân quyền khai báo ngay
 * trên phương thức thay vì gọi $this->authorize() trong thân hàm.
 */
final class UserController
{
    #[Authorize('viewAny', User::class)]
    public function index(Request $request): AnonymousResourceCollection
    {
        $users = User::query()
            ->when($request->filled('search'), function (Builder $q) use ($request): void {
                $tuKhoa = '%'.$request->string('search').'%';

                $q->where(fn (Builder $s) => $s
                    ->where('name', 'like', $tuKhoa)
                    ->orWhere('email', 'like', $tuKhoa)
                    ->orWhere('employee_code', 'like', $tuKhoa));
            })
            ->when(
                $request->filled('department_id'),
                fn (Builder $q) => $q->where(
                    'department_id',
                    $this->resolveId(Department::class, $request->input('department_id')),
                ),
            )
            // Dùng scope `role()` của spatie/laravel-permission thay vì tự viết
            // whereHas: bên trong closure của whereHas, tham số chỉ còn là
            // Builder chung nên phân tích tĩnh không kiểm được tên cột.
            ->when(
                $request->filled('role'),
                fn (Builder $q) => $q->role((string) $request->string('role')),
            )
            // Mặc định CHỈ hiện người đang làm việc. Người đã nghỉ vẫn còn
            // trong hệ thống để giữ lịch sử, nhưng trộn lẫn vào danh sách nhân
            // sự thì con số "công ty có bao nhiêu người" luôn sai.
            ->when(
                ! $request->boolean('include_inactive'),
                fn (Builder $q) => $q->where('is_active', true),
            )
            ->with(['department', 'position', 'manager', 'roles.permissions', 'permissions'])
            ->orderBy('name')
            ->paginate(perPage: min((int) $request->integer('per_page', 25), 100))
            ->withQueryString();

        return UserResource::collection($users);
    }

    #[Authorize('create', User::class)]
    public function store(StoreUserRequest $request, CreateUserAction $action): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        $ketQua = $action->execute(new CreateUserData(
            name: (string) $request->string('name'),
            email: (string) $request->string('email'),
            employeeCode: (string) $request->string('employee_code'),
            role: Role::from((string) $request->string('role')),
            phone: $request->filled('phone') ? (string) $request->string('phone') : null,
            departmentId: $this->resolveId(Department::class, $request->input('department_id')),
            positionId: $this->resolveId(Position::class, $request->input('position_id')),
            managerId: $this->resolveId(User::class, $request->input('manager_id')),
            joinedAt: $request->filled('joined_at') ? (string) $request->string('joined_at') : null,
        ), $actor);

        return UserResource::make(
            $ketQua->user->load(['department', 'position', 'roles.permissions', 'permissions']),
        )
            // Mật khẩu tạm hiện đúng một lần, ở đúng đây. Không lưu lại, không
            // gửi email — người tạo tài khoản đọc cho nhân viên mới, và nhân
            // viên đổi ngay lần đăng nhập đầu.
            ->additional(['meta' => ['temporary_password' => $ketQua->temporaryPassword]])
            ->response()->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Sửa hồ sơ nhân viên.
     *
     * Ngữ nghĩa **thay thế toàn bộ** (PUT), không phải sửa từng phần — lý do
     * ghi ở `UpdateUserData`. Form ở giao diện gửi đủ mọi trường mỗi lần lưu.
     */
    #[Authorize('update', 'user')]
    public function update(UpdateUserRequest $request, User $user, UpdateUserAction $action): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        $ketQua = $action->execute($user, new UpdateUserData(
            name: (string) $request->string('name'),
            email: (string) $request->string('email'),
            employeeCode: (string) $request->string('employee_code'),
            role: Role::from((string) $request->string('role')),
            phone: $request->filled('phone') ? (string) $request->string('phone') : null,
            departmentId: $this->resolveId(Department::class, $request->input('department_id')),
            positionId: $this->resolveId(Position::class, $request->input('position_id')),
            managerId: $this->resolveId(User::class, $request->input('manager_id')),
            joinedAt: $request->filled('joined_at') ? (string) $request->string('joined_at') : null,
        ), $actor);

        return UserResource::make(
            $ketQua->user->load(['department', 'position', 'manager', 'roles.permissions', 'permissions']),
        )
            // Cảnh báo là hệ quả đúng nhưng dễ bất ngờ, không phải lỗi — nằm ở
            // `meta` để giao diện hiện sau khi lưu thành công.
            ->additional(['meta' => ['warnings' => $ketQua->warnings]])
            ->response();
    }

    /**
     * Đổi uuid nhận từ client sang khoá chính nội bộ.
     *
     * @param  class-string<Department|Position|User>  $model
     */
    private function resolveId(string $model, mixed $uuid): ?int
    {
        if (! \is_string($uuid) || $uuid === '') {
            return null;
        }

        $id = $model::query()->where('uuid', $uuid)->value('id');

        return $id === null ? null : (int) $id;
    }
}
