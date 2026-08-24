<?php

declare(strict_types=1);

use App\Domain\Identity\Enums\Role;
use App\Domain\Identity\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\RolePermissionSeeder;

/*
|--------------------------------------------------------------------------
| Dòng thời gian một ngày — màn hình của giám đốc
|--------------------------------------------------------------------------
|
| Lưới tháng trả lời "tháng này ai làm bao nhiêu giờ". Màn này trả lời một câu
| khác hẳn: **"hôm nay ai đang làm, và khoảng nào ngồi không"**.
|
| Điểm mấu chốt là các KHOẢNG TRỐNG. Nhịp tim cách nhau quá 10 phút thì hệ
| thống cắt thành phiên mới (xem RecordHeartbeatAction), nên khe giữa hai phiên
| chính là lúc không có tương tác nào. Tổng số phút thì không nói được điều đó —
| làm 5 tiếng liền một mạch và làm 5 tiếng rải rác cả ngày ra cùng một con số.
|
| Mốc: 12/08/2026.
|
*/

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->travelTo(CarbonImmutable::parse('2026-08-12 09:00:00'));
});

it('trả về từng phiên làm việc trong ngày, theo giờ Việt Nam', function (): void {
    [$sep, $nv] = sepVaNhanVien();

    coGioLamTu($nv, '2026-08-12', '08:15', 165);   // 08:15 – 11:00

    $hang = collect(
        $this->actingAs($sep)
            ->getJson('/api/v1/attendance/timeline?date=2026-08-12')
            ->assertOk()
            ->json('data.rows'),
    )->firstWhere('user.id', $nv->uuid);

    expect($hang['sessions'])->toHaveCount(1)
        ->and($hang['sessions'][0]['start'])->toBe('08:15')
        ->and($hang['sessions'][0]['end'])->toBe('11:00');
});

it('khoảng lặng giữa hai phiên hiện ra thành khoảng NGỒI KHÔNG', function (): void {
    /*
    | Đây là lý do cả màn hình tồn tại. Làm sáng, nghỉ dài, làm lại chiều — tổng
    | giờ giống hệt người làm liền mạch, nhưng câu chuyện khác hẳn.
    */
    [$sep, $nv] = sepVaNhanVien();

    coGioLamTu($nv, '2026-08-12', '08:15', 105);   // 08:15 – 10:00
    coGioLamTu($nv, '2026-08-12', '11:30', 60);    // 11:30 – 12:30

    $hang = collect(
        $this->actingAs($sep)
            ->getJson('/api/v1/attendance/timeline?date=2026-08-12')
            ->json('data.rows'),
    )->firstWhere('user.id', $nv->uuid);

    expect($hang['sessions'])->toHaveCount(2)
        ->and($hang['gaps'])->toHaveCount(1)
        ->and($hang['gaps'][0]['start'])->toBe('10:00')
        ->and($hang['gaps'][0]['end'])->toBe('11:30')
        ->and($hang['idle_minutes'])->toBe(90);
});

it('không tính khoảng trước phiên đầu và sau phiên cuối là ngồi không', function (): void {
    // Chưa tới giờ làm và đã về thì không phải "ngồi không" — gộp vào là biến
    // cả buổi tối thành thời gian lười biếng.
    [$sep, $nv] = sepVaNhanVien();

    coGioLamTu($nv, '2026-08-12', '09:00', 60);    // 09:00 – 10:00

    $hang = collect(
        $this->actingAs($sep)
            ->getJson('/api/v1/attendance/timeline?date=2026-08-12')
            ->json('data.rows'),
    )->firstWhere('user.id', $nv->uuid);

    expect($hang['gaps'])->toBe([])
        ->and($hang['idle_minutes'])->toBe(0);
});

it('khung giờ nới rộng ra khi có người làm ngoài ca', function (): void {
    /*
    | Công ty làm remote, làm buổi tối là chuyện bình thường. Cắt cứng khung
    | 08h–18h thì phiên lúc 21h **biến mất khỏi màn hình** mà không có gì báo —
    | đúng loại hỏng im lặng dự án này liên tục phải trả giá.
    */
    [$sep, $nv] = sepVaNhanVien();

    coGioLamTu($nv, '2026-08-12', '21:00', 60);    // 21:00 – 22:00

    $data = $this->actingAs($sep)
        ->getJson('/api/v1/attendance/timeline?date=2026-08-12')
        ->json('data');

    expect($data['range']['end'] >= '22:00')->toBeTrue();
});

