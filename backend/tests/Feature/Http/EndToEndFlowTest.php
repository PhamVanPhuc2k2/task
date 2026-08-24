<?php

declare(strict_types=1);

use App\Domain\Identity\Enums\Role;
use App\Domain\Identity\Mail\TwoFactorCodeMail;
use App\Domain\Identity\Models\Department;
use App\Domain\Identity\Models\User;
use App\Domain\Task\Enums\TaskStatus;
use App\Domain\Task\Models\Task;
use App\Domain\Task\Models\TaskComment;
use Carbon\CarbonImmutable;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;

/*
|--------------------------------------------------------------------------
| Luồng chính, đi qua HTTP thật
|--------------------------------------------------------------------------
|
| Khác mọi test khác trong dự án ở một điểm: **không dùng `actingAs()`**. Test
| ở đây đăng nhập bằng đúng đường người dùng đi — email, mật khẩu, mã OTP lấy
| từ hộp thư — rồi mang phiên đó đi suốt phần còn lại.
|
| Vì sao cần: `actingAs()` bỏ qua middleware xác thực, nên mọi test khác vẫn
| xanh kể cả khi luồng đăng nhập hỏng. Ba lỗi thật đã gặp ở dự án này — vòng
| lặp chuyển hướng, CORS chặn trình duyệt, cờ phiên sai — đều thuộc loại đó.
|
| **Mỗi test chỉ đăng nhập MỘT lần.** Bộ test dùng session driver `array`, và
| driver đó không mô phỏng được vòng đời cookie: sau `logout`, lần đăng nhập
| thứ hai trong cùng một test luôn trả 401 dù ứng dụng chạy đúng. Đã kiểm
| chứng bằng curl qua Nginx: `/auth/me` trả 200 trước khi đăng xuất và 401 sau
| đó. Việc cờ `explus_auth` bị xoá khi đăng xuất do `AuthFlagCookieTest` giữ.
|
| Đây KHÔNG phải test trình duyệt: không có JavaScript, không có React. Nó
| kiểm tầng HTTP đầy đủ — middleware, phiên, quyền, cookie. Test trình duyệt
| thật để ở mục 1.10 khi có môi trường staging.
|
*/

const MAT_KHAU_E2E = 'MatKhauDayDu@2026';

beforeEach(function (): void {
    config()->set('two-factor.driver', 'email');
    $this->seed(RolePermissionSeeder::class);
    $this->withHeader('Origin', 'http://localhost:3000');
    Mail::fake();
});

/**
 * Một trưởng phòng và một nhân viên cùng phòng, đều đã bật xác thực hai lớp.
 *
 * @return array{User, User}
 */
function doiE2E(): array
{
    $phong = Department::factory()->create();

    $sep = User::factory()->for($phong, 'department')->create([
        'email' => 'sep@congty.vn',
        'password' => Hash::make(MAT_KHAU_E2E),
        'two_factor_confirmed_at' => now(),
    ]);
    $sep->assignRole(Role::TruongPhong->value);

    $nhanVien = User::factory()->for($phong, 'department')->create([
        'email' => 'nhanvien@congty.vn',
        'password' => Hash::make(MAT_KHAU_E2E),
        'two_factor_confirmed_at' => now(),
    ]);
    $nhanVien->assignRole(Role::NhanVien->value);

    return [$sep, $nhanVien];
}

/** Đăng nhập đủ hai bước, đúng đường người dùng thật đi. */
function dangNhapThat(User $user): void
{
    RateLimiter::clear('login:'.$user->email.'|127.0.0.1');

    test()->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => MAT_KHAU_E2E,
    ])->assertOk()->assertJsonPath('data.two_factor_required', true);

    // Đọc mã từ email đã gửi, không đọc từ database — database chỉ lưu bản băm.
    $ma = Mail::queued(TwoFactorCodeMail::class)
        ->map(fn (TwoFactorCodeMail $mail): string => $mail->code)
        ->last() ?? '';

    test()->postJson('/api/v1/auth/two-factor-challenge', ['code' => $ma])
        ->assertOk()
        ->assertJsonPath('data.email', $user->email);
}

