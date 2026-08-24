<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Organization;

use App\Domain\Identity\Models\Position;
use Illuminate\Http\JsonResponse;

/**
 * Danh sách chức vụ, dùng để vẽ ô chọn trong form nhân sự.
 *
 * Sắp theo `level` giảm dần rồi tới tên: người dùng mở ô chọn thường tìm chức
 * vụ cao trước. Xem thêm chú thích ở DepartmentController về việc vì sao đường
 * này không cần Policy riêng.
 *
 * Khác `Role` (vai trò trong phần mềm): chức vụ là danh nghĩa trên giấy tờ
 * nhân sự, còn vai trò quyết định làm được gì trong hệ thống. Một trưởng phòng
 * trên danh nghĩa vẫn có thể chỉ được cấp vai trò nhân viên.
 */
final class PositionController
{
    public function index(): JsonResponse
    {
        $chucVu = Position::query()
            ->where('is_active', true)
            ->orderByDesc('level')
            ->orderBy('name')
            ->get();

        return new JsonResponse(['data' => $chucVu->map(fn (Position $c): array => [
            'id' => $c->uuid,
            'name' => $c->name,
            'code' => $c->code,
            'level' => $c->level,
        ])->all()]);
    }
}
