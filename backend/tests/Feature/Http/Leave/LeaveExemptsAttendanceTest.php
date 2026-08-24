<?php

declare(strict_types=1);

use App\Domain\Attendance\Models\Holiday;
use App\Domain\Identity\Enums\Role;
use App\Domain\Identity\Models\Department;
use App\Domain\Identity\Models\User;
use App\Domain\Leave\Enums\LeaveStatus;
use App\Domain\Leave\Enums\LeaveType;
use App\Domain\Leave\Models\LeaveRequest;
use App\Domain\Report\Notifications\DailyReportMissingNotification;
use Carbon\CarbonImmutable;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Notification;

/*
|--------------------------------------------------------------------------
| Ngày nghỉ đã duyệt được miễn chấm công
|--------------------------------------------------------------------------
|
| Đây là LÝ DO cả tính năng nghỉ phép tồn tại. Trước khi có nó, ngày nghỉ để
| lại một ô trống y hệt ngày vắng mặt không lý do, và cách duy nhất để dọn là
| quản lý bấm "Bỏ qua" kèm lý do — cho MỖI ngày nghỉ của MỖI người.
|
| Điều dễ hỏng âm thầm nhất: người nghỉ phép KHÔNG có phiên làm việc nào, mà ô
| trên lưới lại dựng từ bảng `work_sessions`. Quên xử lý thì ngày nghỉ đơn giản
| là không xuất hiện — không lỗi, không cảnh báo, chỉ là tính năng vô dụng.
|
| Mốc: 12/08/2026 09:00 UTC = 16:00 giờ Việt Nam.
|
*/

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->withHeader('Origin', 'http://localhost:3000');
    $this->travelTo(CarbonImmutable::parse('2026-08-12 09:00:00'));
});

function donDaDuyet(User $u, string $tu, string $den): LeaveRequest
{
    return LeaveRequest::query()->create([
        'user_id' => $u->id,
        'type' => LeaveType::Annual,
        'start_date' => $tu,
        'end_date' => $den,
        'reason' => 'Về quê, đã bàn giao việc.',
        'status' => LeaveStatus::Approved,
        'reviewed_at' => now(),
    ]);
}

it('ngày nghỉ đã duyệt HIỆN RA trên bảng công dù không có phiên làm việc nào', function (): void {
    /*
    | Test quan trọng nhất của file. Người nghỉ phép không đụng vào hệ thống nên
    | không có phiên nào — nếu ô không được sinh ra thì cả tính năng vô nghĩa.
    */
    $u = User::factory()->create();
    $u->assignRole(Role::NhanVien->value);

    donDaDuyet($u, '2026-08-10', '2026-08-11');

    $this->actingAs($u)
        ->getJson('/api/v1/attendance/me?month=2026-08')
        ->assertOk()
        ->assertJsonPath('data.cells.2026-08-10.report_match', 'on_leave')
        ->assertJsonPath('data.cells.2026-08-11.report_match', 'on_leave')
        ->assertJsonPath('data.cells.2026-08-10.minutes', 0);
});

it('ngày nghỉ KHÔNG bị tính là thiếu báo cáo, kể cả khi có giờ làm', function (): void {
    // Mở máy trả lời một tin nhắn trong ngày nghỉ không biến ngày đó thành
    // ngày làm việc.
    $u = User::factory()->create();
    $u->assignRole(Role::NhanVien->value);

    coGioLam($u, '2026-08-10', 300);
    donDaDuyet($u, '2026-08-10', '2026-08-10');

    $this->actingAs($u)
        ->getJson('/api/v1/attendance/me?month=2026-08')
        ->assertOk()
        ->assertJsonPath('data.cells.2026-08-10.report_match', 'on_leave')
        ->assertJsonPath('data.missing_report_days', 0);
});

