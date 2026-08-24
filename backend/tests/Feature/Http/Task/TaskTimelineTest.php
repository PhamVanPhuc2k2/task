<?php

declare(strict_types=1);

use App\Domain\Identity\Enums\Role;
use App\Domain\Identity\Models\Department;
use App\Domain\Identity\Models\User;
use App\Domain\Task\Enums\TaskStatus;
use App\Domain\Task\Models\Task;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->withHeader('Origin', 'http://localhost:3000');
});

/*
|--------------------------------------------------------------------------
| Dòng thời gian hoạt động
|--------------------------------------------------------------------------
*/

it('trả về nhật ký của task, mới nhất lên đầu', function (): void {
    // Người mở trang quan tâm "vừa có gì thay đổi", không phải task được tạo ra
    // thế nào từ ba tuần trước.
    [, $nhanVien] = sepVaNhanVien();
    $task = Task::factory()->for($nhanVien, 'assignee')->create(['status' => TaskStatus::Todo]);

    $this->actingAs($nhanVien)->patchJson("/api/v1/tasks/{$task->uuid}/status", [
        'status' => TaskStatus::InProgress->value,
    ])->assertOk();

    $moc = $this->actingAs($nhanVien)
        ->getJson("/api/v1/tasks/{$task->uuid}/activities")
        ->assertOk()
        ->json('data');

    expect($moc)->toHaveCount(2)
        ->and($moc[0]['event'])->toBe('updated')
        ->and($moc[0]['new_values']['status'])->toBe('in_progress')
        ->and($moc[0]['causer']['name'])->toBe($nhanVien->name)
        ->and($moc[1]['event'])->toBe('created');
});

it('không cho xem nhật ký của task ngoài phạm vi', function (): void {
    // Nhật ký lộ ai giao việc cho ai và đã dời hạn mấy lần — cùng mức nhạy cảm
    // với chính task đó, nên cùng một luật quyền.
    $nguoiNgoai = User::factory()->for(Department::factory(), 'department')->create();
    $nguoiNgoai->assignRole(Role::NhanVien->value);

    $task = Task::factory()->create();

    $this->actingAs($nguoiNgoai)
        ->getJson("/api/v1/tasks/{$task->uuid}/activities")
        ->assertStatus(403);
});

/*
|--------------------------------------------------------------------------
| Danh bạ để chọn người
|--------------------------------------------------------------------------
*/

it('trưởng phòng lấy được danh bạ dù không có quyền quản trị người dùng', function (): void {
    // Đây là lý do endpoint này tồn tại: GET /users đòi quyền user.manage, mà
    // trưởng phòng không có — không tách ra thì họ không mở nổi ô chọn người
    // thực hiện.
    [$sep, $nhanVien] = sepVaNhanVien();

    expect($sep->can('user.manage'))->toBeFalse();

    $danhBa = $this->actingAs($sep)->getJson('/api/v1/users/assignable')
        ->assertOk()
        ->json('data.*.name');

    expect($danhBa)->toContain($nhanVien->name);
});

it('danh bạ không lộ vai trò và quyền của người khác', function (): void {
    [$sep] = sepVaNhanVien();

    $dong = $this->actingAs($sep)->getJson('/api/v1/users/assignable')->json('data.0');

    expect(array_keys($dong))
        ->toEqualCanonicalizing(['id', 'name', 'email', 'employee_code', 'department']);
});

it('người không xem được task toàn công ty chỉ thấy danh bạ phòng mình', function (): void {
    [$sep] = sepVaNhanVien();
    $phongKhac = User::factory()->for(Department::factory(), 'department')->create();

    $danhBa = $this->actingAs($sep)->getJson('/api/v1/users/assignable')->json('data.*.name');

    expect($danhBa)->not->toContain($phongKhac->name);
});

it('danh bạ bỏ qua tài khoản đã vô hiệu hoá', function (): void {
    // Giao việc cho người đã nghỉ là task treo ngay từ lúc tạo.
    [$sep] = sepVaNhanVien();

    $daNghi = User::factory()->create([
        'department_id' => $sep->department_id,
        'is_active' => false,
    ]);

    $danhBa = $this->actingAs($sep)->getJson('/api/v1/users/assignable')->json('data.*.name');

    expect($danhBa)->not->toContain($daNghi->name);
});

it('tìm người trong danh bạ theo tên', function (): void {
    [$sep] = sepVaNhanVien();

    User::factory()->create([
        'name' => 'Nguyễn Văn Tìm Được',
        'department_id' => $sep->department_id,
    ]);

    $danhBa = $this->actingAs($sep)
        ->getJson('/api/v1/users/assignable?search=Tìm Được')
        ->json('data.*.name');

    expect($danhBa)->toBe(['Nguyễn Văn Tìm Được']);
});
