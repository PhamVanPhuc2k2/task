<?php

declare(strict_types=1);

use App\Domain\Identity\IdentityServiceProvider;
use App\Domain\Leave\LeaveServiceProvider;
use App\Domain\Task\TaskServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\HorizonServiceProvider;
use App\Providers\SettingsServiceProvider;

return [
    AppServiceProvider::class,
    HorizonServiceProvider::class,

    // Mỗi miền nghiệp vụ có ServiceProvider riêng.
    // Thêm miền mới = thêm một thư mục trong app/Domain và một dòng ở đây.
    // Cài đặt trang nạp TRƯỚC các miền: nó ghi đè config mà những miền kia
    // đọc, nên nạp sau là các miền đã lấy giá trị cũ.
    SettingsServiceProvider::class,

    IdentityServiceProvider::class,
    TaskServiceProvider::class,
    LeaveServiceProvider::class,
];