it('đơn CHƯA duyệt không miễn gì cả', function (): void {
    // Nộp đơn rồi tự nghỉ trước khi được duyệt thì ngày đó vẫn phải hiện như
    // bình thường — nếu không, ai cũng miễn được chấm công bằng cách nộp đơn.
    $u = User::factory()->create();
    $u->assignRole(Role::NhanVien->value);

    coGioLam($u, '2026-08-10', 300);

    LeaveRequest::query()->create([
        'user_id' => $u->id,
        'type' => LeaveType::Annual,
        'start_date' => '2026-08-10',
        'end_date' => '2026-08-10',
        'reason' => 'Đơn còn đang chờ duyệt.',
        'status' => LeaveStatus::Pending,
    ]);

    $this->actingAs($u)
        ->getJson('/api/v1/attendance/me?month=2026-08')
        ->assertJsonPath('data.cells.2026-08-10.report_match', 'missing_report')
        ->assertJsonPath('data.missing_report_days', 1);
});

it('bảng công của quản lý cũng hiện ngày nghỉ', function (): void {
    $phong = Department::factory()->create();

    $sep = User::factory()->for($phong, 'department')->create();
    $sep->assignRole(Role::TruongPhong->value);

    $nv = User::factory()->for($phong, 'department')->create(['name' => 'Đang nghỉ']);
    $nv->assignRole(Role::NhanVien->value);

    donDaDuyet($nv, '2026-08-10', '2026-08-12');

    $hang = collect(
        $this->actingAs($sep)
            ->getJson('/api/v1/attendance/team?month=2026-08')
            ->assertOk()
            ->json('data.rows'),
    )->firstWhere('user.name', 'Đang nghỉ');

    expect($hang['cells']['2026-08-10']['report_match'])->toBe('on_leave')
        ->and($hang['cells']['2026-08-12']['report_match'])->toBe('on_leave')
        ->and($hang['missing_report_days'])->toBe(0);
});

it('chỉ cắt phần đơn rơi vào tháng đang xem', function (): void {
    // Đơn vắt qua hai tháng: xem tháng 8 thì không được sinh ô cho ngày tháng 9.
    $u = User::factory()->create();
    $u->assignRole(Role::NhanVien->value);

    donDaDuyet($u, '2026-08-28', '2026-09-04');

    $o = $this->actingAs($u)
        ->getJson('/api/v1/attendance/me?month=2026-08')
        ->assertOk()
        ->json('data.cells');

    expect($o)->toHaveKey('2026-08-31')
        ->and($o)->not->toHaveKey('2026-09-01');
});

it('lệnh nhắc 17h30 bỏ qua người đang nghỉ phép', function (): void {
    /*
    | Nhắc người đang nghỉ phép nộp báo cáo là đúng loại thông báo khiến cả
    | công ty tắt hết thông báo của hệ thống.
    */
    Notification::fake();

    $u = User::factory()->create();
    $u->assignRole(Role::NhanVien->value);

    coGioLam($u, '2026-08-12', 300);
    donDaDuyet($u, '2026-08-12', '2026-08-12');

    $this->artisan('reports:remind')->assertSuccessful();

    Notification::assertNotSentTo($u, DailyReportMissingNotification::class);
});

it('vẫn nhắc người KHÔNG có đơn nghỉ', function (): void {
    // Lưới an toàn cho test trên: nếu lệnh nhắc hỏng hẳn thì test kia vẫn xanh.
    Notification::fake();

    $u = User::factory()->create();
    $u->assignRole(Role::NhanVien->value);

    coGioLam($u, '2026-08-12', 300);

    $this->artisan('reports:remind')->assertSuccessful();

    Notification::assertSentTo($u, DailyReportMissingNotification::class);
});

it('ngày nghỉ được ưu tiên hiện hơn ngày lễ', function (): void {
    // Hai thứ trùng nhau khi xin nghỉ cả tuần có ngày lễ ở giữa. Khi đó thông
    // tin đáng hiện là "người này đang nghỉ phép" — nó giải thích cả chuỗi ngày.
    $u = User::factory()->create();
    $u->assignRole(Role::NhanVien->value);

    Holiday::query()->create([
        'date' => '2026-08-10',
        'observed_date' => '2026-08-10',
        'name' => 'Ngày lễ thử',
        'is_paid' => true,
    ]);

    donDaDuyet($u, '2026-08-10', '2026-08-10');

    $this->actingAs($u)
        ->getJson('/api/v1/attendance/me?month=2026-08')
        ->assertJsonPath('data.cells.2026-08-10.report_match', 'on_leave');
});
