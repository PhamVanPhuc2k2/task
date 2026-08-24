<?php

declare(strict_types=1);

use App\Domain\Identity\Enums\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Leave\Enums\LeaveStatus;
use App\Domain\Leave\Models\LateArrivalRequest;
use Carbon\CarbonImmutable;
use Database\Seeders\RolePermissionSeeder;

/*
|--------------------------------------------------------------------------
| Đơn đã duyệt miễn cờ đi muộn
|--------------------------------------------------------------------------
|
| Đây là LÝ DO cả tính năng tồn tại. Không có phần này thì người xin phép đàng
| hoàng và người ngủ quên hiện y hệt nhau trên bảng công — và cái đơn trở thành
| thủ tục giấy tờ không có tác dụng gì.
|
| Quyết định quan trọng nhất ở đây: đơn miễn **tới đúng giờ đã xin**, không
| phải miễn cả ngày. Xin đến 9h mà 11h mới tới thì vẫn là đi muộn — nếu không
| thì mọi người chỉ cần nộp một đơn "9h" là muộn bao nhiêu cũng được bỏ qua.
|
| Mốc: 12/08/2026. Ca chuẩn 8h15.
|
*/

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->travelTo(CarbonImmutable::parse('2026-08-12 09:00:00'));
});

function donDiMuon(User $u, string $ngay, string $gio, LeaveStatus $trangThai): LateArrivalRequest
{
    return LateArrivalRequest::query()->create([
        'user_id' => $u->id,
        'date' => $ngay,
        'expected_arrival' => $gio,
        'reason' => 'Đưa con đi khám buổi sáng.',
        'status' => $trangThai,
        'reviewed_at' => $trangThai === LeaveStatus::Pending ? null : now(),
    ]);
}

it('đơn đã duyệt và đến đúng giờ đã xin thì được miễn', function (): void {
    $u = User::factory()->create();
    $u->assignRole(Role::NhanVien->value);

    coGioLamTu($u, '2026-08-12', '09:25', 300);
    donDiMuon($u, '2026-08-12', '09:30', LeaveStatus::Approved);

    $this->actingAs($u)
        ->getJson('/api/v1/attendance/me?month=2026-08')
        ->assertOk()
        // Số phút muộn vẫn giữ nguyên — sự thật không bị xoá, chỉ được giải
        // thích. Ai cần tra lại "hôm đó đến lúc mấy giờ" vẫn tra được.
        ->assertJsonPath('data.cells.2026-08-12.late_minutes', 70)
        ->assertJsonPath('data.cells.2026-08-12.late_excused', true);
});

it('đơn CHƯA duyệt không miễn gì cả', function (): void {
    // Nộp đơn rồi tự ý đi muộn trước khi được duyệt thì vẫn phải hiện cờ —
    // nếu không, ai cũng miễn được bằng cách nộp đơn.
    $u = User::factory()->create();
    $u->assignRole(Role::NhanVien->value);

    coGioLamTu($u, '2026-08-12', '09:25', 300);
    donDiMuon($u, '2026-08-12', '09:30', LeaveStatus::Pending);

    $this->actingAs($u)
        ->getJson('/api/v1/attendance/me?month=2026-08')
        ->assertJsonPath('data.cells.2026-08-12.late_excused', false);
});

it('đến muộn HƠN giờ đã xin thì không được miễn', function (): void {
    /*
    | Test quan trọng nhất file. Xin đến 9h nhưng 11h mới tới — đơn không bao
    | được phần vượt quá. Bỏ luật này thì một đơn duy nhất biến thành giấy
    | thông hành cho cả ngày.
    */
    $u = User::factory()->create();
    $u->assignRole(Role::NhanVien->value);

    coGioLamTu($u, '2026-08-12', '11:00', 300);
    donDiMuon($u, '2026-08-12', '09:00', LeaveStatus::Approved);

    $this->actingAs($u)
        ->getJson('/api/v1/attendance/me?month=2026-08')
        ->assertJsonPath('data.cells.2026-08-12.late_minutes', 165)
        ->assertJsonPath('data.cells.2026-08-12.late_excused', false);
});

it('đơn bị từ chối không miễn gì cả', function (): void {
    $u = User::factory()->create();
    $u->assignRole(Role::NhanVien->value);

    coGioLamTu($u, '2026-08-12', '09:25', 300);
    donDiMuon($u, '2026-08-12', '09:30', LeaveStatus::Rejected);

    $this->actingAs($u)
        ->getJson('/api/v1/attendance/me?month=2026-08')
        ->assertJsonPath('data.cells.2026-08-12.late_excused', false);
});

it('đơn của ngày khác không miễn cho ngày này', function (): void {
    // Bẫy dễ mắc khi gom khoá theo người mà quên ghép ngày.
    $u = User::factory()->create();
    $u->assignRole(Role::NhanVien->value);

    coGioLamTu($u, '2026-08-12', '09:25', 300);
    donDiMuon($u, '2026-08-11', '09:30', LeaveStatus::Approved);

    $this->actingAs($u)
        ->getJson('/api/v1/attendance/me?month=2026-08')
        ->assertJsonPath('data.cells.2026-08-12.late_excused', false);
});

it('đơn của người khác không miễn cho mình', function (): void {
    $u = User::factory()->create();
    $u->assignRole(Role::NhanVien->value);

    $nguoiKhac = User::factory()->create();

    coGioLamTu($u, '2026-08-12', '09:25', 300);
    donDiMuon($nguoiKhac, '2026-08-12', '09:30', LeaveStatus::Approved);

    $this->actingAs($u)
        ->getJson('/api/v1/attendance/me?month=2026-08')
        ->assertJsonPath('data.cells.2026-08-12.late_excused', false);
});

it('bảng công của quản lý cũng thấy đơn đã duyệt', function (): void {
    [$sep, $nv] = sepVaNhanVien();

    coGioLamTu($nv, '2026-08-12', '09:25', 300);
    donDiMuon($nv, '2026-08-12', '09:30', LeaveStatus::Approved);

    $hang = collect(
        $this->actingAs($sep)
            ->getJson('/api/v1/attendance/team?month=2026-08')
            ->assertOk()
            ->json('data.rows'),
    )->firstWhere('user.id', $nv->uuid);

    expect($hang['cells']['2026-08-12']['late_excused'])->toBeTrue();
});

it('không có đơn thì không được miễn', function (): void {
    $u = User::factory()->create();
    $u->assignRole(Role::NhanVien->value);

    coGioLamTu($u, '2026-08-12', '09:25', 300);

    $this->actingAs($u)
        ->getJson('/api/v1/attendance/me?month=2026-08')
        ->assertJsonPath('data.cells.2026-08-12.late_excused', false);
});
