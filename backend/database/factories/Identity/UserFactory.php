<?php

declare(strict_types=1);

namespace Database\Factories\Identity;

use App\Domain\Identity\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
final class UserFactory extends Factory
{
    protected $model = User::class;

    /**
     * Mật khẩu dùng chung cho mọi bản ghi sinh ra trong một lần chạy test —
     * băm mật khẩu là thao tác đắt, băm lại cho từng user làm test chậm hẳn.
     */
    protected static ?string $password = null;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => self::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'department_id' => null,
            'position_id' => null,
            'manager_id' => null,
            'employee_code' => Str::upper(Str::random(8)),
            'phone' => null,
            'joined_at' => now()->subYear()->toDateString(),
            'is_active' => true,
            'terminated_at' => null,
        ];
    }

    public function unverified(): self
    {
        return $this->state(fn (array $attributes): array => [
            'email_verified_at' => null,
        ]);
    }

    /** Nhân viên đã nghỉ việc: giữ nguyên bản ghi, chỉ đánh dấu. */
    public function terminated(): self
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
            'terminated_at' => now(),
        ]);
    }
}
