<?php

declare(strict_types=1);

use App\Domain\Identity\Models\Department;
use App\Domain\Identity\Models\Position;
use App\Domain\Identity\Models\User;

it('gán được phòng ban, chức vụ và quản lý trực tiếp cho nhân viên', function (): void {
    $phong = Department::factory()->create(['name' => 'Phòng Sale']);
    $chucVu = Position::factory()->create(['name' => 'Nhân viên kinh doanh']);
    $truongPhong = User::factory()->create(['name' => 'Trưởng phòng']);

    $nhanVien = User::factory()
        ->for($phong, 'department')
        ->for($chucVu, 'position')
        ->for($truongPhong, 'manager')
        ->create();

    expect($nhanVien->department?->name)->toBe('Phòng Sale')
        ->and($nhanVien->position?->name)->toBe('Nhân viên kinh doanh')
        ->and($nhanVien->manager?->name)->toBe('Trưởng phòng')
        ->and($truongPhong->subordinates->pluck('id')->all())->toBe([$nhanVien->id]);
});

it('mặc định nhân viên mới là đang làm việc', function (): void {
    expect(User::factory()->create()->is_active)->toBeTrue();
});

it('loại nhân viên đã nghỉ việc khỏi danh sách đang làm việc', function (): void {
    // Nhân viên nghỉ việc KHÔNG bị xoá — task họ từng làm phải còn nguyên vết.
    // Chỉ bị loại khỏi các truy vấn "ai đang làm việc".
    User::factory()->count(2)->create();
    $daNghi = User::factory()->terminated()->create();

    $dangLam = User::query()->active()->pluck('id')->all();

    expect($dangLam)->toHaveCount(2)
        ->and($dangLam)->not->toContain($daNghi->id)
        ->and(User::query()->count())->toBe(3)
        ->and($daNghi->terminated_at)->not->toBeNull();
});

it('lộ ra uuid thay vì id tuần tự', function (): void {
    expect(User::factory()->create()->uuid)->toBeString()->toHaveLength(36);
});

it('sắp xếp chức vụ theo cấp bậc', function (): void {
    Position::factory()->create(['name' => 'Nhân viên', 'level' => 1]);
    Position::factory()->create(['name' => 'Giám đốc', 'level' => 4]);
    Position::factory()->create(['name' => 'Trưởng phòng', 'level' => 3]);

    expect(Position::query()->orderByDesc('level')->pluck('name')->all())
        ->toBe(['Giám đốc', 'Trưởng phòng', 'Nhân viên']);
});
