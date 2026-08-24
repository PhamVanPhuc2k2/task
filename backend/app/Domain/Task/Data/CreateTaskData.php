<?php

declare(strict_types=1);

namespace App\Domain\Task\Data;

use App\Domain\Task\Enums\TaskPriority;
use App\Domain\Task\Enums\TaskStatus;

final readonly class CreateTaskData
{
    public function __construct(
        public string $title,
        public ?string $description = null,
        public ?int $projectId = null,
        public ?int $parentTaskId = null,
        public ?int $assigneeId = null,
        public ?int $reviewerId = null,
        public TaskStatus $status = TaskStatus::Todo,
        public TaskPriority $priority = TaskPriority::Normal,
        public ?string $dueDate = null,
        public ?string $estimateHours = null,
    ) {}
}
