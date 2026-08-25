<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Organization;

use App\Domain\Identity\Actions\CreateDepartmentAction;
use App\Domain\Identity\Actions\DeleteDepartmentAction;
use App\Domain\Identity\Actions\UpdateDepartmentAction;
use App\Domain\Identity\Data\DepartmentData;
use App\Domain\Identity\Models\Department;
use App\Http\Requests\Organization\StoreDepartmentRequest;
use App\Http\Requests\Organization\UpdateDepartmentRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Routing\Attributes\Controllers\Authorize;

/**
 * Sửa cây phòng ban.
 *
 * Tách khỏi `DepartmentController` chứ không thêm phương thức vào đó, và không
 * phải vì ngăn nắp: `DepartmentController` nằm trong danh sách miễn khai quyền
 * của `ControllerAuthorizationTest` với lý do "danh mục phòng ban, chỉ đọc".
 * Thêm phương thức ghi vào đó làm lý do kia thành lời nói dối, mà test chỉ dò
 * xem tệp có dấu hiệu kiểm quyền hay không nên nó vẫn xanh.
 *
 * Hai tệp thì ranh giới công khai / quản trị nhìn thấy được ngay từ tên tệp.
 */
final class DepartmentAdminController
{
    #[Authorize('create', Department::class)]
    public function store(StoreDepartmentRequest $request, CreateDepartmentAction $action): JsonResponse
    {
        $phongBan = $action->execute(new DepartmentData(
            name: (string) $request->string('name'),
            code: $request->filled('code') ? (string) $request->string('code') : null,
            description: $request->filled('description') ? (string) $request->string('description') : null,
            parentId: $this->idCha($request->input('parent_id')),
            isActive: $request->boolean('is_active', default: true),
        ));

        return new JsonResponse(
            ['data' => $this->trinhBay($phongBan)],
            Response::HTTP_CREATED,
        );
    }

    #[Authorize('update', 'department')]
    public function update(
        UpdateDepartmentRequest $request,
        Department $department,
        UpdateDepartmentAction $action,
    ): JsonResponse {
        $phongBan = $action->execute($department, new DepartmentData(
            name: (string) $request->string('name'),
            code: $request->filled('code') ? (string) $request->string('code') : null,
            description: $request->filled('description') ? (string) $request->string('description') : null,
            parentId: $this->idCha($request->input('parent_id')),
            isActive: $request->boolean('is_active', default: true),
        ));

        return new JsonResponse(['data' => $this->trinhBay($phongBan)]);
    }

    #[Authorize('delete', 'department')]
    public function destroy(Department $department, DeleteDepartmentAction $action): Response
    {
        $action->execute($department);

        return response()->noContent();
    }

    /**
     * Đổi uuid của phòng ban cấp trên sang khoá chính.
     *
     * API nhận uuid; database nối bằng id tuần tự. Form Request đã kiểm uuid có
     * thật, nên tới đây chỉ còn việc tra.
     */
    private function idCha(mixed $uuid): ?int
    {
        if (! is_string($uuid) || $uuid === '') {
            return null;
        }

        $id = Department::query()->where('uuid', $uuid)->value('id');

        return $id === null ? null : (int) $id;
    }

    /**
     * @return array<string, mixed>
     */
    private function trinhBay(Department $phongBan): array
    {
        $phongBan->loadMissing('parent:id,uuid,name');

        return [
            'id' => $phongBan->uuid,
            'name' => $phongBan->name,
            'code' => $phongBan->code,
            'description' => $phongBan->description,
            'is_active' => $phongBan->is_active,
            'parent_id' => $phongBan->parent?->uuid,
            'parent_name' => $phongBan->parent?->name,
        ];
    }
}
