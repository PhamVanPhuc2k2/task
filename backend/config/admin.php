<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Tài khoản quản trị đầu tiên
    |--------------------------------------------------------------------------
    |
    | Dùng bởi AdminUserSeeder để tạo tài khoản đăng nhập được ngay sau khi cài
    | đặt. Khai báo ở đây chứ không gọi thẳng env() trong seeder: trên production
    | config bị cache, và khi đó env() trả về null.
    |
    | Để trống 'password' thì seeder sinh mật khẩu ngẫu nhiên và in ra một lần.
    |
    */

    'name' => env('ADMIN_NAME', 'Quản trị hệ thống'),
    'email' => env('ADMIN_EMAIL', 'admin@example.com'),
    'password' => env('ADMIN_PASSWORD', ''),

];
