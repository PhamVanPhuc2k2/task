<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Models\User;
use App\Domain\Task\Models\TaskComment;

/**
 * Quyền thao tác trên từng bình luận.
 *
 * Đọc và viết bình luận đi theo quyền xem task: thấy được task thì đọc và trao
 * đổi được trên đó. Không cần một quyền riêng — bình luận là cách làm việc
 * hằng ngày, dựng thêm một lớp quyền nữa chỉ tạo ra tài khoản không nói được
 * gì trong chính việc của mình.
 */
final class TaskCommentPolicy
{
    public function view(User $actor, TaskComment $comment): bool
    {
        return $comment->task->isVisibleTo($actor);
    }

    /**
     * Sửa: chỉ chính tác giả.
     *
     * Quản lý cũng không sửa được lời của người khác. Sửa được lời người khác
     * thì toàn bộ dòng trao đổi mất giá trị làm bằng chứng — trong một hệ
     * thống có thưởng phạt theo tiến độ, đó là chuyện lớn.
     */
    public function update(User $actor, TaskComment $comment): bool
    {
        return $comment->user_id === $actor->id
            && $comment->task->isVisibleTo($actor);
    }

    /**
     * Xoá: tác giả, hoặc người có quyền xoá task.
     *
     * Người quản lý cần đường gỡ nội dung không phù hợp. Xoá là xoá mềm nên
     * vẫn còn vết.
     */
    public function delete(User $actor, TaskComment $comment): bool
    {
        if (! $comment->task->isVisibleTo($actor)) {
            return false;
        }

        return $comment->user_id === $actor->id
            || $actor->can(Permission::DeleteTask->value);
    }
}
