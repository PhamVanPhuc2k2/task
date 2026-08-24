<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Chỉ chứa dữ liệu nền cần cho hệ thống chạy được: cơ cấu tổ chức và tài khoản
 * quản trị đầu tiên. An toàn để chạy trên production lúc go-live.
 *
 * Dữ liệu giả để dev và demo nằm ở DemoDataSeeder, chạy riêng:
 *     php artisan db:seed --class=DemoDataSeeder
 *
 * KHÔNG dùng trait WithoutModelEvents ở đây, dù đó là mặc định của Laravel.
 * Trait đó tắt toàn bộ sự kiện model, trong khi `HasUuid` sinh uuid qua sự kiện
 * `creating`. Tắt đi thì cột uuid rỗng và mọi insert chết với lỗi NOT NULL —
 * và toàn bộ test model vẫn xanh, vì factory không đi qua seeder.
 *
 * Hệ quả cần nhớ: khi thêm Observer có tác dụng phụ ra bên ngoài (gửi thông
 * báo, gọi API), Observer đó phải tự bỏ qua khi đang chạy seeder, chứ không
 * giải quyết bằng cách tắt sự kiện ở đây.
 */
final class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            OrganizationSeeder::class,
            RolePermissionSeeder::class,
            AdminUserSeeder::class,
        ]);
    }
}
