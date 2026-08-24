<?php

declare(strict_types=1);

namespace App\Domain\Task\Listeners;

use App\Domain\Identity\Models\User;
use App\Domain\Task\Events\CommentPosted;
use App\Domain\Task\Notifications\CommentAddedNotification;
use App\Domain\Task\Notifications\MentionedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;

/**
 * Báo cho những người liên quan tới một bình luận mới.
 *
 * Hai nhóm, hai loại thông báo khác nhau:
 *
 * - **Được nhắc tên** — lời gọi trực tiếp, gửi `MentionedNotification`.
 * - **Đang theo dõi task** — tin nền, gửi `CommentAddedNotification`.
 *
 * Người vừa được nhắc KHÔNG nhận thêm bản "có bình luận mới", dù họ cũng nằm
 * trong danh sách theo dõi (Action tự thêm họ vào). Hai thông báo cho cùng một
 * bình luận là thứ khiến người dùng tắt hết thông báo.
 *
 * Người tự viết bình luận cũng không nhận gì — họ vừa gõ xong câu đó.
 */
final class SendCommentNotifications implements ShouldQueue
{
    public function handle(CommentPosted $event): void
    {
        $comment = $event->comment;
        $tacGia = $comment->user_id;

        $duocNhac = User::query()
            ->with('notificationSettings')
            ->where('is_active', true)
            ->whereIn('id', $event->duocNhac)
            ->get();

        if ($duocNhac->isNotEmpty()) {
            Notification::send($duocNhac, new MentionedNotification($comment));
        }

        $theoDoi = $comment->task->watchers()
            ->with('notificationSettings')
            ->where('users.is_active', true)
            ->whereNotIn('users.id', [...$event->duocNhac, ...($tacGia === null ? [] : [$tacGia])])
            ->get();

        if ($theoDoi->isNotEmpty()) {
            Notification::send($theoDoi, new CommentAddedNotification($comment));
        }
    }
}
