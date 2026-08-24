<?php

declare(strict_types=1);

namespace App\Domain\Task\Notifications;

use App\Domain\Identity\Enums\NotificationType;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Notifications\PreferenceAwareNotification;
use App\Domain\Task\Models\TaskComment;
use Illuminate\Queue\SerializesModels;

/**
 * Ai đó nhắc tên bạn trong một bình luận.
 *
 * Tách khỏi `CommentAddedNotification` chứ không gộp làm một: được nhắc đích
 * danh là lời gọi trực tiếp, còn "có bình luận mới trên việc mình theo dõi" chỉ
 * là tin nền. Người dùng phải tắt được cái sau mà vẫn giữ cái trước — gộp lại
 * thì họ chỉ có lựa chọn nhận cả hai hoặc mất cả hai.
 */
final class MentionedNotification extends PreferenceAwareNotification
{
    use QuotesComment;
    use SerializesModels;

    public function __construct(private readonly TaskComment $comment) {}

    public function type(): NotificationType
    {
        return NotificationType::Mentioned;
    }

    public function title(): string
    {
        return 'Bạn được nhắc tên';
    }

    public function message(User $notifiable): string
    {
        // Xem chú thích cùng chỗ ở CommentAddedNotification: `author` nullable
        // thật, nhưng Larastan suy kiểu quan hệ thành non-null.
        $nguoiViet = $this->comment->author->name ?? 'Ai đó';

        return "{$nguoiViet} nhắc tên bạn trong việc “{$this->comment->task->title}”: "
            .$this->trichDan($this->comment->body);
    }

    public function url(): string
    {
        return "/tasks/{$this->comment->task->uuid}";
    }

    /**
     * @return array<string, mixed>
     */
    protected function extra(): array
    {
        return [
            'task_id' => $this->comment->task->uuid,
            'task_title' => $this->comment->task->title,
            'comment_id' => $this->comment->uuid,
            'actor_name' => $this->comment->author?->name,
        ];
    }
}