it('hiện cả người không có phiên nào — vắng mặt cũng là thông tin', function (): void {
    // Bỏ người vắng ra khỏi danh sách thì màn hình toàn người đang làm, và
    // giám đốc không thấy được ai chưa vào.
    [$sep, $nv] = sepVaNhanVien();

    $hang = collect(
        $this->actingAs($sep)
            ->getJson('/api/v1/attendance/timeline?date=2026-08-12')
            ->json('data.rows'),
    )->firstWhere('user.id', $nv->uuid);

    expect($hang)->not->toBeNull()
        ->and($hang['sessions'])->toBe([])
        ->and($hang['worked_minutes'])->toBe(0);
});

it('mang theo số phút đi muộn và trạng thái miễn', function (): void {
    [$sep, $nv] = sepVaNhanVien();

    coGioLamTu($nv, '2026-08-12', '09:00', 60);

    $hang = collect(
        $this->actingAs($sep)
            ->getJson('/api/v1/attendance/timeline?date=2026-08-12')
            ->json('data.rows'),
    )->firstWhere('user.id', $nv->uuid);

    expect($hang['late_minutes'])->toBe(45)
        ->and($hang['late_excused'])->toBeFalse();
});

it('nhân viên thường không xem được dòng thời gian của cả đội', function (): void {
    $u = User::factory()->create();
    $u->assignRole(Role::NhanVien->value);

    $this->actingAs($u)
        ->getJson('/api/v1/attendance/timeline?date=2026-08-12')
        ->assertForbidden();
});

it('nghỉ trưa KHÔNG bị tính là ngồi không', function (): void {
    /*
    | Nếu không trừ giờ nghỉ trưa ra thì NGÀY NÀO CŨNG có một khoảng vàng dài
    | 90 phút cho MỌI NGƯỜI. Cờ bật cho tất cả là cờ vô nghĩa — và theo đúng
    | nguyên tắc đã ghi ở nơi khác trong dự án, một chỉ số không bao giờ về 0
    | là chỉ số người ta ngừng đọc.
    |
    | Ca chuẩn nghỉ 12:00–13:30. Người làm tới 12:00 rồi quay lại 13:35 thì
    | phần 12:00–13:30 là nghỉ trưa, chỉ 5 phút sau đó mới là ngồi không.
    */
    [$sep, $nv] = sepVaNhanVien();

    coGioLamTu($nv, '2026-08-12', '08:15', 225);   // 08:15 – 12:00
    coGioLamTu($nv, '2026-08-12', '13:35', 100);   // 13:35 – 15:15

    $hang = collect(
        $this->actingAs($sep)
            ->getJson('/api/v1/attendance/timeline?date=2026-08-12')
            ->json('data.rows'),
    )->firstWhere('user.id', $nv->uuid);

    expect($hang['idle_minutes'])->toBe(5)
        ->and($hang['lunch_minutes'])->toBe(90);
});

it('vẫn tính ngồi không cho khoảng lặng nằm ngoài giờ nghỉ trưa', function (): void {
    // Lưới an toàn cho test trên: nếu ai đó trừ nhầm mọi khoảng lặng thành
    // nghỉ trưa thì cả cột ngồi không im lặng về 0 mà không có gì báo.
    [$sep, $nv] = sepVaNhanVien();

    coGioLamTu($nv, '2026-08-12', '08:15', 105);   // 08:15 – 10:00
    coGioLamTu($nv, '2026-08-12', '11:00', 60);    // 11:00 – 12:00

    $hang = collect(
        $this->actingAs($sep)
            ->getJson('/api/v1/attendance/timeline?date=2026-08-12')
            ->json('data.rows'),
    )->firstWhere('user.id', $nv->uuid);

    expect($hang['idle_minutes'])->toBe(60)
        ->and($hang['lunch_minutes'])->toBe(0);
});
