<?php

declare(strict_types=1);

namespace App\Domain\Task\Notifications;

use App\Domain\Identity\Enums\NotificationType;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Notifications\PreferenceAwareNotification;
use App\Domain\Task\Models\Task;
use Illuminate\Queue\SerializesModels;

final class TaskDueSoonNotification extends PreferenceAwareNotification
{
    use SerializesModels;

    public function __construct(private readonly Task $task) {}

    public function type(): NotificationType
    {
        return NotificationType::TaskDueSoon;
    }

    public function title(): string
    {
        return 'Việc sắp tới hạn';
    }

    public function message(User $notifiable): string
    {
        // Ghép chuỗi thay vì nhét chữ tiếng Việt vào format(): mọi ký tự chữ
        // trong chuỗi format đều phải escape, và dấu tiếng Việt làm việc đó
        // thành một bãi mìn.
        $han = $this->task->due_date?->timezone(config()->string('app.display_timezone'));

        $khi = $han === null
            ? 'sắp tới hạn'
            : 'tới hạn lúc '.$han->format('H:i').' ngày '.$han->format('d/m');

        return "Việc “{$this->task->title}” {$khi}.";
    }

    public function url(): string
    {
        return "/tasks/{$this->task->uuid}";
    }

    /**
     * @return array<string, mixed>
     */
    protected function extra(): array
    {
        return [
            'task_id' => $this->task->uuid,
            'task_title' => $this->task->title,
            'due_date' => $this->task->due_date?->toIso8601String(),
        ];
    }
}
