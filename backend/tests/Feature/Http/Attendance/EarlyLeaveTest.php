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
| Xin về sớm
|--------------------------------------------------------------------------
|
| Đối xứng với đi muộn nhưng KHÔNG giống hệt, và ba khác biệt đều quan trọng:
|
| 1. Đi muộn đọc `first_seen_at` — đứng yên ngay từ nhịp tim đầu. Về sớm đọc
|    `last_seen_at`, mốc vẫn lớn dần chừng nào người ta còn mở máy. Nên con số
|    về sớm chỉ có nghĩa khi nhìn lại một ngày đã qua.
|
| 2. Ân hạn 5 phút, không phải 0 như đi muộn. Về sớm năm phút mà bắt làm đơn thì
|    không ai dùng, và một tính năng không ai dùng còn tệ hơn không có — nó làm
|    bảng công trông như đã theo dõi trong khi thực tế thì không.
|
| 3. Giờ tan ca phụ thuộc NGÀY. Thứ bảy tan lúc 12:00, nên về lúc 11:30 là sớm
|    30 phút chứ không phải sớm 6 tiếng.
|
| Mốc: 12/08/2026 là thứ Tư, 15/08 thứ Bảy. Ca 08:15–17:30, thứ bảy 08:15–12:00.
|
*/

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->withHeader('Origin', 'http://localhost:3000');
    $this->travelTo(CarbonImmutable::parse('2026-08-12 09:00:00'));
});

function nhanVienVeSom(): User
{
    $u = User::factory()->create();
    $u->assignRole(Role::NhanVien->value);

    return $u;
}

it('nộp được đơn xin về sớm', function (): void {
    $u = nhanVienVeSom();

    $this->actingAs($u)
        ->postJson('/api/v1/late-arrivals', [
            'type' => 'early',
            'date' => '2026-08-13',
            'expected_departure' => '16:00',
            'reason' => 'Đưa con đi khám buổi chiều.',
        ])
        ->assertCreated()
        ->assertJsonPath('data.type', 'early')
        ->assertJsonPath('data.type_label', 'Về sớm')
        ->assertJsonPath('data.expected_time', '16:00')
        ->assertJsonPath('data.status', LeaveStatus::Pending->value);
});

it('từ chối giờ rời không sớm hơn giờ tan ca', function (): void {
    // Xin "về sớm" lúc 18h trong khi ca tan 17h30 là không có nghĩa. Cho qua
    // thì sinh ra những đơn được duyệt mà chẳng miễn cái gì.
    $u = nhanVienVeSom();

    $this->actingAs($u)
        ->postJson('/api/v1/late-arrivals', [
            'type' => 'early',
            'date' => '2026-08-13',
            'expected_departure' => '18:00',
            'reason' => 'Đưa con đi khám buổi chiều.',
        ])
        ->assertJsonValidationErrors('expected_departure');
});

it('thứ bảy so với giờ tan NỬA BUỔI, không phải 17h30', function (): void {
    /*
    | Đây là chỗ mô hình cũ sẽ sai im lặng. Thứ bảy tan 12:00, nên xin về lúc
    | 14h là muộn HƠN giờ tan — một đơn vô nghĩa. Nếu luật lấy 17:30 cứng thì
    | đơn này lọt qua và được duyệt mà chẳng miễn cái gì.
    */
    $u = nhanVienVeSom();

    $this->actingAs($u)
        ->postJson('/api/v1/late-arrivals', [
            'type' => 'early',
            'date' => '2026-08-15',
            'expected_departure' => '14:00',
            'reason' => 'Đưa con đi khám buổi chiều.',
        ])
        ->assertJsonValidationErrors('expected_departure');

    // Còn 11:00 thì hợp lệ — sớm một tiếng so với 12:00.
    $this->actingAs($u)
        ->postJson('/api/v1/late-arrivals', [
            'type' => 'early',
            'date' => '2026-08-15',
            'expected_departure' => '11:00',
            'reason' => 'Đưa con đi khám buổi chiều.',
        ])
        ->assertCreated();
});

it('xin đi muộn và về sớm trong cùng một ngày đều được', function (): void {
    // Hai việc chẳng liên quan gì tới nhau. Luật "một ngày một đơn" phải lọc
    // theo LOẠI, nếu không thì xin đi muộn buổi sáng là mất quyền xin về sớm
    // buổi chiều.
    $u = nhanVienVeSom();

    $this->actingAs($u)->postJson('/api/v1/late-arrivals', [
        'type' => 'late',
        'date' => '2026-08-13',
        'expected_arrival' => '09:30',
        'reason' => 'Đưa con đi khám buổi sáng.',
    ])->assertCreated();

    $this->actingAs($u)->postJson('/api/v1/late-arrivals', [
        'type' => 'early',
        'date' => '2026-08-13',
        'expected_departure' => '16:00',
        'reason' => 'Chiều đón con ở trường.',
    ])->assertCreated();

    expect(LateArrivalRequest::query()->where('user_id', $u->id)->count())->toBe(2);
});

