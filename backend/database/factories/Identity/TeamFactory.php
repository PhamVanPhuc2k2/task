<?php

declare(strict_types=1);

namespace Database\Factories\Identity;

use App\Domain\Identity\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Team>
 */
final class TeamFactory extends Factory
{
    protected $model = Team::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Đội '.fake()->unique()->word(),
            'code' => Str::upper(Str::random(8)),
            'description' => null,
            'leader_id' => null,
            'is_active' => true,
        ];
    }
}
