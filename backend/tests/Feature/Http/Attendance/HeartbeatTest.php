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

/*
|--------------------------------------------------------------------------
| Chấm công theo sự có mặt
|--------------------------------------------------------------------------
|
| Từ khi đổi cách tính, mở tab là được tính — không cần thao tác, không cần
| tab đang hiển thị. Lý do: lập trình viên sống trong IDE, cả buổi sáng viết
| code xong hệ thống cũ hiện số 0.
|
| Đo hụt người làm thật tệ hơn hẳn đếm dư người treo máy, nên phần chặn lạm
| dụng chuyển sang trần giờ mỗi ngày chứ không nằm ở điều kiện thao tác nữa.
*/

it('vẫn tính giờ khi chỉ mở tab, không thao tác gì', function (): void {
    // Đây là test quan trọng nhất của cả thay đổi này.
    $u = nhanVienChamCong();

    $moc = CarbonImmutable::parse('2026-03-02 09:00:00', 'Asia/Ho_Chi_Minh');

    foreach ([0, 5, 10, 15] as $phut) {
        $this->travelTo($moc->addMinutes($phut));
        nhip($u, coThaoTac: false)->assertOk();
    }

    // `sole()` chứ không `first()`: nó khẳng định luôn "đúng MỘT phiên", tức
    // là bốn nhịp đã nối vào nhau chứ không tạo bốn dòng rời.
    $phien = WorkSession::query()->where('user_id', $u->id)->sole();

    expect($phien->started_at->diffInMinutes($phien->ended_at))->toBe(15.0)
        ->and($phien->interactive)->toBeFalse();
});

it('cắt phiên mới khi chuyển giữa có thao tác và chỉ mở tab', function (): void {
    // Không cắt theo loại thì một phiên bốn tiếng lẫn lộn cả hai, và dòng thời
    // gian mất khả năng phân biệt "ngồi làm" với "để đó".
    $u = nhanVienChamCong();

    $moc = CarbonImmutable::parse('2026-03-02 09:00:00', 'Asia/Ho_Chi_Minh');

    $this->travelTo($moc);
    nhip($u, coThaoTac: true);

    $this->travelTo($moc->addMinutes(5));
    nhip($u, coThaoTac: true);

    // Rời sang VS Code — vẫn mở tab nhưng không chạm vào Explus nữa.
    $this->travelTo($moc->addMinutes(10));
    nhip($u, coThaoTac: false);

    $loai = WorkSession::query()
        ->where('user_id', $u->id)
        ->orderBy('started_at')
        ->pluck('interactive')
        ->all();

    // So cả dãy một lần: vừa kiểm số phiên vừa kiểm thứ tự loại, và đọc ra
    // đúng điều đang muốn nói — "có thao tác trước, để tab sau".
    expect($loai)->toBe([true, false]);
});

it('không gửi cờ active thì coi như có thao tác', function (): void {
    // Bản giao diện cũ chỉ gửi nhịp khi CÓ thao tác. Trong quãng người dùng
    // chưa tải lại trang sau deploy, mặc định `false` sẽ gắn nhãn sai cho mọi
    // phiên của họ.
    $u = nhanVienChamCong();

    $this->actingAs($u)->postJson('/api/v1/attendance/heartbeat')->assertOk();

    expect(WorkSession::query()->where('user_id', $u->id)->first()?->interactive)
        ->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| Trần giờ mỗi ngày
|--------------------------------------------------------------------------
|
| Tab quên đóng qua đêm ghi thẳng 16 tiếng công. Vài lần như vậy là không ai
| còn tin bảng công — mà mất niềm tin thì cả hệ thống chấm công thành vô dụng,
| chứ không chỉ sai vài con số.
*/

it('ngừng ghi khi đã chạm trần giờ trong ngày', function (): void {
    config()->set('attendance.max_daily_minutes', 60);

    $u = nhanVienChamCong();
    $moc = CarbonImmutable::parse('2026-03-02 08:00:00', 'Asia/Ho_Chi_Minh');

    // Một phiên đã có sẵn 60 phút — vừa đủ trần.
    WorkSession::query()->create([
        'user_id' => $u->id,
        'started_at' => $moc->utc(),
        'ended_at' => $moc->addMinutes(60)->utc(),
        'work_date' => '2026-03-02',
        'source' => 'heartbeat',
        'interactive' => false,
    ]);

    $this->travelTo($moc->addMinutes(62));
    $phanHoi = nhip($u, coThaoTac: false)->assertOk();

    expect($phanHoi->json('data.capped'))->toBeTrue()
        // Không phiên mới, và phiên cũ không dài thêm một giây nào.
        ->and(WorkSession::query()->where('user_id', $u->id)->count())->toBe(1)
        ->and($phanHoi->json('data.session_started_at'))->toBeNull();
});

it('trần không chặn quãng do người quản lý nhập tay', function (): void {
    // Quãng nhập tay đã đi qua một con người rồi — không nên bị một cái trần
    // tự động chặn.
    config()->set('attendance.max_daily_minutes', 60);

    $u = nhanVienChamCong();
    $moc = CarbonImmutable::parse('2026-03-02 08:00:00', 'Asia/Ho_Chi_Minh');

    WorkSession::query()->create([
        'user_id' => $u->id,
        'started_at' => $moc->utc(),
        'ended_at' => $moc->addMinutes(300)->utc(),
        'work_date' => '2026-03-02',
        'source' => 'manual',
        'interactive' => true,
    ]);

    $this->travelTo($moc->addMinutes(310));

    expect(nhip($u)->assertOk()->json('data.capped'))->toBeFalse();
});

it('trần bằng 0 nghĩa là không giới hạn', function (): void {
    config()->set('attendance.max_daily_minutes', 0);

    $u = nhanVienChamCong();
    $moc = CarbonImmutable::parse('2026-03-02 08:00:00', 'Asia/Ho_Chi_Minh');

    WorkSession::query()->create([
        'user_id' => $u->id,
        'started_at' => $moc->utc(),
        'ended_at' => $moc->addMinutes(900)->utc(),
        'work_date' => '2026-03-02',
        'source' => 'heartbeat',
        'interactive' => false,
    ]);

    $this->travelTo($moc->addMinutes(905));

    expect(nhip($u)->assertOk()->json('data.capped'))->toBeFalse();
});
