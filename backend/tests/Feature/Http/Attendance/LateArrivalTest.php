<?php

declare(strict_types=1);

use App\Domain\Identity\Enums\Role;
use App\Domain\Identity\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\RolePermissionSeeder;

/*
|--------------------------------------------------------------------------
| Đi làm muộn hiện trên bảng công
|--------------------------------------------------------------------------
|
| Số phút muộn là thông tin THÊM, đặt cạnh số phút làm việc chứ không thay nó.
| Người đến muộn mà làm bù tới tối vẫn được tính đủ giờ — hai con số trả lời
| hai câu hỏi khác nhau, gộp lại là mất một câu.
|
| Mốc: 12/08/2026, ca chuẩn 8h15.
|
*/

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->travelTo(CarbonImmutable::parse('2026-08-12 09:00:00'));
});

function nhanVienCa(): User
{
    $u = User::factory()->create();
    $u->assignRole(Role::NhanVien->value);

    return $u;
}

it('đến 8h30 thì bảng công ghi muộn 15 phút', function (): void {
    $u = nhanVienCa();
    coGioLamTu($u, '2026-08-12', '08:30', 300);

    $this->actingAs($u)
        ->getJson('/api/v1/attendance/me?month=2026-08')
        ->assertOk()
        ->assertJsonPath('data.cells.2026-08-12.late_minutes', 15);
});

it('đến trước giờ vào làm thì không muộn phút nào', function (): void {
    $u = nhanVienCa();
    coGioLamTu($u, '2026-08-12', '07:55', 300);

    $this->actingAs($u)
        ->getJson('/api/v1/attendance/me?month=2026-08')
        ->assertJsonPath('data.cells.2026-08-12.late_minutes', 0);
});

it('đi muộn KHÔNG cắt giờ công', function (): void {
    /*
    | Ranh giới quan trọng nhất của tính năng này. Người đến muộn 15 phút rồi
    | làm 300 phút vẫn phải được ghi nhận đủ 300 phút. Trừ giờ vì đi muộn là
    | quyết định về lương, không phải việc của cái đồng hồ — và trộn hai thứ
    | vào một con số thì không ai lần lại được nữa.
    */
    $u = nhanVienCa();
    coGioLamTu($u, '2026-08-12', '08:30', 300);

    $this->actingAs($u)
        ->getJson('/api/v1/attendance/me?month=2026-08')
        ->assertJsonPath('data.cells.2026-08-12.minutes', 300)
        ->assertJsonPath('data.cells.2026-08-12.late_minutes', 15);
});

it('ngày không có phiên nào thì không phải đi muộn', function (): void {
    // Vắng mặt là chuyện khác hẳn. Người nghỉ phép mà hiện "muộn 9 tiếng" là
    // vô nghĩa, và làm cả cột mất tin cậy.
    $u = nhanVienCa();
    coGioLamTu($u, '2026-08-10', '08:00', 300);

    $o = $this->actingAs($u)
        ->getJson('/api/v1/attendance/me?month=2026-08')
        ->json('data.cells');

    expect($o)->not->toHaveKey('2026-08-12');
});

it('bảng công của quản lý cũng hiện số phút muộn', function (): void {
    [$sep, $nv] = sepVaNhanVien();
    coGioLamTu($nv, '2026-08-12', '09:00', 300);

    $hang = collect(
        $this->actingAs($sep)
            ->getJson('/api/v1/attendance/team?month=2026-08')
            ->assertOk()
            ->json('data.rows'),
    )->firstWhere('user.id', $nv->uuid);

    expect($hang['cells']['2026-08-12']['late_minutes'])->toBe(45);
});
