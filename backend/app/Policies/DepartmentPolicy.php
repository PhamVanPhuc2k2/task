<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Models\Department;
use App\Domain\Identity\Models\User;

/**
 * Quyền thao tác trên cây phòng ban.
 *
 * Chỉ có quyền GHI ở đây. Đọc danh mục phòng ban là công khai với người đã
 * đăng nhập và cố ý như vậy — xem `DepartmentController`: cơ cấu tổ chức nằm
 * trên bảng tin, trong chữ ký email, trong mọi cuộc họp; giấu nó chỉ làm các
 * ô chọn không dùng được mà không thêm an toàn nào.
 */
final class DepartmentPolicy
{
    public function create(User $actor): bool
    {
        return $actor->can(Permission::ManageOrganization->value);
    }

    public function update(User $actor, Department $target): bool
    {
        return $actor->can(Permission::ManageOrganization->value);
    }

    public function delete(User $actor, Department $target): bool
    {
        return $actor->can(Permission::ManageOrganization->value);
    }
}
