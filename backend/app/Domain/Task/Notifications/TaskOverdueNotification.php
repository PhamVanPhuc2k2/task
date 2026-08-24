<?php

declare(strict_types=1);

namespace App\Domain\Task\Notifications;

use App\Domain\Identity\Enums\NotificationType;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Notifications\PreferenceAwareNotification;
use App\Domain\Task\Models\Task;
use Illuminate\Queue\SerializesModels;

final class TaskOverdueNotification extends PreferenceAwareNotification
{
    use SerializesModels;

    public function __construct(private readonly Task $task) {}

    public function type(): NotificationType
    {
        return NotificationType::TaskOverdue;
    }

    public function title(): string
    {
        return 'Việc đã quá hạn';
    }

    public function message(User $notifiable): string
    {
        $han = $this->task->due_date?->timezone(config()->string('app.display_timezone'));

        $khi = $han === null ? '' : ' Hạn là '.$han->format('H:i d/m/Y').'.';

        return "Việc “{$this->task->title}” đã qua hạn mà chưa đóng.{$khi}";
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
