<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Models\User;

/**
 * Quyền thao tác trên từng bản ghi người dùng.
 *
 * Quyền (`Permission`) trả lời "được làm loại việc này không". Policy trả lời
 * "được đụng vào đúng bản ghi này không". Xem README, bảng pattern.
 */
final class UserPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can(Permission::ManageUsers->value);
    }

    public function view(User $actor, User $target): bool
    {
        // Ai cũng xem được hồ sơ của chính mình.
        return $actor->is($target) || $actor->can(Permission::ManageUsers->value);
    }

    public function create(User $actor): bool
    {
        return $actor->can(Permission::ManageUsers->value);
    }

    public function update(User $actor, User $target): bool
    {
        return $actor->can(Permission::ManageUsers->value);
    }

    public function deactivate(User $actor, User $target): bool
    {
        return $actor->can(Permission::ManageUsers->value);
    }

    public function resetPassword(User $actor, User $target): bool
    {
        return $actor->can(Permission::ManageUsers->value);
    }

    public function resetTwoFactor(User $actor, User $target): bool
    {
        return $actor->can(Permission::ManageUsers->value);
    }

    /**
     * Đặt mức lương cho người này.
     *
     * Không tự đặt lương cho chính mình, kể cả khi có quyền `payroll.manage` —
     * cùng họ với luật chặn tự đổi vai trò và tự duyệt ngày công của mình. Đây
     * là ràng buộc mà thiếu nó thì cả cơ chế duyệt lương mất ý nghĩa.
     */
    public function setSalary(User $actor, User $target): bool
    {
        return ! $actor->is($target)
            && $actor->can(Permission::ManageSalary->value);
    }
}
