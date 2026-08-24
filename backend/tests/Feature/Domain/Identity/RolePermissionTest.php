<?php

declare(strict_types=1);

use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Enums\Role;
use App\Domain\Identity\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

it('tạo đủ bốn vai trò', function (): void {
    expect(Spatie\Permission\Models\Role::query()->pluck('name')->all())
        ->toEqualCanonicalizing(array_column(Role::cases(), 'value'));
});

it('nhân viên chỉ xem được task của mình, không được giao việc cho người khác', function (): void {
    $nhanVien = User::factory()->create();
    $nhanVien->assignRole(Role::NhanVien->value);

    expect($nhanVien->can(Permission::ViewOwnTasks->value))->toBeTrue()
        ->and($nhanVien->can(Permission::CreateTask->value))->toBeTrue()
        ->and($nhanVien->can(Permission::AssignTask->value))->toBeFalse()
        ->and($nhanVien->can(Permission::ViewTeamTasks->value))->toBeFalse()
        ->and($nhanVien->can(Permission::ManageUsers->value))->toBeFalse();
});

it('trưởng phòng giao được việc và xem được task cả phòng', function (): void {
    $truongPhong = User::factory()->create();
    $truongPhong->assignRole(Role::TruongPhong->value);

    expect($truongPhong->can(Permission::AssignTask->value))->toBeTrue()
        ->and($truongPhong->can(Permission::ViewTeamTasks->value))->toBeTrue()
        ->and($truongPhong->can(Permission::ChangeTaskDueDate->value))->toBeTrue()
        // Đổi hạn là quyền của người giao việc, không phải của người làm —
        // xem ràng buộc nghiệp vụ ở mục 1.3.
        ->and($truongPhong->can(Permission::ViewAllTasks->value))->toBeFalse()
        ->and($truongPhong->can(Permission::ManageUsers->value))->toBeFalse();
});

it('giám đốc xem được toàn công ty nhưng không quản trị người dùng', function (): void {
    $giamDoc = User::factory()->create();
    $giamDoc->assignRole(Role::GiamDoc->value);

    expect($giamDoc->can(Permission::ViewAllTasks->value))->toBeTrue()
        ->and($giamDoc->can(Permission::ViewReports->value))->toBeTrue()
        ->and($giamDoc->can(Permission::ManageUsers->value))->toBeFalse();
});

it('quản trị viên có toàn bộ quyền', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole(Role::Admin->value);

    foreach (Permission::cases() as $permission) {
        expect($admin->can($permission->value))->toBeTrue();
    }
});

it('chạy seeder nhiều lần không nhân đôi vai trò hay quyền', function (): void {
    $this->seed(RolePermissionSeeder::class);

    expect(Spatie\Permission\Models\Role::query()->count())->toBe(count(Role::cases()))
        ->and(Spatie\Permission\Models\Permission::query()->count())->toBe(count(Permission::cases()));
});

it('tài khoản quản trị do seeder tạo ra có sẵn vai trò admin', function (): void {
    $this->seed(DatabaseSeeder::class);

    $admin = User::query()->where('employee_code', 'ADMIN')->firstOrFail();

    expect($admin->hasRole(Role::Admin->value))->toBeTrue();
});
