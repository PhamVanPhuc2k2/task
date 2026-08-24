<?php

declare(strict_types=1);

use App\Domain\Identity\Enums\Role;
use App\Domain\Identity\Models\Department;
use App\Domain\Identity\Models\User;
use App\Domain\Task\Enums\TaskPriority;
use App\Domain\Task\Enums\TaskStatus;
use App\Domain\Task\Models\Task;
use App\Domain\Task\Models\TaskDueDateChange;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->withHeader('Origin', 'http://localhost:3000');
});

function truongPhong(): User
{
    $phong = Department::factory()->create();
    $user = User::factory()->for($phong, 'department')->create();
    $user->assignRole(Role::TruongPhong->value);

    return $user;
}

function nhanVienCua(User $sep): User
{
    $user = User::factory()->create(['department_id' => $sep->department_id]);
    $user->assignRole(Role::NhanVien->value);

    return $user;
}

/*
|--------------------------------------------------------------------------
| Tạo và sửa
|--------------------------------------------------------------------------
*/

it('tạo task và giao cho nhân viên', function (): void {
    $sep = truongPhong();
    $nhanVien = nhanVienCua($sep);

    $this->actingAs($sep)->postJson('/api/v1/tasks', [
        'title' => 'Gọi lại khách hàng A',
        'description' => 'Khách hỏi báo giá tuần trước',
        'assignee_id' => $nhanVien->uuid,
        'priority' => TaskPriority::High->value,
        'due_date' => now()->addDays(3)->toIso8601String(),
    ])
        ->assertCreated()
        ->assertJsonPath('data.title', 'Gọi lại khách hàng A')
        ->assertJsonPath('data.priority.value', 'high')
        ->assertJsonPath('data.status.value', 'todo');

    $task = Task::query()->where('title', 'Gọi lại khách hàng A')->firstOrFail();

    expect($task->assignee_id)->toBe($nhanVien->id)
        // Người bấm nút là người giao việc và người tạo — không cần gửi lên.
        ->and($task->assigner_id)->toBe($sep->id)
        ->and($task->created_by)->toBe($sep->id);
});

it('từ chối tạo task không có tiêu đề', function (): void {
    $this->actingAs(truongPhong())->postJson('/api/v1/tasks', ['title' => ''])
        ->assertStatus(422)
        ->assertJsonPath('code', 'VALIDATION_FAILED')
        ->assertJsonStructure(['errors' => ['title']]);
});

it('nhân viên thường không được giao việc cho người khác', function (): void {
    $sep = truongPhong();
    $nhanVien = nhanVienCua($sep);
    $nguoiKhac = nhanVienCua($sep);

    $this->actingAs($nhanVien)->postJson('/api/v1/tasks', [
        'title' => 'Việc tự tạo',
        'assignee_id' => $nguoiKhac->uuid,
    ])->assertStatus(403);
});

it('sửa được tiêu đề và mô tả', function (): void {
    $sep = truongPhong();
    $task = Task::factory()->for($sep, 'assigner')->create(['title' => 'Tên cũ']);

    $this->actingAs($sep)->patchJson("/api/v1/tasks/{$task->uuid}", [
        'title' => 'Tên mới',
        'progress_percent' => 40,
    ])->assertOk()->assertJsonPath('data.title', 'Tên mới');

    expect($task->refresh()->progress_percent)->toBe(40);
});

