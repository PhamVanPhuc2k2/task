<?php

declare(strict_types=1);

use App\Domain\Attendance\Models\WorkSession;
use App\Domain\Identity\Enums\Role;
use App\Domain\Identity\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\RolePermissionSeeder;

/*
|--------------------------------------------------------------------------
| Nhịp tim → phiên làm việc
|--------------------------------------------------------------------------
|
| Phần dễ sai nhất của cả tính năng nằm ở đây: ranh giới ngày công theo giờ
| Việt Nam, và luật nối phiên. Cả hai đều sai âm thầm — dữ liệu vẫn lưu được,
| vẫn đọc ra được, chỉ sai số.
|
*/

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->withHeader('Origin', 'http://localhost:3000');
});

function nhanVienChamCong(): User
{
    $u = User::factory()->create();
    $u->assignRole(Role::NhanVien->value);

    return $u;
}

/**
 * Mô phỏng một quãng làm việc liên tục.
 *
 * Giao diện thật gửi nhịp mỗi phút; ở đây gửi mỗi 5 phút cho test chạy nhanh —
 * vẫn dưới ngưỡng nối phiên 10 phút nên kết quả là một phiên duy nhất.
 *
 * Có hàm này vì viết tay hai mốc rồi mong chúng nối vào nhau là sai: hai nhịp
 * cách nhau 30 phút KHÔNG phải một phiên 30 phút, mà là hai phiên rỗng.
 */
function lamViec(User $u, string $tu, string $den): void
{
    $moc = CarbonImmutable::parse($tu);
    $cuoi = CarbonImmutable::parse($den);

    while ($moc->lessThanOrEqualTo($cuoi)) {
        test()->travelTo($moc);
        nhip($u);
        $moc = $moc->addMinutes(5);
    }
}

/*
|--------------------------------------------------------------------------
| Nối phiên
|--------------------------------------------------------------------------
*/

it('nhịp đầu tiên mở một phiên mới', function (): void {
    $u = nhanVienChamCong();

    nhip($u)->assertOk()->assertJsonPath('data.today_minutes', 0);

    expect(WorkSession::query()->where('user_id', $u->id)->count())->toBe(1);
});

it('các nhịp liên tiếp nối dài phiên đang mở, không tạo phiên mới', function (): void {
    // Nếu mỗi nhịp tạo một dòng thì tám tiếng làm việc thành 480 dòng mỗi
    // người mỗi ngày — đúng thứ mà thiết kế này sinh ra để tránh.
    $u = nhanVienChamCong();

    $this->travelTo(CarbonImmutable::parse('2026-08-12 09:00:00'));
    nhip($u);

    $this->travelTo(CarbonImmutable::parse('2026-08-12 09:01:00'));
    nhip($u);

    $this->travelTo(CarbonImmutable::parse('2026-08-12 09:05:00'));
    nhip($u)->assertJsonPath('data.today_minutes', 5);

    $phien = WorkSession::query()->where('user_id', $u->id)->get();

    expect($phien)->toHaveCount(1)
        ->and($phien->first()?->minutes())->toBe(5);
});

it('khoảng lặng quá ngưỡng thì cắt phiên và KHÔNG tính khoảng đó', function (): void {
    // Đây là khác biệt cốt lõi so với cách đếm "tab còn mở": nghỉ trưa hai
    // tiếng phải bị trừ ra, không được cộng vào giờ làm.
    $u = nhanVienChamCong();

    lamViec($u, '2026-08-12 09:00:00', '2026-08-12 09:30:00');

    // Nghỉ trưa 2 tiếng, không tương tác gì.
    lamViec($u, '2026-08-12 11:30:00', '2026-08-12 12:00:00');

    $ra = nhip($u);

    expect(WorkSession::query()->where('user_id', $u->id)->count())->toBe(2)
        // 30 phút + 30 phút, KHÔNG phải 180 phút.
        ->and($ra->json('data.today_minutes'))->toBe(60);
});