it('vẫn chặn hai đơn CÙNG LOẠI trong một ngày', function (): void {
    $u = nhanVienVeSom();

    $this->actingAs($u)->postJson('/api/v1/late-arrivals', [
        'type' => 'early',
        'date' => '2026-08-13',
        'expected_departure' => '16:00',
        'reason' => 'Đưa con đi khám buổi chiều.',
    ])->assertCreated();

    $this->actingAs($u)
        ->postJson('/api/v1/late-arrivals', [
            'type' => 'early',
            'date' => '2026-08-13',
            'expected_departure' => '15:00',
            'reason' => 'Đổi giờ, đón con sớm hơn.',
        ])
        ->assertJsonValidationErrors('date');
});

it('hạn mức về sớm tách riêng khỏi hạn mức đi muộn', function (): void {
    /*
    | Công ty đã chốt: hai hạn mức riêng. Dùng hết quota đi muộn không được làm
    | mất quyền xin về sớm — đó là hai chuyện khác nhau.
    */
    config()->set('leave.late_arrival_max_per_month', 1);
    config()->set('leave.early_leave_max_per_month', 1);

    $u = nhanVienVeSom();

    $this->actingAs($u)->postJson('/api/v1/late-arrivals', [
        'type' => 'late',
        'date' => '2026-08-13',
        'expected_arrival' => '09:30',
        'reason' => 'Đưa con đi khám buổi sáng.',
    ])->assertCreated();

    // Hết quota đi muộn, nhưng quota về sớm vẫn còn nguyên.
    $this->actingAs($u)
        ->postJson('/api/v1/late-arrivals', [
            'type' => 'late',
            'date' => '2026-08-14',
            'expected_arrival' => '09:30',
            'reason' => 'Đưa con đi khám buổi sáng.',
        ])
        ->assertJsonValidationErrors('date');

    $this->actingAs($u)->postJson('/api/v1/late-arrivals', [
        'type' => 'early',
        'date' => '2026-08-14',
        'expected_departure' => '16:00',
        'reason' => 'Chiều đón con ở trường.',
    ])->assertCreated();
});

it('bảng công đếm số phút về sớm, có ân hạn 5 phút', function (): void {
    $u = nhanVienVeSom();

    // 08:15 + 480 phút = 16:15 → sớm 75 phút so với 17:30.
    coGioLamTu($u, '2026-08-12', '08:15', 480);

    $o = $this->actingAs($u)
        ->getJson('/api/v1/attendance/me?month=2026-08')
        ->assertOk()
        ->json('data.cells.2026-08-12');

    expect($o['early_leave_minutes'])->toBe(75)
        ->and($o['early_leave_excused'])->toBeFalse();
});

it('về sớm trong ân hạn thì không tính', function (): void {
    // Ân hạn 5 phút: rời lúc 17:27 là sớm 3 phút — không đáng làm đơn, nên
    // không đáng hiện lên bảng công.
    $u = nhanVienVeSom();

    coGioLamTu($u, '2026-08-12', '08:15', 552); // 08:15 + 9h12 = 17:27

    $o = $this->actingAs($u)
        ->getJson('/api/v1/attendance/me?month=2026-08')
        ->json('data.cells.2026-08-12');

    expect($o['early_leave_minutes'])->toBe(0);
});

it('đơn về sớm đã duyệt chỉ bao TỪ đúng giờ đã xin', function (): void {
    /*
    | Đối xứng với luật của đơn đi muộn: xin về lúc 16h mà 14h đã tắt máy thì
    | phần sớm hơn vẫn là về sớm. Bỏ luật này thì một đơn duy nhất biến thành
    | giấy thông hành cho cả buổi chiều.
    */
    [$sep, $nv] = sepVaNhanVien();

    $tao = $this->actingAs($nv)->postJson('/api/v1/late-arrivals', [
        'type' => 'early',
        'date' => '2026-08-12',
        'expected_departure' => '16:00',
        'reason' => 'Đưa con đi khám buổi chiều.',
    ])->assertCreated();

    $this->actingAs($sep)
        ->postJson("/api/v1/late-arrivals/{$tao->json('data.id')}/review", ['approve' => true])
        ->assertOk();

    // Về đúng 16:00 → được miễn.
    coGioLamTu($nv, '2026-08-12', '08:15', 465);

    $o = $this->actingAs($nv)
        ->getJson('/api/v1/attendance/me?month=2026-08')
        ->json('data.cells.2026-08-12');

    expect($o['early_leave_minutes'])->toBe(90)
        ->and($o['early_leave_excused'])->toBeTrue();
});
