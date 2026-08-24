<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Identity\Enums\Role;
use App\Domain\Identity\Models\Department;
use App\Domain\Identity\Models\Position;
use App\Domain\Identity\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Tài khoản quản trị đầu tiên — để đăng nhập được lần đầu sau khi cài đặt.
 *
 * Mật khẩu lấy từ biến môi trường ADMIN_PASSWORD. Không đặt mật khẩu mặc định
 * trong mã nguồn: seeder này chạy cả trên production, và một mật khẩu mặc định
 * nằm trong repository là mật khẩu ai cũng biết.
 *
 * Không khai báo ADMIN_PASSWORD thì seeder sinh mật khẩu ngẫu nhiên và in ra
 * màn hình đúng một lần.
 */
final class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $email = (string) config('admin.email');

        if (User::query()->where('email', $email)->exists()) {
            $this->command->warn(sprintf('Tài khoản %s đã tồn tại, bỏ qua.', $email));

            return;
        }

        $password = (string) config('admin.password');
        $generated = $password === '';

        if ($generated) {
            $password = Str::password(16);
        }

        $admin = User::query()->create([
            'name' => (string) config('admin.name'),
            'email' => $email,
            'password' => Hash::make($password),
            'email_verified_at' => now(),
            'employee_code' => 'ADMIN',
            'department_id' => Department::query()->where('code', 'CTY')->value('id'),
            'position_id' => Position::query()->where('code', 'GD')->value('id'),
            'joined_at' => now()->toDateString(),
            'is_active' => true,
        ]);

        $admin->assignRole(Role::Admin->value);

        $this->command->info(sprintf('Đã tạo tài khoản quản trị: %s', $email));

        if ($generated) {
            $this->command->warn('Mật khẩu sinh tự động (chỉ hiện một lần): '.$password);
            $this->command->warn('Hãy đăng nhập và đổi mật khẩu ngay.');
        }
    }
}