it('khoảng lặng đúng bằng ngưỡng vẫn tính là cùng phiên', function (): void {
    $u = nhanVienChamCong();

    $this->travelTo(CarbonImmutable::parse('2026-08-12 09:00:00'));
    nhip($u);
    $this->travelTo(CarbonImmutable::parse('2026-08-12 09:10:00'));
    nhip($u);

    expect(WorkSession::query()->where('user_id', $u->id)->count())->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Ranh giới ngày công — cái bẫy đắt nhất
|--------------------------------------------------------------------------
*/

it('ngày công tính theo giờ Việt Nam, không theo giờ UTC', function (): void {
    // 17:30 UTC ngày 11 = 00:30 giờ Việt Nam ngày 12.
    //
    // Nếu gom bảng công bằng DATE(started_at) trên cột UTC thì ngày công này
    // rơi vào 11/08 — sai một ngày, và sai âm thầm. Đây chính là lý do
    // work_date là một cột riêng chứ không tính lại lúc đọc.
    $u = nhanVienChamCong();

    $this->travelTo(CarbonImmutable::parse('2026-08-11 17:30:00', 'UTC'));
    nhip($u);

    $phien = WorkSession::query()->where('user_id', $u->id)->sole();

    expect($phien->work_date)->toBe('2026-08-12')
        ->and($phien->started_at->toDateString())->toBe('2026-08-11');
});

it('làm xuyên nửa đêm thì cắt sang phiên mới của ngày hôm sau', function (): void {
    // Không cắt thì toàn bộ số giờ sau nửa đêm bị dồn hết vào ngày hôm trước.
    $u = nhanVienChamCong();

    // 23:58 giờ VN = 16:58 UTC.
    $this->travelTo(CarbonImmutable::parse('2026-08-11 16:58:00', 'UTC'));
    nhip($u);

    // 00:02 giờ VN hôm sau = 17:02 UTC cùng ngày UTC.
    $this->travelTo(CarbonImmutable::parse('2026-08-11 17:02:00', 'UTC'));
    nhip($u);

    $ngay = WorkSession::query()
        ->where('user_id', $u->id)
        ->orderBy('id')
        ->pluck('work_date')
        ->all();

    // Cách nhau 4 phút, dưới ngưỡng nối phiên — nhưng khác ngày công nên vẫn
    // phải tách.
    expect($ngay)->toBe(['2026-08-11', '2026-08-12']);
});

/*
|--------------------------------------------------------------------------
| Quyền
|--------------------------------------------------------------------------
*/

it('chặn nhịp tim khi chưa đăng nhập', function (): void {
    $this->postJson('/api/v1/attendance/heartbeat')->assertUnauthorized();
});

it('mỗi người chỉ ghi phiên cho chính mình', function (): void {
    // Không có tham số người dùng trên endpoint — không có đường nào để ai đó
    // gửi nhịp tim hộ người khác.
    $a = nhanVienChamCong();
    $b = nhanVienChamCong();

    nhip($a);

    expect(WorkSession::query()->where('user_id', $a->id)->count())->toBe(1)
        ->and(WorkSession::query()->where('user_id', $b->id)->count())->toBe(0);
});

/*
|--------------------------------------------------------------------------
| Màn "của tôi"
|--------------------------------------------------------------------------
*/

it('nhân viên xem được giờ làm của chính mình mà không cần quyền gì thêm', function (): void {
    // Đây là điều kiện để việc đo giờ là tự theo dõi chứ không phải bị theo
    // dõi lén: nhân viên thấy đúng con số mà quản lý thấy.
    $u = nhanVienChamCong();

    lamViec($u, '2026-08-12 09:00:00', '2026-08-12 11:00:00');

    $this->actingAs($u)
        ->getJson('/api/v1/attendance/me?month=2026-08')
        ->assertOk()
        ->assertJsonPath('data.month', '2026-08')
        ->assertJsonPath('data.total_minutes', 120)
        ->assertJsonPath('data.days_worked', 1)
        ->assertJsonPath('data.cells.2026-08-12.minutes', 120)
        ->assertJsonPath('data.cells.2026-08-12.session_count', 1);
});

it('trả đủ mọi ngày trong tháng kể cả ngày không ai làm', function (): void {
    // Thiếu ngày thì các cột trong bảng lệch nhau và không đọc được theo hàng
    // dọc.
    $u = nhanVienChamCong();

    $ngay = $this->actingAs($u)
        ->getJson('/api/v1/attendance/me?month=2026-02')
        ->assertOk()
        ->json('data.days');

    expect($ngay)->toHaveCount(28)
        ->and($ngay[0])->toBe('2026-02-01')
        ->and($ngay[27])->toBe('2026-02-28');
});

it('tháng không hợp lệ thì về tháng hiện tại thay vì lỗi', function (): void {
    $u = nhanVienChamCong();
    $this->travelTo(CarbonImmutable::parse('2026-08-12 09:00:00'));

    $this->actingAs($u)
        ->getJson('/api/v1/attendance/me?month=linh-tinh')
        ->assertOk()
        ->assertJsonPath('data.month', '2026-08');
});
