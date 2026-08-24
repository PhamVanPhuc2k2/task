<?php

declare(strict_types=1);

use App\Domain\Identity\Enums\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Leave\Enums\LeaveStatus;
use App\Domain\Leave\Notifications\LateArrivalRequestedNotification;
use App\Domain\Leave\Notifications\LateArrivalReviewedNotification;
use Carbon\CarbonImmutable;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Notification;

/*
|--------------------------------------------------------------------------
| Đơn xin đi làm muộn
|--------------------------------------------------------------------------
|
| Khác đơn nghỉ ở một điểm quyết định cả thiết kế: đơn nghỉ tính bằng NGÀY, đơn
| này tính bằng GIỜ. `leave_requests.start_date` là cột DATE nên không nhét vừa
| "mai tôi tới lúc 9h30" — đó là lý do có bảng riêng chứ không thêm một loại
| nghỉ phép.
|
| Mốc: 12/08/2026 09:00 UTC = 16:00 giờ Việt Nam. Ca chuẩn 8h15.
|
*/

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->withHeader('Origin', 'http://localhost:3000');
    $this->travelTo(CarbonImmutable::parse('2026-08-12 09:00:00'));
});

it('nộp được đơn xin đi muộn cho ngày mai', function (): void {
    $u = User::factory()->create();
    $u->assignRole(Role::NhanVien->value);

    $this->actingAs($u)
        ->postJson('/api/v1/late-arrivals', [
            'date' => '2026-08-13',
            'expected_arrival' => '09:30',
            'reason' => 'Đưa con đi khám buổi sáng.',
        ])
        ->assertCreated()
        ->assertJsonPath('data.date', '2026-08-13')
        ->assertJsonPath('data.expected_arrival', '09:30')
        ->assertJsonPath('data.status', LeaveStatus::Pending->value);
});

it('bắt buộc phải có lý do', function (): void {
    // Đơn không lý do thì người duyệt không có gì để quyết, và ô duyệt biến
    // thành nút bấm cho có.
    $u = User::factory()->create();
    $u->assignRole(Role::NhanVien->value);

    $this->actingAs($u)
        ->postJson('/api/v1/late-arrivals', [
            'date' => '2026-08-13',
            'expected_arrival' => '09:30',
        ])
        ->assertJsonValidationErrors('reason');
});

it('từ chối giờ dự kiến sớm hơn giờ vào làm', function (): void {
    // Xin "đi muộn" tới 8h00 trong khi ca bắt đầu 8h15 là không có nghĩa. Cho
    // qua thì sinh ra những đơn được duyệt mà chẳng miễn cái gì.
    $u = User::factory()->create();
    $u->assignRole(Role::NhanVien->value);

    $this->actingAs($u)
        ->postJson('/api/v1/late-arrivals', [
            'date' => '2026-08-13',
            'expected_arrival' => '08:00',
            'reason' => 'Không hợp lệ.',
        ])
        ->assertJsonValidationErrors('expected_arrival');
});

it('không nộp hai đơn cho cùng một ngày', function (): void {
    $u = User::factory()->create();
    $u->assignRole(Role::NhanVien->value);

    $don = fn () => $this->actingAs($u)->postJson('/api/v1/late-arrivals', [
        'date' => '2026-08-13',
        'expected_arrival' => '09:30',
        'reason' => 'Lý do hợp lệ.',
    ]);

    $don()->assertCreated();
    $don()->assertJsonValidationErrors('date');
});

it('quản lý duyệt được đơn', function (): void {
    [$sep, $nv] = sepVaNhanVien();

    $tao = $this->actingAs($nv)->postJson('/api/v1/late-arrivals', [
        'date' => '2026-08-13',
        'expected_arrival' => '09:30',
        'reason' => 'Đưa con đi khám buổi sáng.',
    ])->assertCreated();

    $this->actingAs($sep)
        ->postJson("/api/v1/late-arrivals/{$tao->json('data.id')}/review", [
            'approve' => true,
        ])
        ->assertOk()
        ->assertJsonPath('data.status', LeaveStatus::Approved->value);
});

it('nhân viên thường không duyệt được đơn của người khác', function (): void {
    [, $nv] = sepVaNhanVien();
    $nguoiKhac = User::factory()->create();
    $nguoiKhac->assignRole(Role::NhanVien->value);

    $tao = $this->actingAs($nv)->postJson('/api/v1/late-arrivals', [
        'date' => '2026-08-13',
        'expected_arrival' => '09:30',
        'reason' => 'Đưa con đi khám buổi sáng.',
    ])->assertCreated();

    $this->actingAs($nguoiKhac)
        ->postJson("/api/v1/late-arrivals/{$tao->json('data.id')}/review", [
            'approve' => true,
        ])
        ->assertForbidden();
});

