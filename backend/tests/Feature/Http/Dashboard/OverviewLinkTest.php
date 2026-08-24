<?php

declare(strict_types=1);

use App\Domain\Identity\Enums\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Task\Enums\TaskStatus;
use App\Domain\Task\Models\Task;
use Carbon\CarbonImmutable;
use Database\Seeders\RolePermissionSeeder;

/*
|--------------------------------------------------------------------------
| Con số ở Tổng quan phải khớp với danh sách bấm vào
|--------------------------------------------------------------------------
|
| Trang Tổng quan hiện con số; bấm vào mở trang Công việc đã lọc sẵn. Nếu ô ghi
| "12 việc quá hạn" mà danh sách ra 9 dòng thì người dùng mất niềm tin vào CẢ
| HAI con số, không riêng con số sai.
|
| Bộ test này khoá lại điều đó: với cùng một tập dữ liệu, `summary.X` phải bằng
| `meta.total` của endpoint danh sách kèm bộ lọc tương ứng.
|
| Mốc thời gian 09:00 UTC = 16:00 giờ Việt Nam, thứ Tư 12/08/2026.
|
*/

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->withHeader('Origin', 'http://localhost:3000');
    $this->travelTo(CarbonImmutable::parse('2026-08-12 09:00:00'));
});

function duLieuMau(): User
{
    $admin = quanTri();
    $nv = User::factory()->create();
    $nv->assignRole(Role::NhanVien->value);

    // Quá hạn.
    Task::factory()->count(3)->for($nv, 'assignee')->create([
        'status' => TaskStatus::InProgress->value,
        'due_date' => CarbonImmutable::parse('2026-08-01 03:00:00'),
    ]);

    // Hạn hôm nay theo GIỜ VIỆT NAM: 20:00 ngày 12/08 VN = 13:00 UTC.
    Task::factory()->count(2)->for($nv, 'assignee')->create([
        'status' => TaskStatus::Todo->value,
        'due_date' => CarbonImmutable::parse('2026-08-12 13:00:00'),
    ]);

    // Chưa giao ai.
    Task::factory()->count(4)->create([
        'assignee_id' => null,
        'status' => TaskStatus::Todo->value,
        'due_date' => null,
    ]);

    // Xong tuần này (thứ Hai 10/08 giờ VN = 09/08 17:00 UTC trở đi).
    Task::factory()->count(2)->for($nv, 'assignee')->create([
        'status' => TaskStatus::Done->value,
        'completed_at' => CarbonImmutable::parse('2026-08-11 03:00:00'),
        'due_date' => null,
    ]);

    // Xong TUẦN TRƯỚC — không được đếm.
    Task::factory()->for($nv, 'assignee')->create([
        'status' => TaskStatus::Done->value,
        'completed_at' => CarbonImmutable::parse('2026-08-05 03:00:00'),
        'due_date' => null,
    ]);

    return $admin;
}

/** @return array{int, int} [con số ở Tổng quan, tổng số ở danh sách] */
function soVaDanhSach(User $admin, string $khoaSummary, string $boLoc): array
{
    $so = test()->actingAs($admin)->getJson('/api/v1/dashboard/overview')
        ->assertOk()->json("data.summary.{$khoaSummary}");

    $tong = test()->actingAs($admin)->getJson("/api/v1/tasks?{$boLoc}")
        ->assertOk()->json('meta.total');

    return [(int) $so, (int) $tong];
}

it('việc đang mở khớp với ?open=1', function (): void {
    $admin = duLieuMau();
    [$so, $tong] = soVaDanhSach($admin, 'open_tasks', 'open=1');

    expect($so)->toBe(9)->and($tong)->toBe($so);
});

it('quá hạn khớp với ?overdue=1', function (): void {
    $admin = duLieuMau();
    [$so, $tong] = soVaDanhSach($admin, 'overdue_tasks', 'overdue=1');

    expect($so)->toBe(3)->and($tong)->toBe($so);
});

it('chưa giao ai khớp với ?unassigned=1', function (): void {
    $admin = duLieuMau();
    [$so, $tong] = soVaDanhSach($admin, 'unassigned_tasks', 'unassigned=1');

    expect($so)->toBe(4)->and($tong)->toBe($so);
});

it('hạn hôm nay khớp với ?due_today=1, và tính theo giờ Việt Nam', function (): void {
    /*
    | Đây là chỗ có lỗi thật trước khi làm phần này: Tổng quan dùng
    | `now()->endOfDay()` — cuối ngày theo UTC, vì ứng dụng chạy múi giờ UTC.
    | Lệch bảy tiếng, nên việc tới hạn rạng sáng HÔM SAU giờ Việt Nam bị đếm
    | nhầm vào hôm nay.
    */
    $admin = duLieuMau();

    // 02:00 ngày 13/08 giờ VN = 19:00 ngày 12/08 UTC. Cùng ngày theo UTC,
    // KHÁC ngày theo giờ Việt Nam — nên không được đếm.
    Task::factory()->create([
        'status' => TaskStatus::Todo->value,
        'due_date' => CarbonImmutable::parse('2026-08-12 19:00:00'),
    ]);

    [$so, $tong] = soVaDanhSach($admin, 'due_today', 'due_today=1');

    expect($so)->toBe(2)->and($tong)->toBe($so);
});

it('xong tuần này khớp với ?completed_this_week=1', function (): void {
    $admin = duLieuMau();
    [$so, $tong] = soVaDanhSach($admin, 'completed_this_week', 'completed_this_week=1');

    // Hai việc xong tuần này; việc xong tuần trước không được đếm.
    expect($so)->toBe(2)->and($tong)->toBe($so);
});

it('lọc theo người khớp với cột tải việc ở Tổng quan', function (): void {
    $admin = duLieuMau();

    $hang = collect(
        $this->actingAs($admin)->getJson('/api/v1/dashboard/overview')->json('data.workload.rows'),
    )->first();

    $tong = $this->actingAs($admin)
        ->getJson("/api/v1/tasks?assignee_id={$hang['id']}&open=1")
        ->assertOk()->json('meta.total');

    expect($tong)->toBe($hang['open']);
});
