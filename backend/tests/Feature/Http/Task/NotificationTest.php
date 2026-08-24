<?php

declare(strict_types=1);

use App\Domain\Identity\Enums\NotificationType;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Models\UserNotificationSetting;
use App\Domain\Task\Enums\TaskStatus;
use App\Domain\Task\Jobs\NotifyUpcomingDeadlinesJob;
use App\Domain\Task\Models\Task;
use App\Domain\Task\Notifications\CommentAddedNotification;
use App\Domain\Task\Notifications\MentionedNotification;
use App\Domain\Task\Notifications\TaskAssignedNotification;
use App\Domain\Task\Notifications\TaskDueSoonNotification;
use App\Domain\Task\Notifications\TaskOverdueNotification;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Notification;

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->withHeader('Origin', 'http://localhost:3000');
});

/*
|--------------------------------------------------------------------------
| Thông báo theo sự kiện
|--------------------------------------------------------------------------
*/

it('báo cho người vừa được giao việc', function (): void {
    Notification::fake();
    [$sep, $nhanVien] = sepVaNhanVien();

    $this->actingAs($sep)->postJson('/api/v1/tasks', [
        'title' => 'Việc mới',
        'assignee_id' => $nhanVien->uuid,
    ])->assertCreated();

    Notification::assertSentTo($nhanVien, TaskAssignedNotification::class);
});

it('không báo khi tự tạo việc cho chính mình', function (): void {
    // Nhận thông báo về việc mình vừa tự tạo là làm phiền vô cớ.
    Notification::fake();
    [$sep] = sepVaNhanVien();

    $this->actingAs($sep)->postJson('/api/v1/tasks', [
        'title' => 'Việc tôi tự làm',
        'assignee_id' => $sep->uuid,
    ])->assertCreated();

    Notification::assertNothingSent();
});

it('báo cho người được giao lại việc', function (): void {
    Notification::fake();
    [$sep, $nhanVien] = sepVaNhanVien();
    $task = Task::factory()->for($sep, 'assignee')->create();

    $this->actingAs($sep)->patchJson("/api/v1/tasks/{$task->uuid}/assign", [
        'assignee_id' => $nhanVien->uuid,
    ])->assertOk();

    Notification::assertSentTo($nhanVien, TaskAssignedNotification::class);
});

it('không báo khi giao lại đúng người đang làm', function (): void {
    // Bấm lưu mà giữ nguyên người cũ thì gửi thông báo là làm phiền, và người
    // nhận sẽ học cách bỏ qua.
    Notification::fake();
    [$sep, $nhanVien] = sepVaNhanVien();
    $task = Task::factory()->for($nhanVien, 'assignee')->create();

    $this->actingAs($sep)->patchJson("/api/v1/tasks/{$task->uuid}/assign", [
        'assignee_id' => $nhanVien->uuid,
    ])->assertOk();

    Notification::assertNothingSent();
});

it('gỡ người thực hiện được, không cần gửi kèm ai', function (): void {
    // Hộp thoại "Giao lại" ở giao diện có lựa chọn "Chưa giao" và gửi null.
    [$sep, $nhanVien] = sepVaNhanVien();
    $task = Task::factory()->for($nhanVien, 'assignee')->create();

    $this->actingAs($sep)->patchJson("/api/v1/tasks/{$task->uuid}/assign", [
        'assignee_id' => null,
    ])->assertOk();

    expect($task->refresh()->assignee_id)->toBeNull();
});

it('báo cho người được nhắc tên, và báo loại khác cho người chỉ theo dõi', function (): void {
    Notification::fake();
    [$sep, $nhanVien] = sepVaNhanVien();
    $nguoiTheoDoi = User::factory()->create(['department_id' => $sep->department_id]);

    $task = Task::factory()->for($nhanVien, 'assignee')->create();
    $task->watchers()->attach($nguoiTheoDoi->id);

    $this->actingAs($nhanVien)->postJson("/api/v1/tasks/{$task->uuid}/comments", [
        'body' => "Nhờ @[{$sep->name}]({$sep->uuid}) duyệt giúp.",
    ])->assertCreated();

    Notification::assertSentTo($sep, MentionedNotification::class);
    Notification::assertSentTo($nguoiTheoDoi, CommentAddedNotification::class);
});

it('người được nhắc không nhận thêm bản "có bình luận mới"', function (): void {
    // Hai thông báo cho cùng một bình luận là thứ khiến người dùng tắt hết.
    Notification::fake();
    [$sep, $nhanVien] = sepVaNhanVien();
    $task = Task::factory()->for($nhanVien, 'assignee')->create();
    $task->watchers()->attach($sep->id);

    $this->actingAs($nhanVien)->postJson("/api/v1/tasks/{$task->uuid}/comments", [
        'body' => "@[{$sep->name}]({$sep->uuid}) xem giúp nhé.",
    ])->assertCreated();

    Notification::assertNotSentTo($sep, CommentAddedNotification::class);
});

