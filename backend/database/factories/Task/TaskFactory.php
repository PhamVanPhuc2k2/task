<?php

declare(strict_types=1);

namespace Database\Factories\Task;

use App\Domain\Task\Enums\TaskPriority;
use App\Domain\Task\Enums\TaskStatus;
use App\Domain\Task\Models\Task;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Task>
 */
final class TaskFactory extends Factory
{
    protected $model = Task::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => null,
            'parent_task_id' => null,
            'title' => fake()->sentence(4),
            'description' => fake()->optional()->paragraph(),
            'assignee_id' => null,
            'assigner_id' => null,
            'reviewer_id' => null,
            'status' => TaskStatus::Todo,
            'priority' => TaskPriority::Normal,
            'due_date' => now()->addWeek(),
            'started_at' => null,
            'completed_at' => null,
            'estimate_hours' => null,
            'progress_percent' => 0,
            'created_by' => null,
            'updated_by' => null,
        ];
    }

    public function overdue(): self
    {
        return $this->state(fn (): array => [
            'due_date' => now()->subDays(3),
            'status' => TaskStatus::InProgress,
        ]);
    }

    public function done(): self
    {
        return $this->state(fn (): array => [
            'status' => TaskStatus::Done,
            'completed_at' => now(),
            'progress_percent' => 100,
        ]);
    }
}
