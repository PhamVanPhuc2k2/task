<?php

declare(strict_types=1);

namespace Database\Factories\Task;

use App\Domain\Identity\Models\User;
use App\Domain\Task\Models\Task;
use App\Domain\Task\Models\TaskComment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TaskComment>
 */
final class TaskCommentFactory extends Factory
{
    protected $model = TaskComment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'task_id' => Task::factory(),
            'parent_id' => null,
            'user_id' => User::factory(),
            'body' => fake()->paragraph(),
            'edited_at' => null,
        ];
    }
}
