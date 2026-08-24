<?php

declare(strict_types=1);

use App\Domain\Identity\Models\Department;
use App\Domain\Identity\Models\Position;
use App\Domain\Identity\Models\User;
use Database\Seeders\DatabaseSeeder;

it('tạo được cơ cấu tổ chức và tài khoản quản trị', function (): void {
    $this->seed(DatabaseSeeder::class);

    expect(Position::query()->count())->toBe(4)
        ->and(Department::query()->count())->toBe(6)
        ->and(User::query()->where('employee_code', 'ADMIN')->exists())->toBeTrue();
});

it('sinh uuid cho mọi bản ghi mà seeder tạo ra', function (): void {
    // Test này tồn tại vì một bug thật: DatabaseSeeder ban đầu dùng
    // WithoutModelEvents, trong khi HasUuid sinh uuid qua sự kiện `creating`.
    // Tắt sự kiện đi thì uuid rỗng và insert chết với lỗi NOT NULL — nhưng
    // toàn bộ test model vẫn xanh, vì factory không đi qua seeder.
    $this->seed(DatabaseSeeder::class);

    expect(Department::query()->whereNull('uuid')->count())->toBe(0)
        ->and(Position::query()->whereNull('uuid')->count())->toBe(0)
        ->and(User::query()->whereNull('uuid')->count())->toBe(0);
});

it('dựng đúng cây phòng ban với công ty ở gốc', function (): void {
    $this->seed(DatabaseSeeder::class);

    $congTy = Department::query()->where('code', 'CTY')->firstOrFail();

    expect($congTy->parent_id)->toBeNull()
        ->and($congTy->descendantIds())->toHaveCount(5);
});

it('chạy seeder nhiều lần không tạo bản ghi trùng', function (): void {
    // Seeder này chạy trên production lúc go-live. Chạy nhầm lần hai không được
    // phép nhân đôi cơ cấu tổ chức.
    $this->seed(DatabaseSeeder::class);
    $this->seed(DatabaseSeeder::class);

    expect(Position::query()->count())->toBe(4)
        ->and(Department::query()->count())->toBe(6)
        ->and(User::query()->where('employee_code', 'ADMIN')->count())->toBe(1);
});
