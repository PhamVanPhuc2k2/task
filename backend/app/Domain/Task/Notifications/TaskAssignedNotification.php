<?php

declare(strict_types=1);

namespace App\Domain\Task\Notifications;

use App\Domain\Identity\Enums\NotificationType;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Notifications\PreferenceAwareNotification;
use App\Domain\Task\Models\Task;
use Illuminate\Queue\SerializesModels;

final class TaskAssignedNotification extends PreferenceAwareNotification
{
    use SerializesModels;

    public function __construct(
        private readonly Task $task,
        private readonly string $nguoiGiao,
    ) {}

    public function type(): NotificationType
    {
        return NotificationType::TaskAssigned;
    }

    public function title(): string
    {
        return 'Bạn được giao một việc mới';
    }

    public function message(User $notifiable): string
    {
        $han = $this->task->due_date === null
            ? 'chưa đặt hạn'
            : 'hạn '.$this->task->due_date->timezone(config()->string('app.display_timezone'))->format('d/m/Y H:i');

        return "{$this->nguoiGiao} giao cho bạn việc “{$this->task->title}” ({$han}).";
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
            'actor_name' => $this->nguoiGiao,
        ];
    }
}
