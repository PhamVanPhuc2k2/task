<?php

declare(strict_types=1);

namespace Database\Factories\Identity;

use App\Domain\Identity\Models\Department;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Department>
 */
final class DepartmentFactory extends Factory
{
    protected $model = Department::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'parent_id' => null,
            'name' => 'Phòng '.fake()->unique()->word(),
            'code' => Str::upper(Str::random(8)),
            'description' => null,
            'is_active' => true,
        ];
    }

    public function inactive(): self
    {
        return $this->state(fn (array $attributes): array => ['is_active' => false]);
    }
}