it('người nộp xem được đơn của chính mình', function (): void {
    $u = User::factory()->create();
    $u->assignRole(Role::NhanVien->value);

    $this->actingAs($u)->postJson('/api/v1/late-arrivals', [
        'date' => '2026-08-13',
        'expected_arrival' => '09:30',
        'reason' => 'Đưa con đi khám buổi sáng.',
    ])->assertCreated();

    $this->actingAs($u)
        ->getJson('/api/v1/late-arrivals/me')
        ->assertOk()
        ->assertJsonPath('data.requests.0.expected_arrival', '09:30');
});

it('server nói ra khoảng ngày và giờ vào làm, không để giao diện tự đoán', function (): void {
    /*
    | Giao diện KHÔNG được tự tính hai thứ này từ `new Date()`: đồng hồ máy
    | người dùng có thể lệch, và múi giờ trình duyệt có thể không phải giờ Việt
    | Nam khi nhân viên đi công tác. Cùng nguyên tắc đã áp cho đơn nghỉ.
    */
    $u = User::factory()->create();
    $u->assignRole(Role::NhanVien->value);

    $this->actingAs($u)
        ->getJson('/api/v1/late-arrivals/me')
        ->assertOk()
        ->assertJsonPath('data.shift.morning_start', '08:15')
        ->assertJsonStructure([
            'data' => ['requests', 'total', 'window' => ['earliest', 'latest']],
        ]);
});

/*
|--------------------------------------------------------------------------
| Thông báo
|--------------------------------------------------------------------------
|
| Đơn nghỉ có thông báo, đơn đi muộn thì chưa — và đó là chỗ lệch rõ nhất giữa
| hai luồng lẽ ra giống hệt nhau. Nộp xong mà không ai được báo thì quản lý
| phải tự nhớ mở trang, tức là quay lại đúng thứ tính năng này sinh ra để bỏ.
|
*/

it('báo cho quản lý trực tiếp khi có đơn đi muộn mới', function (): void {
    Notification::fake();

    [$sep, $nv] = sepVaNhanVien();
    $nv->forceFill(['manager_id' => $sep->id])->save();

    $this->actingAs($nv)->postJson('/api/v1/late-arrivals', [
        'date' => '2026-08-13',
        'expected_arrival' => '09:30',
        'reason' => 'Đưa con đi khám buổi sáng.',
    ])->assertCreated();

    Notification::assertSentTo($sep, LateArrivalRequestedNotification::class);
});

it('không có quản lý trực tiếp thì không báo cho ai, và vẫn nộp được', function (): void {
    /*
    | Bắn cho mọi người có quyền duyệt thì bốn người cùng nhận một đơn, ba
    | người trong đó không liên quan. Lưới hứng là hộp duyệt trên trang — nó
    | luôn hiện số đơn đang chờ.
    */
    Notification::fake();

    $u = User::factory()->create(['manager_id' => null]);
    $u->assignRole(Role::NhanVien->value);

    $this->actingAs($u)->postJson('/api/v1/late-arrivals', [
        'date' => '2026-08-13',
        'expected_arrival' => '09:30',
        'reason' => 'Đưa con đi khám buổi sáng.',
    ])->assertCreated();

    Notification::assertNothingSent();
});

it('báo cho người nộp khi đơn được duyệt', function (): void {
    Notification::fake();

    [$sep, $nv] = sepVaNhanVien();

    $tao = $this->actingAs($nv)->postJson('/api/v1/late-arrivals', [
        'date' => '2026-08-13',
        'expected_arrival' => '09:30',
        'reason' => 'Đưa con đi khám buổi sáng.',
    ])->assertCreated();

    $this->actingAs($sep)
        ->postJson("/api/v1/late-arrivals/{$tao->json('data.id')}/review", ['approve' => true])
        ->assertOk();

    Notification::assertSentTo($nv, LateArrivalReviewedNotification::class);
});

it('báo cho người nộp khi đơn bị từ chối', function (): void {
    // Bị từ chối mà không được báo là trường hợp tệ nhất: người ta đinh ninh
    // mình đã xin phép xong rồi cứ thế đi muộn.
    Notification::fake();

    [$sep, $nv] = sepVaNhanVien();

    $tao = $this->actingAs($nv)->postJson('/api/v1/late-arrivals', [
        'date' => '2026-08-13',
        'expected_arrival' => '09:30',
        'reason' => 'Đưa con đi khám buổi sáng.',
    ])->assertCreated();

    $this->actingAs($sep)
        ->postJson("/api/v1/late-arrivals/{$tao->json('data.id')}/review", [
            'approve' => false,
            'note' => 'Sáng đó có họp giao ban với khách.',
        ])
        ->assertOk();

    Notification::assertSentTo($nv, LateArrivalReviewedNotification::class);
});
