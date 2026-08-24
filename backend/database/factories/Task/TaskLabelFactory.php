<?php

declare(strict_types=1);

namespace Database\Factories\Task;

use App\Domain\Task\Models\TaskLabel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TaskLabel>
 */
final class TaskLabelFactory extends Factory
{
    protected $model = TaskLabel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Nhãn '.fake()->unique()->word(),
            'color' => fake()->hexColor(),
        ];
    }
}
