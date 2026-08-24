<?php

declare(strict_types=1);

use App\Domain\Task\Models\Task;
use App\Support\Time\IncomingDateTime;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->withHeader('Origin', 'http://localhost:3000');
});

/*
|--------------------------------------------------------------------------
| Chuẩn hoá mốc thời gian nhận từ client
|--------------------------------------------------------------------------
|
| Lỗi này chỉ lộ ra khi chạy thật ở mục 1.6: đặt hạn 20:00 tối, hệ thống hiểu
| thành 03:00 sáng hôm sau. Không có gì báo — dữ liệu vẫn lưu, vẫn đọc ra được,
| chỉ sai giờ.
|
*/

it('hiểu chuỗi không kèm offset là giờ Việt Nam', function (): void {
    // Ô `datetime-local` của trình duyệt gửi đúng dạng này. Cast của Eloquent
    // hiểu nó là UTC — lệch bảy tiếng.
    $utc = IncomingDateTime::toUtc('2026-08-07T20:00');

    expect($utc?->toIso8601String())->toBe('2026-08-07T13:00:00+00:00');
});

it('tôn trọng offset khi client có gửi kèm', function (): void {
    // Cast của Eloquent nuốt mất offset và lưu nguyên giờ địa phương.
    $utc = IncomingDateTime::toUtc('2026-08-07T20:00:00+07:00');

    expect($utc?->toIso8601String())->toBe('2026-08-07T13:00:00+00:00');
});

it('hiểu hậu tố Z là UTC', function (): void {
    expect(IncomingDateTime::toUtc('2026-08-07T13:00:00Z')?->toIso8601String())
        ->toBe('2026-08-07T13:00:00+00:00');
});

it('trả null cho chuỗi rỗng', function (): void {
    expect(IncomingDateTime::toUtc(null))->toBeNull()
        ->and(IncomingDateTime::toUtc(''))->toBeNull()
        ->and(IncomingDateTime::toUtc('   '))->toBeNull();
});

/*
|--------------------------------------------------------------------------
| Đường đi thật qua API
|--------------------------------------------------------------------------
*/

it('lưu đúng mốc khi tạo task với hạn giờ Việt Nam', function (): void {
    [$sep] = sepVaNhanVien();

    $this->actingAs($sep)->postJson('/api/v1/tasks', [
        'title' => 'Hạn tám giờ tối',
        'due_date' => '2026-08-07T20:00',
    ])->assertCreated();

    $task = Task::query()->where('title', 'Hạn tám giờ tối')->firstOrFail();

    expect($task->due_date?->toIso8601String())->toBe('2026-08-07T13:00:00+00:00')
        // Điều người dùng thực sự thấy: đúng 20:00 ngày 07/08.
        ->and($task->due_date?->timezone('Asia/Ho_Chi_Minh')->format('d/m/Y H:i'))
        ->toBe('07/08/2026 20:00');
});

it('lưu đúng mốc khi dời hạn', function (): void {
    [$sep, $nhanVien] = sepVaNhanVien();
    $task = Task::factory()->for($nhanVien, 'assignee')->create();

    $this->actingAs($sep)->patchJson("/api/v1/tasks/{$task->uuid}/due-date", [
        'due_date' => '2026-09-01T09:30',
        'reason' => 'Khách hàng dời lịch nghiệm thu.',
    ])->assertOk();

    expect($task->refresh()->due_date?->timezone('Asia/Ho_Chi_Minh')->format('d/m/Y H:i'))
        ->toBe('01/09/2026 09:30');
});

it('trả về hạn kèm offset để frontend đổi lại đúng giờ', function (): void {
    [$sep] = sepVaNhanVien();

    $response = $this->actingAs($sep)->postJson('/api/v1/tasks', [
        'title' => 'Kiểm định dạng trả về',
        'due_date' => '2026-08-07T20:00',
    ])->assertCreated();

    expect($response->json('data.due_date'))->toBe('2026-08-07T13:00:00+00:00');
});
