<?php

declare(strict_types=1);

namespace Database\Factories\Task;

use App\Domain\Task\Enums\ProjectStatus;
use App\Domain\Task\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Project>
 */
final class ProjectFactory extends Factory
{
    protected $model = Project::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Dự án '.fake()->unique()->word(),
            'code' => Str::upper(Str::random(8)),
            'description' => null,
            'owner_id' => null,
            'department_id' => null,
            'status' => ProjectStatus::Planning,
            'start_date' => null,
            'end_date' => null,
            'created_by' => null,
            'updated_by' => null,
        ];
    }

    public function active(): self
    {
        return $this->state(fn (): array => ['status' => ProjectStatus::Active]);
    }
}