it('người tự viết bình luận không nhận thông báo về chính nó', function (): void {
    Notification::fake();
    [, $nhanVien] = sepVaNhanVien();
    $task = Task::factory()->for($nhanVien, 'assignee')->create();

    $this->actingAs($nhanVien)->postJson("/api/v1/tasks/{$task->uuid}/comments", [
        'body' => 'Ghi chú cho chính mình.',
    ])->assertCreated();

    Notification::assertNothingSent();
});

/*
|--------------------------------------------------------------------------
| Tuỳ chọn nhận thông báo
|--------------------------------------------------------------------------
*/

it('tôn trọng tuỳ chọn tắt kênh của người nhận', function (): void {
    Notification::fake();
    [$sep, $nhanVien] = sepVaNhanVien();

    UserNotificationSetting::query()->create([
        'user_id' => $nhanVien->id,
        'type' => NotificationType::TaskAssigned,
        'in_app' => false,
        'email' => false,
    ]);

    $this->actingAs($sep)->postJson('/api/v1/tasks', [
        'title' => 'Việc mới',
        'assignee_id' => $nhanVien->uuid,
    ])->assertCreated();

    // Tắt hết kênh thì `via()` trả về mảng rỗng và không có gì được gửi đi.
    Notification::assertNotSentTo($nhanVien, TaskAssignedNotification::class);
});

it('tắt một kênh không làm tắt kênh còn lại', function (): void {
    // Kiểm riêng vì test trên chỉ chứng minh "tắt hết thì im"; nếu `via()` cứ
    // trả rỗng bất kể tuỳ chọn thì test đó vẫn xanh mà tính năng thì hỏng.
    [, $nhanVien] = sepVaNhanVien();

    UserNotificationSetting::query()->create([
        'user_id' => $nhanVien->id,
        'type' => NotificationType::TaskAssigned,
        'in_app' => true,
        'email' => false,
    ]);

    $task = Task::factory()->for($nhanVien, 'assignee')->create();

    expect((new TaskAssignedNotification($task, 'Ai đó'))->via($nhanVien))
        ->toBe(['database']);
});

it('trả về đủ mọi loại thông báo kèm giá trị mặc định', function (): void {
    [, $nhanVien] = sepVaNhanVien();

    $muc = $this->actingAs($nhanVien)->getJson('/api/v1/notification-settings')
        ->assertOk()->json('data');

    expect($muc)->toHaveCount(count(NotificationType::cases()));

    $giaoViec = collect($muc)->firstWhere('type', NotificationType::TaskAssigned->value);

    expect($giaoViec['in_app'])->toBeTrue()
        // Email bật mặc định cho việc bỏ lỡ là có hậu quả thật.
        ->and($giaoViec['email'])->toBeTrue();

    $binhLuan = collect($muc)->firstWhere('type', NotificationType::CommentAdded->value);

    // Một task sôi nổi sinh vài chục bình luận một ngày — gửi email cho từng
    // cái là cách nhanh nhất để người dùng lọc hệ thống vào thư rác.
    expect($binhLuan['email'])->toBeFalse();
});

