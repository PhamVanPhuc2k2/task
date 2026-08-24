<?php

declare(strict_types=1);

use App\Domain\Identity\Models\User;
use App\Domain\Task\Enums\TaskStatus;
use App\Domain\Task\Models\Task;
use App\Domain\Task\Models\TaskActivity;
use Carbon\CarbonImmutable;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->withHeader('Origin', 'http://localhost:3000');
});

// sepVaNhanVien() khai báo ở tests/Pest.php để mọi file test dùng chung được.

/*
|--------------------------------------------------------------------------
| Việc của tôi
|--------------------------------------------------------------------------
*/

it('gom task của tôi theo nhóm hạn', function (): void {
    // Đây là màn hình mặc định mỗi sáng. Nhân viên cần biết ngay: việc nào đã
    // trễ, việc nào phải xong hôm nay.
    //
    // Cố định thời gian về một thứ Hai: nhóm "tuần này" tính tới hết chủ nhật,
    // nên nếu chạy vào thứ Sáu thì `addDays(3)` rơi sang tuần sau và test đỏ
    // dù mã nguồn đúng. Test phụ thuộc ngày trong tuần là test không đáng tin.
    $this->travelTo(CarbonImmutable::parse('2026-08-10 09:00:00'));

    [, $nhanVien] = sepVaNhanVien();

    Task::factory()->for($nhanVien, 'assignee')->create([
        'title' => 'Trễ rồi', 'due_date' => now()->subDays(2), 'status' => TaskStatus::InProgress,
    ]);
    Task::factory()->for($nhanVien, 'assignee')->create([
        'title' => 'Hôm nay', 'due_date' => now()->endOfDay()->subHour(), 'status' => TaskStatus::Todo,
    ]);
    Task::factory()->for($nhanVien, 'assignee')->create([
        'title' => 'Tuần này', 'due_date' => now()->addDays(3), 'status' => TaskStatus::Todo,
    ]);
    Task::factory()->for($nhanVien, 'assignee')->create([
        'title' => 'Xa hơn', 'due_date' => now()->addDays(30), 'status' => TaskStatus::Todo,
    ]);

    $response = $this->actingAs($nhanVien)->getJson('/api/v1/tasks/my')->assertOk();

    expect($response->json('data.overdue.*.title'))->toBe(['Trễ rồi'])
        ->and($response->json('data.today.*.title'))->toBe(['Hôm nay'])
        ->and($response->json('data.this_week.*.title'))->toBe(['Tuần này'])
        ->and($response->json('data.later.*.title'))->toBe(['Xa hơn']);
});

it('không tính task đã xong vào nhóm quá hạn', function (): void {
    [, $nhanVien] = sepVaNhanVien();

    Task::factory()->for($nhanVien, 'assignee')->create([
        'title' => 'Xong rồi', 'due_date' => now()->subDays(5), 'status' => TaskStatus::Done,
    ]);

    expect($this->actingAs($nhanVien)->getJson('/api/v1/tasks/my')->json('data.overdue'))
        ->toBeEmpty();
});

it('việc của tôi chỉ lấy task mình làm, không lấy task mình giao', function (): void {
    [$sep, $nhanVien] = sepVaNhanVien();

    Task::factory()->for($nhanVien, 'assignee')->for($sep, 'assigner')->create(['title' => 'Tôi làm']);
    Task::factory()->for($sep, 'assignee')->create(['title' => 'Sếp tự làm']);

    $tatCa = $this->actingAs($nhanVien)->getJson('/api/v1/tasks/my')->json('data');
    $tieuDe = collect($tatCa)->flatten(1)->pluck('title')->all();

    expect($tieuDe)->toContain('Tôi làm')->not->toContain('Sếp tự làm');
});

/*
|--------------------------------------------------------------------------
| Việc của đội
|--------------------------------------------------------------------------
*/

it('trưởng phòng xem được task của cấp dưới', function (): void {
    [$sep, $nhanVien] = sepVaNhanVien();

    Task::factory()->for($nhanVien, 'assignee')->create(['title' => 'Việc nhân viên']);

    expect($this->actingAs($sep)->getJson('/api/v1/tasks/team')->assertOk()->json('data.*.title'))
        ->toContain('Việc nhân viên');
});

it('nhân viên thường không vào được danh sách của đội', function (): void {
    [, $nhanVien] = sepVaNhanVien();

    $this->actingAs($nhanVien)->getJson('/api/v1/tasks/team')->assertStatus(403);
});

/*
|--------------------------------------------------------------------------
| Bàn giao hàng loạt
|--------------------------------------------------------------------------
*/

