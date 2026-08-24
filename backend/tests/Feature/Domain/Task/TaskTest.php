<?php

declare(strict_types=1);

use App\Domain\Identity\Models\User;
use App\Domain\Task\Enums\TaskPriority;
use App\Domain\Task\Enums\TaskStatus;
use App\Domain\Task\Models\Project;
use App\Domain\Task\Models\Task;

it('ghi nhận đủ người làm, người giao và người duyệt', function (): void {
    $nguoiLam = User::factory()->create(['name' => 'Người làm']);
    $nguoiGiao = User::factory()->create(['name' => 'Người giao']);
    $nguoiDuyet = User::factory()->create(['name' => 'Người duyệt']);

    $task = Task::factory()
        ->for($nguoiLam, 'assignee')
        ->for($nguoiGiao, 'assigner')
        ->for($nguoiDuyet, 'reviewer')
        ->create();

    expect($task->assignee?->name)->toBe('Người làm')
        ->and($task->assigner?->name)->toBe('Người giao')
        ->and($task->reviewer?->name)->toBe('Người duyệt');
});

it('cho phép task rời không thuộc dự án nào', function (): void {
    // Việc vặt sếp giao trực tiếp không nhất thiết phải nằm trong một dự án.
    $task = Task::factory()->create(['project_id' => null]);

    expect($task->project)->toBeNull();
});

it('gắn task vào dự án và lấy ngược lại được', function (): void {
    $duAn = Project::factory()->create();
    $task = Task::factory()->for($duAn)->create();

    expect($task->project?->id)->toBe($duAn->id)
        ->and($duAn->tasks->pluck('id')->all())->toBe([$task->id]);
});

it('dựng được task con', function (): void {
    $cha = Task::factory()->create(['title' => 'Task cha']);
    $con = Task::factory()->for($cha, 'parent')->create(['title' => 'Task con']);

    expect($con->parent?->title)->toBe('Task cha')
        ->and($cha->subtasks->pluck('title')->all())->toBe(['Task con']);
});

it('mặc định task mới là chưa bắt đầu, ưu tiên bình thường', function (): void {
    $task = Task::factory()->create();

    expect($task->status)->toBe(TaskStatus::Todo)
        ->and($task->priority)->toBe(TaskPriority::Normal);
});

it('cast trạng thái và ưu tiên sang enum sau khi đọc lại từ database', function (): void {
    $task = Task::factory()->create([
        'status' => TaskStatus::InProgress,
        'priority' => TaskPriority::Urgent,
    ]);

    expect($task->refresh()->status)->toBeInstanceOf(TaskStatus::class)
        ->and($task->priority)->toBeInstanceOf(TaskPriority::class);
});

it('giữ nguyên độ chính xác số giờ ước lượng', function (): void {
    // Giờ công phải là DECIMAL. Nếu lỡ khai FLOAT thì 7.25 giờ có thể đọc lại
    // thành 7.2499999, và sai số đó sẽ tích luỹ lên bảng lương ở đợt 4.
    $task = Task::factory()->create(['estimate_hours' => '7.25']);

    expect($task->refresh()->estimate_hours)->toBe('7.25');
});

it('nhận diện task đã quá hạn mà chưa xong', function (): void {
    $quaHan = Task::factory()->create([
        'due_date' => now()->subDays(2),
        'status' => TaskStatus::InProgress,
    ]);
    Task::factory()->create(['due_date' => now()->addDays(2), 'status' => TaskStatus::InProgress]);
    // Đã xong thì dù quá hạn cũng không còn là việc cần nhắc.
    Task::factory()->create(['due_date' => now()->subDays(5), 'status' => TaskStatus::Done]);

    expect(Task::query()->overdue()->pluck('id')->all())->toBe([$quaHan->id]);
});

it('ghi nhận ai tạo bản ghi để truy vết', function (): void {
    $nguoiTao = User::factory()->create();
    $task = Task::factory()->create(['created_by' => $nguoiTao->id]);

    expect($task->creator?->id)->toBe($nguoiTao->id);
});

it('xoá mềm chứ không xoá cứng', function (): void {
    $task = Task::factory()->create();
    $task->delete();

    expect(Task::query()->count())->toBe(0)
        ->and(Task::withTrashed()->count())->toBe(1);
});
