<?php

declare(strict_types=1);

namespace App\Domain\Task\Notifications;

use App\Domain\Identity\Enums\NotificationType;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Notifications\PreferenceAwareNotification;
use App\Domain\Task\Models\TaskComment;
use Illuminate\Queue\SerializesModels;

final class CommentAddedNotification extends PreferenceAwareNotification
{
    use QuotesComment;
    use SerializesModels;

    public function __construct(private readonly TaskComment $comment) {}

    public function type(): NotificationType
    {
        return NotificationType::CommentAdded;
    }

    public function title(): string
    {
        return 'Có bình luận mới';
    }

    public function message(User $notifiable): string
    {
        // `author` nullable thật ở database: người viết nghỉ việc thì `user_id`
        // về null, bình luận vẫn còn. Larastan suy kiểu quan hệ belongsTo
        // thành non-null nên coi `?->` là thừa; dùng `??` thay thế — toán tử
        // này không báo lỗi khi vế trái là null.
        $nguoiViet = $this->comment->author->name ?? 'Ai đó';

        return "{$nguoiViet} vừa bình luận trên việc “{$this->comment->task->title}”: "
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
