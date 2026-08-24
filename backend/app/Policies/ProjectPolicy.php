<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Models\User;
use App\Domain\Task\Models\Project;

/**
 * Quyền thao tác trên từng dự án.
 *
 * `viewAny` mở cho mọi người đã đăng nhập vì danh sách đã bị scope `visibleTo`
 * cắt sẵn — người không tham gia dự án nào sẽ nhận về danh sách rỗng, không
 * phải lỗi 403. Chặn ở đây chỉ tạo màn hình trắng khó hiểu mà không thêm an
 * toàn nào.
 */
final class ProjectPolicy
{
    public function viewAny(User $actor): bool
    {
        return true;
    }

    public function view(User $actor, Project $project): bool
    {
        return $project->isVisibleTo($actor);
    }

    public function create(User $actor): bool
    {
        return $actor->can(Permission::ManageProjects->value);
    }

    /**
     * Sửa: người có quyền quản lý dự án, hoặc chính chủ dự án.
     *
     * Giao dự án cho một nhân viên phụ trách là chuyện bình thường; bắt họ xin
     * quyền toàn hệ thống chỉ để đổi mô tả dự án của mình là vô lý.
     */
    public function update(User $actor, Project $project): bool
    {
        if ($project->owner_id === $actor->id) {
            return true;
        }

        return $actor->can(Permission::ManageProjects->value)
            && $project->isVisibleTo($actor);
    }

    /**
     * Xoá: chỉ người có quyền quản lý dự án.
     *
     * Nặng hơn sửa — xoá là giấu đi cả một mảng công việc của nhiều người, nên
     * chủ dự án thôi chưa đủ.
     */
    public function delete(User $actor, Project $project): bool
    {
        return $actor->can(Permission::ManageProjects->value)
            && $project->isVisibleTo($actor);
    }

    /** Thêm, đổi vai trò, gỡ thành viên — cùng mức với sửa dự án. */
    public function manageMembers(User $actor, Project $project): bool
    {
        return $this->update($actor, $project);
    }
}
