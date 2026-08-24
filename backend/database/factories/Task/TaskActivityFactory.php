<?php

declare(strict_types=1);

namespace Database\Factories\Task;

use App\Domain\Identity\Models\User;
use App\Domain\Task\Models\Task;
use App\Domain\Task\Models\TaskActivity;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TaskActivity>
 */
final class TaskActivityFactory extends Factory
{
    protected $model = TaskActivity::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'task_id' => Task::factory(),
            'causer_id' => User::factory(),
            'event' => 'updated',
            'old_values' => null,
            'new_values' => null,
        ];
    }
}