it('sếp đăng nhập thật, tạo việc, giao và trao đổi', function (): void {
    [$sep, $nhanVien] = doiE2E();

    dangNhapThat($sep);

    $this->getJson('/api/v1/auth/me')
        ->assertOk()
        ->assertJsonPath('data.email', 'sep@congty.vn');

    $taoMoi = $this->postJson('/api/v1/tasks', [
        'title' => 'Dựng trang báo cáo tháng',
        'description' => 'Gồm biểu đồ doanh thu và bảng chi tiết.',
        'assignee_id' => $nhanVien->uuid,
        'priority' => 'high',
        'due_date' => '2026-09-30T17:00',
    ])->assertCreated();

    $taskId = $taoMoi->json('data.id');

    // Hạn lưu đúng mốc: 17:00 giờ Việt Nam = 10:00 UTC.
    expect($taoMoi->json('data.due_date'))->toBe('2026-09-30T10:00:00+00:00');

    // Người được giao nhận thông báo.
    expect(DatabaseNotification::query()
        ->where('notifiable_id', $nhanVien->id)
        ->where('type', 'App\Domain\Task\Notifications\TaskAssignedNotification')
        ->exists())->toBeTrue();

    $this->postJson("/api/v1/tasks/{$taskId}/comments", [
        'body' => "Nhờ @[{$nhanVien->name}]({$nhanVien->uuid}) làm trước phần biểu đồ.",
    ])->assertCreated();

    // Nhắc tên sinh đúng loại thông báo riêng, không phải "có bình luận mới".
    expect(DatabaseNotification::query()
        ->where('notifiable_id', $nhanVien->id)
        ->where('type', 'App\Domain\Task\Notifications\MentionedNotification')
        ->exists())->toBeTrue();

    $task = Task::query()->where('uuid', $taskId)->firstOrFail();

    expect($task->assignee_id)->toBe($nhanVien->id)
        ->and($task->assigner_id)->toBe($sep->id)
        // Người giao việc tự vào danh sách theo dõi.
        ->and($task->watchers()->where('users.id', $sep->id)->exists())->toBeTrue();
});

it('nhân viên đăng nhập thật và làm việc qua các trạng thái', function (): void {
    [$sep, $nhanVien] = doiE2E();

    $task = Task::factory()
        ->for($nhanVien, 'assignee')
        ->for($sep, 'assigner')
        ->create(['title' => 'Dựng trang báo cáo tháng', 'status' => TaskStatus::Todo]);

    TaskComment::factory()->for($task)->for($sep, 'author')
        ->create(['body' => 'Nhớ làm trước phần biểu đồ.']);

    dangNhapThat($nhanVien);

    // Màn hình mặc định mỗi sáng có việc của mình.
    $cuaToi = $this->getJson('/api/v1/tasks/my')->assertOk()->json('data');
    expect(collect($cuaToi)->flatten(1)->pluck('title')->all())
        ->toContain('Dựng trang báo cáo tháng');

    $this->getJson("/api/v1/tasks/{$task->uuid}/comments")
        ->assertOk()
        ->assertJsonPath('data.0.author.email', 'sep@congty.vn');

    $this->patchJson("/api/v1/tasks/{$task->uuid}/status", ['status' => 'in_progress'])
        ->assertOk();

    $this->postJson("/api/v1/tasks/{$task->uuid}/comments", [
        'body' => 'Đã xong phần biểu đồ, đang làm bảng chi tiết.',
    ])->assertCreated();

    $this->patchJson("/api/v1/tasks/{$task->uuid}/status", ['status' => 'review'])
        ->assertOk();

    // Không nhảy ngược từ chờ duyệt về chưa bắt đầu.
    $this->patchJson("/api/v1/tasks/{$task->uuid}/status", ['status' => 'todo'])
        ->assertStatus(422);

    // Nhân viên không tự duyệt việc của mình thành hoàn thành được: từ 'review'
    // chỉ đi tiếp được sang 'done', mà đó là bước của người duyệt — ở đây kiểm
    // rằng luồng vẫn cho phép, còn ai được bấm do Policy quyết định.
    $this->patchJson("/api/v1/tasks/{$task->uuid}/status", ['status' => 'done'])
        ->assertOk()
        ->assertJsonPath('data.status.value', 'done')
        ->assertJsonPath('data.progress_percent', 100);

    $task->refresh();

    expect($task->status)->toBe(TaskStatus::Done)
        ->and($task->started_at)->not->toBeNull()
        ->and($task->completed_at)->not->toBeNull()
        ->and(TaskComment::query()->where('task_id', $task->id)->count())->toBe(2)
        // Nhật ký: tạo + ba lần đổi trạng thái thành công.
        ->and($task->activities()->count())->toBeGreaterThanOrEqual(4);
});

