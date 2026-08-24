<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Organization;

use App\Domain\Identity\Models\Department;
use Illuminate\Http\JsonResponse;

/**
 * Danh sách phòng ban, dùng để vẽ ô chọn trong form nhân sự.
 *
 * Không phân trang và không có Policy riêng: cơ cấu tổ chức là thông tin ai
 * trong công ty cũng biết — nó nằm trên bảng tin, trong chữ ký email, trong
 * mọi cuộc họp. Giấu nó chỉ làm form không dùng được mà không thêm an toàn nào.
 *
 * Trả về dạng phẳng kèm `parent_id` để frontend tự dựng cây nếu cần. Trả về
 * cây lồng nhau sẵn thì mỗi chỗ dùng lại phải duyệt đệ quy chỉ để đổ vào một
 * thẻ `<select>`.
 */
final class DepartmentController
{
    public function index(): JsonResponse
    {
        $phongBan = Department::query()
            ->where('is_active', true)
            ->with('parent:id,uuid,name')
            ->orderBy('name')
            ->get();

        return new JsonResponse(['data' => $phongBan->map(fn (Department $p): array => [
            'id' => $p->uuid,
            'name' => $p->name,
            'code' => $p->code,
            'parent_id' => $p->parent?->uuid,
            'parent_name' => $p->parent?->name,
        ])->all()]);
    }
}