it('bàn giao toàn bộ task đang mở của người nghỉ việc', function (): void {
    // Không có đường này thì nhân viên nghỉ việc là task treo lơ lửng, không ai
    // biết ai đang làm.
    [$sep, $nhanVien] = sepVaNhanVien();
    $nguoiNhan = User::factory()->create(['department_id' => $sep->department_id]);

    Task::factory()->count(3)->for($nhanVien, 'assignee')->create(['status' => TaskStatus::InProgress]);
    $daXong = Task::factory()->for($nhanVien, 'assignee')->create(['status' => TaskStatus::Done]);

    $this->actingAs($sep)->postJson('/api/v1/tasks/bulk-reassign', [
        'from_user_id' => $nhanVien->uuid,
        'to_user_id' => $nguoiNhan->uuid,
    ])->assertOk()->assertJsonPath('data.reassigned', 3);

    expect(Task::query()->where('assignee_id', $nguoiNhan->id)->count())->toBe(3)
        // Task đã xong giữ nguyên người làm — đó là lịch sử, không phải việc tồn.
        ->and($daXong->refresh()->assignee_id)->toBe($nhanVien->id);
});

it('nhân viên không được bàn giao hàng loạt', function (): void {
    [$sep, $nhanVien] = sepVaNhanVien();

    $this->actingAs($nhanVien)->postJson('/api/v1/tasks/bulk-reassign', [
        'from_user_id' => $nhanVien->uuid,
        'to_user_id' => $sep->uuid,
    ])->assertStatus(403);
});

/*
|--------------------------------------------------------------------------
| Nhật ký thay đổi tự động
|--------------------------------------------------------------------------
*/

it('tự ghi nhật ký khi đổi trạng thái', function (): void {
    // Nhật ký phải tự sinh, không phụ thuộc lập trình viên nhớ gọi. Quên một
    // chỗ là mất dấu vết ở đúng chỗ đó.
    [, $nhanVien] = sepVaNhanVien();
    $task = Task::factory()->for($nhanVien, 'assignee')->create(['status' => TaskStatus::Todo]);

    $this->actingAs($nhanVien)->patchJson("/api/v1/tasks/{$task->uuid}/status", [
        'status' => TaskStatus::InProgress->value,
    ])->assertOk();

    $nhatKy = TaskActivity::query()->where('task_id', $task->id)->latest('id')->firstOrFail();

    expect($nhatKy->event)->toBe('updated')
        ->and($nhatKy->causer_id)->toBe($nhanVien->id)
        ->and($nhatKy->old_values)->toHaveKey('status')
        ->and($nhatKy->new_values['status'] ?? null)->toBe('in_progress');
});

it('ghi nhật ký khi tạo task', function (): void {
    [$sep] = sepVaNhanVien();

    $this->actingAs($sep)->postJson('/api/v1/tasks', ['title' => 'Việc mới'])->assertCreated();

    $task = Task::query()->where('title', 'Việc mới')->firstOrFail();

    expect(TaskActivity::query()->where('task_id', $task->id)->where('event', 'created')->exists())
        ->toBeTrue();
});

it('ghi ngày giờ trong nhật ký theo ISO 8601 kèm offset', function (): void {
    // Mảng thuộc tính của Eloquent giữ ngày dưới dạng 'Y-m-d H:i:s' không kèm
    // múi giờ. Ghi thẳng chuỗi đó thì trình duyệt đọc thành giờ máy — lệch bảy
    // tiếng với người dùng ở Việt Nam, mà không có gì báo là đã lệch.
    [, $nhanVien] = sepVaNhanVien();
    $task = Task::factory()->for($nhanVien, 'assignee')->create(['status' => TaskStatus::Todo]);

    $this->actingAs($nhanVien)->patchJson("/api/v1/tasks/{$task->uuid}/status", [
        'status' => TaskStatus::InProgress->value,
    ])->assertOk();

    $nhatKy = TaskActivity::query()->where('task_id', $task->id)->latest('id')->firstOrFail();

    expect($nhatKy->new_values['started_at'] ?? null)
        ->toMatch('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}$/');
});

it('xoá cứng task không làm Observer ném lỗi khoá ngoại', function (): void {
    // Lỗi thật gặp ở mục 1.4: Observer cố ghi nhật ký trỏ vào dòng task vừa
    // biến mất, vi phạm khoá ngoại và trả 500.
    [, $nhanVien] = sepVaNhanVien();
    $task = Task::factory()->for($nhanVien, 'assignee')->create();

    $task->forceDelete();

    expect(TaskActivity::query()->where('task_id', $task->id)->count())->toBe(0);
});

it('không ghi nhật ký khi lưu mà không đổi gì', function (): void {
    [, $nhanVien] = sepVaNhanVien();
    $task = Task::factory()->for($nhanVien, 'assignee')->create(['status' => TaskStatus::Todo]);

    TaskActivity::query()->delete();

    // Đổi sang chính trạng thái đang có — Action trả về sớm, không lưu.
    $this->actingAs($nhanVien)->patchJson("/api/v1/tasks/{$task->uuid}/status", [
        'status' => TaskStatus::Todo->value,
    ])->assertOk();

    expect(TaskActivity::query()->count())->toBe(0);
});