it('xoá mềm chứ không xoá cứng', function (): void {
    $sep = truongPhong();
    $task = Task::factory()->for($sep, 'assigner')->create();

    $this->actingAs($sep)->deleteJson("/api/v1/tasks/{$task->uuid}")->assertNoContent();

    expect(Task::query()->count())->toBe(0)
        ->and(Task::withTrashed()->count())->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Đổi trạng thái
|--------------------------------------------------------------------------
*/

it('người làm tự đổi được trạng thái task của mình', function (): void {
    $sep = truongPhong();
    $nhanVien = nhanVienCua($sep);
    $task = Task::factory()->for($nhanVien, 'assignee')->create(['status' => TaskStatus::Todo]);

    $this->actingAs($nhanVien)
        ->patchJson("/api/v1/tasks/{$task->uuid}/status", ['status' => TaskStatus::InProgress->value])
        ->assertOk()
        ->assertJsonPath('data.status.value', 'in_progress');
});

it('không cho nhảy thẳng từ chưa bắt đầu sang hoàn thành', function (): void {
    // Luồng hợp lệ nằm ở TaskStatus::allowedTransitions(). Ràng buộc này phải
    // được áp ở API chứ không chỉ ở test đơn vị của enum.
    $sep = truongPhong();
    $nhanVien = nhanVienCua($sep);
    $task = Task::factory()->for($nhanVien, 'assignee')->create(['status' => TaskStatus::Todo]);

    $this->actingAs($nhanVien)
        ->patchJson("/api/v1/tasks/{$task->uuid}/status", ['status' => TaskStatus::Done->value])
        ->assertStatus(422)
        ->assertJsonPath('code', 'INVALID_STATUS_TRANSITION');

    expect($task->refresh()->status)->toBe(TaskStatus::Todo);
});

it('ghi mốc thời gian khi bắt đầu và khi hoàn thành', function (): void {
    $sep = truongPhong();
    $task = Task::factory()->for($sep, 'assignee')->create(['status' => TaskStatus::Todo]);

    $this->actingAs($sep)->patchJson("/api/v1/tasks/{$task->uuid}/status", [
        'status' => TaskStatus::InProgress->value,
    ])->assertOk();

    expect($task->refresh()->started_at)->not->toBeNull();

    $this->actingAs($sep)->patchJson("/api/v1/tasks/{$task->uuid}/status", [
        'status' => TaskStatus::Review->value,
    ])->assertOk();
    $this->actingAs($sep)->patchJson("/api/v1/tasks/{$task->uuid}/status", [
        'status' => TaskStatus::Done->value,
    ])->assertOk();

    expect($task->refresh()->completed_at)->not->toBeNull()
        ->and($task->progress_percent)->toBe(100);
});

/*
|--------------------------------------------------------------------------
| Giao lại
|--------------------------------------------------------------------------
*/

it('trưởng phòng giao lại task cho người khác', function (): void {
    $sep = truongPhong();
    $nguoiCu = nhanVienCua($sep);
    $nguoiMoi = nhanVienCua($sep);
    $task = Task::factory()->for($nguoiCu, 'assignee')->create();

    $this->actingAs($sep)
        ->patchJson("/api/v1/tasks/{$task->uuid}/assign", ['assignee_id' => $nguoiMoi->uuid])
        ->assertOk();

    expect($task->refresh()->assignee_id)->toBe($nguoiMoi->id);
});

it('nhân viên không được giao lại task cho người khác', function (): void {
    $sep = truongPhong();
    $nhanVien = nhanVienCua($sep);
    $nguoiKhac = nhanVienCua($sep);
    $task = Task::factory()->for($nhanVien, 'assignee')->create();

    $this->actingAs($nhanVien)
        ->patchJson("/api/v1/tasks/{$task->uuid}/assign", ['assignee_id' => $nguoiKhac->uuid])
        ->assertStatus(403);
});

/*
|--------------------------------------------------------------------------
| Đổi hạn — ràng buộc nghiệp vụ quan trọng nhất
|--------------------------------------------------------------------------
*/

it('đổi hạn phải kèm lý do và được ghi vào lịch sử', function (): void {
    $sep = truongPhong();
    $task = Task::factory()->for($sep, 'assigner')->create([
        'due_date' => now()->addDays(3),
        'due_date_change_count' => 0,
    ]);
    $hanMoi = now()->addDays(10);

    $this->actingAs($sep)->patchJson("/api/v1/tasks/{$task->uuid}/due-date", [
        'due_date' => $hanMoi->toIso8601String(),
        'reason' => 'Khách hàng lùi lịch nghiệm thu',
    ])->assertOk();

    $task->refresh();
    $lanDoi = TaskDueDateChange::query()->where('task_id', $task->id)->firstOrFail();

    expect($task->due_date_change_count)->toBe(1)
        ->and($lanDoi->reason)->toBe('Khách hàng lùi lịch nghiệm thu')
        ->and($lanDoi->requested_by)->toBe($sep->id);
});

it('từ chối đổi hạn khi không nêu lý do', function (): void {
    // Toàn bộ đánh giá đúng hạn ở đợt 5 dựa trên deadline. Dời hạn trong im
    // lặng làm mọi chỉ số về sau vô nghĩa.
    $sep = truongPhong();
    $task = Task::factory()->for($sep, 'assigner')->create(['due_date' => now()->addDays(3)]);

    $this->actingAs($sep)->patchJson("/api/v1/tasks/{$task->uuid}/due-date", [
        'due_date' => now()->addDays(10)->toIso8601String(),
    ])
        ->assertStatus(422)
        ->assertJsonStructure(['errors' => ['reason']]);

    expect(TaskDueDateChange::query()->count())->toBe(0);
});

it('nhân viên không được tự dời hạn', function (): void {
    $sep = truongPhong();
    $nhanVien = nhanVienCua($sep);
    $task = Task::factory()->for($nhanVien, 'assignee')->create(['due_date' => now()->addDay()]);

    $this->actingAs($nhanVien)->patchJson("/api/v1/tasks/{$task->uuid}/due-date", [
        'due_date' => now()->addDays(30)->toIso8601String(),
        'reason' => 'Em làm không kịp',
    ])->assertStatus(403);
});

/*
|--------------------------------------------------------------------------
| Lọc, sắp xếp, phân trang
|--------------------------------------------------------------------------
*/

it('lọc theo trạng thái', function (): void {
    $sep = truongPhong();
    Task::factory()->for($sep, 'assignee')->create(['status' => TaskStatus::Todo, 'title' => 'Chưa làm']);
    Task::factory()->for($sep, 'assignee')->create(['status' => TaskStatus::Done, 'title' => 'Đã xong']);

    $tieuDe = $this->actingAs($sep)
        ->getJson('/api/v1/tasks?status=done')
        ->assertOk()
        ->json('data.*.title');

    expect($tieuDe)->toBe(['Đã xong']);
});

it('lọc task quá hạn', function (): void {
    $sep = truongPhong();
    Task::factory()->for($sep, 'assignee')->create([
        'due_date' => now()->subDays(2), 'status' => TaskStatus::InProgress, 'title' => 'Trễ rồi',
    ]);
    Task::factory()->for($sep, 'assignee')->create([
        'due_date' => now()->addDays(2), 'status' => TaskStatus::InProgress, 'title' => 'Còn hạn',
    ]);

    expect($this->actingAs($sep)->getJson('/api/v1/tasks?overdue=1')->json('data.*.title'))
        ->toBe(['Trễ rồi']);
});

it('tìm theo từ khoá trong tiêu đề', function (): void {
    $sep = truongPhong();
    Task::factory()->for($sep, 'assignee')->create(['title' => 'Gọi khách hàng VIP']);
    Task::factory()->for($sep, 'assignee')->create(['title' => 'Viết báo cáo tháng']);

    expect($this->actingAs($sep)->getJson('/api/v1/tasks?search=khách')->json('data.*.title'))
        ->toBe(['Gọi khách hàng VIP']);
});

it('trả về đủ thông tin phân trang theo chuẩn thống nhất', function (): void {
    // Cấu trúc này là hợp đồng với frontend — mọi endpoint danh sách phải giống
    // nhau. Xem README mục 1.4.
    $sep = truongPhong();
    Task::factory()->count(30)->for($sep, 'assignee')->create();

    $this->actingAs($sep)->getJson('/api/v1/tasks?per_page=10')
        ->assertOk()
        ->assertJsonCount(10, 'data')
        ->assertJsonStructure([
            'data',
            'links' => ['first', 'last', 'prev', 'next'],
            'meta' => ['current_page', 'last_page', 'per_page', 'total'],
        ])
        ->assertJsonPath('meta.total', 30);
});

it('trả thời gian theo chuẩn ISO 8601 kèm offset', function (): void {
    $sep = truongPhong();
    Task::factory()->for($sep, 'assignee')->create(['due_date' => now()->addDay()]);

    $han = $this->actingAs($sep)->getJson('/api/v1/tasks')->json('data.0.due_date');

    expect($han)->toMatch('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}$/');
});
