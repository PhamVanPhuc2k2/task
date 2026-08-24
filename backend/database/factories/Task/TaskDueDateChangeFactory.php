<?php

declare(strict_types=1);

namespace Database\Factories\Task;

use App\Domain\Identity\Models\User;
use App\Domain\Task\Models\Task;
use App\Domain\Task\Models\TaskDueDateChange;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TaskDueDateChange>
 */
final class TaskDueDateChangeFactory extends Factory
{
    protected $model = TaskDueDateChange::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'task_id' => Task::factory(),
            'old_due_date' => now()->addDays(3),
            'new_due_date' => now()->addDays(10),
            'reason' => fake()->sentence(),
            'requested_by' => User::factory(),
            'approved_by' => null,
            'approved_at' => null,
        ];
    }
}
