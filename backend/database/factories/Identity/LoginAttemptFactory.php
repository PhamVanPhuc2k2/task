<?php

declare(strict_types=1);

namespace Database\Factories\Identity;

use App\Domain\Identity\Models\LoginAttempt;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LoginAttempt>
 */
final class LoginAttemptFactory extends Factory
{
    protected $model = LoginAttempt::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => null,
            'email' => fake()->safeEmail(),
            'successful' => true,
            'failure_reason' => null,
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
        ];
    }
}
