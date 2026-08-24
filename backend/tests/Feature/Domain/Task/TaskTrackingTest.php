<?php

declare(strict_types=1);

use App\Domain\Identity\Models\User;
use App\Domain\Task\Models\Task;
use App\Domain\Task\Models\TaskActivity;
use App\Domain\Task\Models\TaskDueDateChange;
use App\Domain\Task\Models\TaskLabel;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;

it('cho người ngoài theo dõi task để nhận thông báo', function (): void {
    $task = Task::factory()->create();
    $nguoiTheoDoi = User::factory()->create();

    $task->watchers()->attach($nguoiTheoDoi->id);

    expect($task->watchers->pluck('id')->all())->toBe([$nguoiTheoDoi->id]);
});

it('không cho một người theo dõi cùng một task hai lần', function (): void {
    $task = Task::factory()->create();
    $nguoi = User::factory()->create();
    $task->watchers()->attach($nguoi->id);

    expect(fn () => $task->watchers()->attach($nguoi->id))
        ->toThrow(UniqueConstraintViolationException::class);
});

it('gắn nhiều nhãn phân loại lên một task', function (): void {
    $task = Task::factory()->create();
    $gap = TaskLabel::factory()->create(['name' => 'Gấp']);
    $khachVIP = TaskLabel::factory()->create(['name' => 'Khách VIP']);

    $task->labels()->attach([$gap->id, $khachVIP->id]);

    expect($task->labels->pluck('name')->all())->toEqualCanonicalizing(['Gấp', 'Khách VIP']);
});

it('ghi nhật ký thay đổi kèm giá trị cũ và mới', function (): void {
    $task = Task::factory()->create();
    $nguoiSua = User::factory()->create();

    $activity = TaskActivity::factory()->for($task)->for($nguoiSua, 'causer')->create([
        'event' => 'status_changed',
        'old_values' => ['status' => 'todo'],
        'new_values' => ['status' => 'in_progress'],
    ]);

    // Từ mục 1.4, TaskActivityObserver tự ghi thêm một mục 'created' ngay khi
    // factory tạo task, nên nhật ký không chỉ có mỗi dòng viết tay ở trên.
    expect($activity->refresh()->old_values)->toBe(['status' => 'todo'])
        ->and($activity->new_values)->toBe(['status' => 'in_progress'])
        ->and($task->activities->pluck('id')->all())->toContain($activity->id)
        ->and($task->activities->pluck('event')->all())->toContain('created');
});

/*
|--------------------------------------------------------------------------
| Đổi deadline
|--------------------------------------------------------------------------
|
| Toàn bộ việc đánh giá đúng hạn ở đợt 5 dựa trên deadline. Nếu ai cũng tự dời
| hạn khi sắp trễ thì mọi chỉ số về sau đều vô nghĩa. Nên mỗi lần dời hạn phải
| để lại vết: hạn cũ, hạn mới, lý do bắt buộc, ai đề nghị, ai duyệt.
|
*/

it('lưu lại mọi lần dời hạn kèm lý do bắt buộc', function (): void {
    $task = Task::factory()->create(['due_date' => now()->addDays(3)]);
    $nguoiDeNghi = User::factory()->create();
    $nguoiDuyet = User::factory()->create();

    $lanDoi = TaskDueDateChange::factory()->for($task)->create([
        'old_due_date' => now()->addDays(3),
        'new_due_date' => now()->addDays(10),
        'reason' => 'Khách hàng lùi lịch nghiệm thu',
        'requested_by' => $nguoiDeNghi->id,
        'approved_by' => $nguoiDuyet->id,
    ]);

    expect($lanDoi->reason)->toBe('Khách hàng lùi lịch nghiệm thu')
        ->and($lanDoi->requester?->id)->toBe($nguoiDeNghi->id)
        ->and($lanDoi->approver?->id)->toBe($nguoiDuyet->id)
        ->and($task->dueDateChanges)->toHaveCount(1);
});

it('từ chối lưu lần dời hạn không có lý do', function (): void {
    // Ràng buộc ở tầng database, không chỉ ở tầng ứng dụng — để không ai lách
    // được bằng cách ghi thẳng vào bảng.
    $task = Task::factory()->create();

    expect(fn () => TaskDueDateChange::factory()->for($task)->create(['reason' => null]))
        ->toThrow(QueryException::class);
});

it('đếm số lần task đã bị dời hạn', function (): void {
    $task = Task::factory()->create(['due_date_change_count' => 0]);

    TaskDueDateChange::factory()->count(3)->for($task)->create();
    $task->increment('due_date_change_count', 3);

    expect($task->refresh()->due_date_change_count)->toBe(3)
        ->and($task->dueDateChanges)->toHaveCount(3);
});