it('lưu được tuỳ chọn của người dùng', function (): void {
    [, $nhanVien] = sepVaNhanVien();

    $this->actingAs($nhanVien)->patchJson('/api/v1/notification-settings', [
        'type' => NotificationType::CommentAdded->value,
        'in_app' => true,
        'email' => true,
    ])->assertOk();

    $muc = $this->actingAs($nhanVien)->getJson('/api/v1/notification-settings')->json('data');

    expect(collect($muc)->firstWhere('type', NotificationType::CommentAdded->value)['email'])
        ->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| Quét deadline
|--------------------------------------------------------------------------
*/

it('nhắc việc sắp tới hạn và việc đã quá hạn', function (): void {
    Notification::fake();
    [, $nhanVien] = sepVaNhanVien();

    Task::factory()->for($nhanVien, 'assignee')->create([
        'title' => 'Sắp tới hạn', 'due_date' => now()->addHours(6), 'status' => TaskStatus::InProgress,
    ]);
    Task::factory()->for($nhanVien, 'assignee')->create([
        'title' => 'Đã quá hạn', 'due_date' => now()->subDays(2), 'status' => TaskStatus::Todo,
    ]);

    (new NotifyUpcomingDeadlinesJob)->handle();

    Notification::assertSentTo($nhanVien, TaskDueSoonNotification::class);
    Notification::assertSentTo($nhanVien, TaskOverdueNotification::class);
});

it('không nhắc lại task đã nhắc rồi', function (): void {
    // Job chạy mỗi giờ; không đánh dấu thì cùng một task báo chín lần một ngày.
    Notification::fake();
    [, $nhanVien] = sepVaNhanVien();

    $task = Task::factory()->for($nhanVien, 'assignee')->create([
        'due_date' => now()->subDays(2), 'status' => TaskStatus::Todo,
    ]);

    (new NotifyUpcomingDeadlinesJob)->handle();
    expect($task->refresh()->overdue_notified_at)->not->toBeNull();

    Notification::fake();
    (new NotifyUpcomingDeadlinesJob)->handle();

    Notification::assertNothingSent();
});

it('không nhắc deadline của task đã đóng', function (): void {
    Notification::fake();
    [, $nhanVien] = sepVaNhanVien();

    Task::factory()->for($nhanVien, 'assignee')->create([
        'due_date' => now()->subDays(5), 'status' => TaskStatus::Done,
    ]);

    (new NotifyUpcomingDeadlinesJob)->handle();

    Notification::assertNothingSent();
});

it('dời hạn thì xoá dấu đã nhắc để hạn mới được nhắc lại', function (): void {
    // Không xoá thì dời hạn ra xa rồi tới gần sẽ im lặng hoàn toàn — đúng lúc
    // người ta cần nhắc nhất.
    [$sep, $nhanVien] = sepVaNhanVien();

    $task = Task::factory()->for($nhanVien, 'assignee')->create([
        'due_date' => now()->subDays(2),
        'status' => TaskStatus::Todo,
        'overdue_notified_at' => now(),
    ]);

    $this->actingAs($sep)->patchJson("/api/v1/tasks/{$task->uuid}/due-date", [
        'due_date' => now()->addDays(5)->toIso8601String(),
        'reason' => 'Khách hàng dời lịch nghiệm thu.',
    ])->assertOk();

    expect($task->refresh()->overdue_notified_at)->toBeNull();
});

it('đánh dấu cả task của người đã nghỉ việc để không quét lại mãi', function (): void {
    Notification::fake();
    [, $nhanVien] = sepVaNhanVien();
    $nhanVien->update(['is_active' => false]);

    $task = Task::factory()->for($nhanVien, 'assignee')->create([
        'due_date' => now()->subDay(), 'status' => TaskStatus::Todo,
    ]);

    (new NotifyUpcomingDeadlinesJob)->handle();

    Notification::assertNothingSent();
    expect($task->refresh()->overdue_notified_at)->not->toBeNull();
});

/*
|--------------------------------------------------------------------------
| Trung tâm thông báo
|--------------------------------------------------------------------------
*/

it('liệt kê thông báo của chính mình và đếm số chưa đọc', function (): void {
    [$sep, $nhanVien] = sepVaNhanVien();

    $this->actingAs($sep)->postJson('/api/v1/tasks', [
        'title' => 'Việc mới', 'assignee_id' => $nhanVien->uuid,
    ])->assertCreated();

    $this->actingAs($nhanVien)->getJson('/api/v1/notifications')
        ->assertOk()
        ->assertJsonPath('data.0.kind', NotificationType::TaskAssigned->value);

    $this->actingAs($nhanVien)->getJson('/api/v1/notifications/unread-count')
        ->assertOk()
        ->assertJsonPath('data.unread', 1);
});

it('không thấy thông báo của người khác', function (): void {
    [$sep, $nhanVien] = sepVaNhanVien();

    $this->actingAs($sep)->postJson('/api/v1/tasks', [
        'title' => 'Việc mới', 'assignee_id' => $nhanVien->uuid,
    ])->assertCreated();

    expect($this->actingAs($sep)->getJson('/api/v1/notifications')->json('data'))->toBeEmpty();
});

it('đánh dấu đã đọc tất cả', function (): void {
    [$sep, $nhanVien] = sepVaNhanVien();

    foreach (['Việc A', 'Việc B'] as $ten) {
        $this->actingAs($sep)->postJson('/api/v1/tasks', [
            'title' => $ten, 'assignee_id' => $nhanVien->uuid,
        ])->assertCreated();
    }

    $this->actingAs($nhanVien)->postJson('/api/v1/notifications/read-all')
        ->assertOk()
        ->assertJsonPath('data.marked', 2);

    $this->actingAs($nhanVien)->getJson('/api/v1/notifications/unread-count')
        ->assertJsonPath('data.unread', 0);
});

it('không đánh dấu đọc được thông báo của người khác', function (): void {
    [$sep, $nhanVien] = sepVaNhanVien();

    $this->actingAs($sep)->postJson('/api/v1/tasks', [
        'title' => 'Việc mới', 'assignee_id' => $nhanVien->uuid,
    ])->assertCreated();

    $id = $this->actingAs($nhanVien)->getJson('/api/v1/notifications')->json('data.0.id');

    $this->actingAs($sep)->patchJson("/api/v1/notifications/{$id}/read")->assertStatus(404);
});