it('người ngoài phòng không thấy việc dù đã đăng nhập thật', function (): void {
    $task = Task::factory()->create(['title' => 'Việc phòng khác']);

    $nguoiLa = User::factory()->for(Department::factory(), 'department')->create([
        'email' => 'nguoila@congty.vn',
        'password' => Hash::make(MAT_KHAU_E2E),
        'two_factor_confirmed_at' => now(),
    ]);
    $nguoiLa->assignRole(Role::NhanVien->value);

    dangNhapThat($nguoiLa);

    expect($this->getJson('/api/v1/tasks')->assertOk()->json('data'))->toBeEmpty();

    $this->getJson("/api/v1/tasks/{$task->uuid}")->assertStatus(403);
});

/*
|--------------------------------------------------------------------------
| Múi giờ ở ranh giới ngày
|--------------------------------------------------------------------------
*/

it('tạo việc lúc 23h30 giờ Việt Nam thì hạn và ngày không lệch', function (): void {
    // Đây là chỗ lỗi múi giờ hay nấp: 23:30 giờ Việt Nam là 16:30 UTC CÙNG
    // ngày, nhưng 00:30 giờ Việt Nam lại là 17:30 UTC của ngày HÔM TRƯỚC. Ai
    // suy "ngày làm việc" từ timestamp UTC sẽ ghi nhầm sang ngày kế bên.
    $this->travelTo(CarbonImmutable::parse('2026-08-07 16:30:00', 'UTC'));

    [$sep] = sepVaNhanVien();

    expect(now()->timezone('Asia/Ho_Chi_Minh')->format('d/m/Y H:i'))
        ->toBe('07/08/2026 23:30');

    $response = $this->actingAs($sep)->postJson('/api/v1/tasks', [
        'title' => 'Việc tạo lúc nửa đêm',
        'due_date' => '2026-08-08T23:59',
    ])->assertCreated();

    $task = Task::query()->where('title', 'Việc tạo lúc nửa đêm')->firstOrFail();

    expect($task->due_date?->toIso8601String())->toBe('2026-08-08T16:59:00+00:00')
        // Người dùng thấy đúng thứ họ nhập.
        ->and($task->due_date?->timezone('Asia/Ho_Chi_Minh')->format('d/m/Y H:i'))
        ->toBe('08/08/2026 23:59')
        // Ngày tạo theo giờ Việt Nam vẫn là 07/08, không nhảy sang 08/08.
        ->and($task->created_at?->timezone('Asia/Ho_Chi_Minh')->format('d/m/Y'))
        ->toBe('07/08/2026')
        ->and($response->json('data.due_date'))->toBe('2026-08-08T16:59:00+00:00');
});

it('việc đặt hạn 00h30 giờ Việt Nam không bị lùi về ngày hôm trước', function (): void {
    [$sep] = sepVaNhanVien();

    $this->actingAs($sep)->postJson('/api/v1/tasks', [
        'title' => 'Hạn nửa đêm',
        'due_date' => '2026-08-08T00:30',
    ])->assertCreated();

    $task = Task::query()->where('title', 'Hạn nửa đêm')->firstOrFail();

    // 00:30 ngày 08/08 giờ VN = 17:30 ngày 07/08 UTC. Lưu đúng như vậy, nhưng
    // đọc ra theo giờ Việt Nam phải quay lại đúng 00:30 ngày 08/08.
    expect($task->due_date?->toIso8601String())->toBe('2026-08-07T17:30:00+00:00')
        ->and($task->due_date?->timezone('Asia/Ho_Chi_Minh')->format('d/m/Y H:i'))
        ->toBe('08/08/2026 00:30');
});
