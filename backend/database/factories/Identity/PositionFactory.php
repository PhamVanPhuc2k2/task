<?php

declare(strict_types=1);

namespace Database\Factories\Identity;

use App\Domain\Identity\Models\Position;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Position>
 */
final class PositionFactory extends Factory
{
    protected $model = Position::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Chức vụ '.fake()->unique()->word(),
            'code' => Str::upper(Str::random(8)),
            'level' => 1,
            'is_active' => true,
        ];
    }
}
