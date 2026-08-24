<?php

declare(strict_types=1);

use App\Domain\Identity\Enums\Role;
use App\Domain\Identity\Models\Department;
use App\Domain\Identity\Models\User;
use App\Domain\Task\Models\Task;
use Database\Seeders\RolePermissionSeeder;

/*
|--------------------------------------------------------------------------
| Ai xem được task nào
|--------------------------------------------------------------------------
|
| Đây là phần quan trọng nhất của API Task và cũng dễ sai nhất. Lộ task của
| phòng khác cho nhân viên thường là rò rỉ thông tin nội bộ — lương thưởng,
| khách hàng, kế hoạch đều nằm trong mô tả công việc.
|
| Ba mức, theo quyền trong Permission enum:
|   task.view.own   — task mình làm, mình giao, mình tạo, hoặc mình theo dõi
|   task.view.team  — thêm task của phòng mình VÀ mọi phòng trực thuộc
|   task.view.all   — toàn công ty
|
*/

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->withHeader('Origin', 'http://localhost:3000');
});

/**
 * Dựng sẵn cơ cấu: Công ty > Khối Kinh doanh > Phòng Sale, và Phòng Kế toán.
 *
 * @return array{
 *     nhanVienSale: User, nhanVienKhac: User,
 *     truongKhoi: User, giamDoc: User,
 *     taskSale: Task, taskKeToan: Task,
 * }
 */
function coCauVaTask(): array
{
    $congTy = Department::factory()->create(['name' => 'Công ty']);
    $khoi = Department::factory()->for($congTy, 'parent')->create(['name' => 'Khối Kinh doanh']);
    $sale = Department::factory()->for($khoi, 'parent')->create(['name' => 'Phòng Sale']);
    $keToan = Department::factory()->for($congTy, 'parent')->create(['name' => 'Phòng Kế toán']);

    $nhanVienSale = User::factory()->for($sale, 'department')->create();
    $nhanVienKeToan = User::factory()->for($keToan, 'department')->create();

    $truongKhoi = User::factory()->for($khoi, 'department')->create();
    $truongKhoi->assignRole(Role::TruongPhong->value);

    $giamDoc = User::factory()->for($congTy, 'department')->create();
    $giamDoc->assignRole(Role::GiamDoc->value);

    $nhanVienSale->assignRole(Role::NhanVien->value);
    $nhanVienKeToan->assignRole(Role::NhanVien->value);

    return [
        'nhanVienSale' => $nhanVienSale,
        'nhanVienKhac' => $nhanVienKeToan,
        'truongKhoi' => $truongKhoi,
        'giamDoc' => $giamDoc,
        'taskSale' => Task::factory()->for($nhanVienSale, 'assignee')->create(['title' => 'Việc của Sale']),
        'taskKeToan' => Task::factory()->for($nhanVienKeToan, 'assignee')->create(['title' => 'Việc của Kế toán']),
    ];
}

/** @return list<string> Tiêu đề các task nhìn thấy trong danh sách. */
function tieuDeThayDuoc(User $user): array
{
    return test()->actingAs($user)
        ->getJson('/api/v1/tasks')
        ->assertOk()
        ->json('data.*.title');
}

it('nhân viên chỉ thấy task của chính mình', function (): void {
    $t = coCauVaTask();

    expect(tieuDeThayDuoc($t['nhanVienSale']))->toBe(['Việc của Sale']);
});

it('nhân viên không thấy task của phòng khác', function (): void {
    $t = coCauVaTask();

    expect(tieuDeThayDuoc($t['nhanVienKhac']))->not->toContain('Việc của Sale');
});

it('nhân viên thấy task mình được giao dù không phải người tạo', function (): void {
    $t = coCauVaTask();
    $sep = User::factory()->create();

    Task::factory()
        ->for($t['nhanVienSale'], 'assignee')
        ->for($sep, 'assigner')
        ->create(['title' => 'Sếp giao thêm']);

    expect(tieuDeThayDuoc($t['nhanVienSale']))->toContain('Sếp giao thêm');
});

it('nhân viên thấy task mình đang theo dõi', function (): void {
    // Người theo dõi nhận thông báo về task, nên phải mở được task đó.
    $t = coCauVaTask();

    $task = Task::factory()->create(['title' => 'Task tôi theo dõi']);
    $task->watchers()->attach($t['nhanVienSale']->id);

    expect(tieuDeThayDuoc($t['nhanVienSale']))->toContain('Task tôi theo dõi');
});

it('trưởng phòng thấy task của mọi phòng trực thuộc, ở mọi độ sâu', function (): void {
    // Trưởng Khối Kinh doanh phải thấy task của Phòng Sale nằm dưới hai cấp.
    $t = coCauVaTask();

    expect(tieuDeThayDuoc($t['truongKhoi']))->toContain('Việc của Sale');
});

it('trưởng phòng không thấy task của nhánh khác trong cây', function (): void {
    $t = coCauVaTask();

    expect(tieuDeThayDuoc($t['truongKhoi']))->not->toContain('Việc của Kế toán');
});

it('giám đốc thấy task toàn công ty', function (): void {
    $t = coCauVaTask();

    expect(tieuDeThayDuoc($t['giamDoc']))
        ->toContain('Việc của Sale')
        ->toContain('Việc của Kế toán');
});

it('không cho xem chi tiết task ngoài phạm vi', function (): void {
    // Đổi uuid trên URL sang task của người khác phải bị chặn (IDOR).
    $t = coCauVaTask();

    $this->actingAs($t['nhanVienSale'])
        ->getJson("/api/v1/tasks/{$t['taskKeToan']->uuid}")
        ->assertStatus(403)
        ->assertJsonPath('code', 'FORBIDDEN');
});

it('cho xem chi tiết task trong phạm vi', function (): void {
    $t = coCauVaTask();

    $this->actingAs($t['nhanVienSale'])
        ->getJson("/api/v1/tasks/{$t['taskSale']->uuid}")
        ->assertOk()
        ->assertJsonPath('data.title', 'Việc của Sale');
});

it('từ chối khi chưa đăng nhập', function (): void {
    coCauVaTask();

    $this->getJson('/api/v1/tasks')
        ->assertStatus(401)
        ->assertJsonPath('code', 'UNAUTHENTICATED');
});
