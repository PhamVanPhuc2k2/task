<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Identity\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
final class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            // Lộ uuid, không lộ id tuần tự — xem README "Quy ước dữ liệu".
            'id' => $this->uuid,
            'name' => $this->name,
            'email' => $this->email,
            'employee_code' => $this->employee_code,
            'phone' => $this->phone,
            'is_active' => $this->is_active,
            'joined_at' => $this->joined_at?->toDateString(),
            // Ngày nghỉ việc: giao diện cần để nói "đã nghỉ từ …" thay vì chỉ
            // hiện một nhãn "Đã nghỉ" không cho biết từ bao giờ.
            'terminated_at' => $this->terminated_at?->toDateString(),

            'department' => $this->whenLoaded('department', fn (): ?array => $this->department === null ? null : [
                'id' => $this->department->uuid,
                'name' => $this->department->name,
                'code' => $this->department->code,
            ]),

            'position' => $this->whenLoaded('position', fn (): ?array => $this->position === null ? null : [
                'id' => $this->position->uuid,
                'name' => $this->position->name,
                'level' => $this->position->level,
            ]),

            // Quản lý trực tiếp: form sửa hồ sơ cần để đổ sẵn ô chọn. Chỉ trả
            // id và tên, không lồng cả hồ sơ người quản lý vào — lồng sâu thì
            // một danh sách 100 người kéo theo 100 hồ sơ nữa.
            'manager' => $this->whenLoaded('manager', fn (): ?array => $this->manager === null ? null : [
                'id' => $this->manager->uuid,
                'name' => $this->manager->name,
            ]),

            'roles' => $this->getRoleNames()->values()->all(),
            'permissions' => $this->getAllPermissions()->pluck('name')->values()->all(),
        ];
    }
}
