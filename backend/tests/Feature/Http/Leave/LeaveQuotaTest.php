<?php

declare(strict_types=1);

use App\Domain\Identity\Enums\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Leave\Enums\LeaveStatus;
use App\Domain\Leave\Enums\LeaveType;
use App\Domain\Leave\Models\LateArrivalRequest;
use App\Domain\Leave\Models\LeaveRequest;
use App\Support\Settings\SettingKey;
use App\Support\Settings\SiteSettings;
use Carbon\CarbonImmutable;
use Database\Seeders\RolePermissionSeeder;

/*
|--------------------------------------------------------------------------
| Hạn mức nghỉ không lương và xin đi muộn
|--------------------------------------------------------------------------
|
| Hai chu kỳ khác nhau, có lý do: nghỉ không lương đếm theo NĂM vì nó hiếm và
| dài ngày; xin đi muộn đếm theo THÁNG vì đó là chuyện lặt vặt lặp lại, mà hạn
| mức năm thì người ta dùng hết từ tháng ba rồi cả năm còn lại không xin được.
|
| Chặn ở bước NỘP chứ không ở bước duyệt. Đây là chỗ hạn mức khác với chấm công:
| giờ công thì hệ thống đo và gắn cờ để con người quyết định, vì phép đo có thể
| sai. Hạn mức thì không có gì để sai — nó là con số giám đốc đặt ra, và số ngày
| đã dùng là dữ liệu chắc chắn.
|
| Mốc thời gian: 12/08/2026, thứ Tư.
|
*/

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->withHeader('Origin', 'http://localhost:3000');
    $this->travelTo(CarbonImmutable::parse('2026-08-12 09:00:00'));
});

function nhanVienXinNghi(): User
{
    $u = User::factory()->create();
    $u->assignRole(Role::NhanVien->value);

    return $u;
}

/** Tạo sẵn một đơn nghỉ không lương ở trạng thái bất kỳ. */
function donKhongLuong(User $u, string $tu, string $den, LeaveStatus $trangThai): LeaveRequest
{
    return LeaveRequest::query()->create([
        'user_id' => $u->id,
        'type' => LeaveType::Unpaid,
        'start_date' => $tu,
        'end_date' => $den,
        'reason' => 'Việc gia đình cần giải quyết.',
        'status' => $trangThai,
    ]);
}

it('chặn đơn nghỉ không lương vượt hạn mức năm', function (): void {
    config()->set('leave.unpaid_max_days_per_year', 10);

    $u = nhanVienXinNghi();

    // Đã duyệt 8 ngày (01–08/03).
    donKhongLuong($u, '2026-03-01', '2026-03-08', LeaveStatus::Approved);

    $this->actingAs($u)
        ->postJson('/api/v1/leave', [
            'type' => 'unpaid',
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-04',
            'reason' => 'Về quê có việc gấp.',
        ])
        ->assertJsonValidationErrors('start_date');

    // Câu chữ phải nói ra CẢ BA con số, nếu không người đọc không biết sửa gì.
    $loi = $this->actingAs($u)
        ->postJson('/api/v1/leave', [
            'type' => 'unpaid',
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-04',
            'reason' => 'Về quê có việc gấp.',
        ])
        ->json('errors.start_date.0');

    expect($loi)->toContain('8/10')
        ->and($loi)->toContain('4 ngày');
});

it('vẫn cho nộp khi còn vừa đủ hạn mức', function (): void {
    // Lưới an toàn cho test trên: nếu phép đếm hỏng hẳn thì test kia vẫn xanh.
    config()->set('leave.unpaid_max_days_per_year', 10);

    $u = nhanVienXinNghi();
    donKhongLuong($u, '2026-03-01', '2026-03-08', LeaveStatus::Approved);

    $this->actingAs($u)
        ->postJson('/api/v1/leave', [
            'type' => 'unpaid',
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-02',
            'reason' => 'Về quê có việc gấp.',
        ])
        ->assertCreated();
});

it('đơn ĐANG CHỜ DUYỆT cũng tính vào hạn mức', function (): void {
    /*
    | Chỗ dễ sai nhất. Chỉ đếm đơn đã duyệt thì nộp năm đơn nhỏ cùng lúc là lách
    | được — mỗi đơn nhìn riêng đều nằm trong hạn mức, và người duyệt phải tự
    | cộng nhẩm.
    */
    config()->set('leave.unpaid_max_days_per_year', 10);

    $u = nhanVienXinNghi();
    donKhongLuong($u, '2026-03-01', '2026-03-09', LeaveStatus::Pending);

    $this->actingAs($u)
        ->postJson('/api/v1/leave', [
            'type' => 'unpaid',
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-03',
            'reason' => 'Về quê có việc gấp.',
        ])
        ->assertJsonValidationErrors('start_date');
});

it('đơn bị từ chối thì trả lại hạn mức', function (): void {
    config()->set('leave.unpaid_max_days_per_year', 10);

    $u = nhanVienXinNghi();
    donKhongLuong($u, '2026-03-01', '2026-03-09', LeaveStatus::Rejected);

    $this->actingAs($u)
        ->postJson('/api/v1/leave', [
            'type' => 'unpaid',
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-09',
            'reason' => 'Về quê có việc gấp.',
        ])
        ->assertCreated();
});

it('đơn vắt qua giao thừa chia phần cho đúng hai năm', function (): void {
    /*
    | Đơn 28/12 → 03/01 tính 4 ngày cho năm cũ và 3 ngày cho năm mới, chứ không
    | dồn cả 7 ngày vào năm bắt đầu. Dồn hết một bên nghĩa là nghỉ cuối năm bị
    | tính nặng hơn nghỉ giữa năm, mà không có lý do nào để như vậy.
    |
    | Hệ quả: đơn phải lọt hạn mức của CẢ HAI năm.
    */
    config()->set('leave.unpaid_max_days_per_year', 5);

    $u = nhanVienXinNghi();

    // Năm 2026 đã dùng 2 ngày, năm 2027 chưa dùng gì.
    donKhongLuong($u, '2026-05-01', '2026-05-02', LeaveStatus::Approved);

    // Đơn này cần 4 ngày cho 2026 (28–31/12) và 3 ngày cho 2027 (01–03/01).
    // 2026: 2 + 4 = 6 > 5  →  chặn, và phải báo đúng năm 2026.
    $loi = $this->actingAs($u)
        ->postJson('/api/v1/leave', [
            'type' => 'unpaid',
            'start_date' => '2026-12-28',
            'end_date' => '2027-01-03',
            'reason' => 'Về quê ăn Tết dương lịch.',
        ])
        ->assertJsonValidationErrors('start_date')
        ->json('errors.start_date.0');

    expect($loi)->toContain('2026');
});

it('chỉ áp cho nghỉ KHÔNG LƯƠNG, không áp cho phép năm hay nghỉ ốm', function (): void {
    // Phép năm sẽ có quỹ riêng ở đợt 4; nghỉ ốm là chuyện chính sách, không
    // phải một con số chặn cứng.
    config()->set('leave.unpaid_max_days_per_year', 1);

    $u = nhanVienXinNghi();

    $this->actingAs($u)
        ->postJson('/api/v1/leave', [
            'type' => 'annual',
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-10',
            'reason' => 'Nghỉ phép năm đã thoả thuận trước.',
        ])
        ->assertCreated();
});

it('đặt hạn mức 0 là tắt hẳn', function (): void {
    config()->set('leave.unpaid_max_days_per_year', 0);

    $u = nhanVienXinNghi();

    $this->actingAs($u)
        ->postJson('/api/v1/leave', [
            'type' => 'unpaid',
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-30',
            'reason' => 'Nghỉ dài đã thoả thuận với giám đốc.',
        ])
        ->assertCreated();
});

it('chặn xin đi muộn quá số lần trong tháng', function (): void {
    config()->set('leave.late_arrival_max_per_month', 2);

    $u = nhanVienXinNghi();

    foreach (['2026-08-17', '2026-08-18'] as $ngay) {
        LateArrivalRequest::query()->create([
            'user_id' => $u->id,
            'date' => $ngay,
            'expected_arrival' => '09:30',
            'reason' => 'Đưa con đi khám.',
            'status' => LeaveStatus::Approved,
        ]);
    }

    $loi = $this->actingAs($u)
        ->postJson('/api/v1/late-arrivals', [
            'date' => '2026-08-19',
            'expected_arrival' => '09:30',
            'reason' => 'Đưa con đi khám.',
        ])
        ->assertJsonValidationErrors('date')
        ->json('errors.date.0');

    expect($loi)->toContain('2/2');
});

it('sang tháng mới thì hạn mức đi muộn làm lại từ đầu', function (): void {
    // Đây là điểm chính của việc đếm theo tháng: hạn mức năm thì người ta dùng
    // hết từ tháng ba rồi cả năm còn lại không xin được nữa.
    config()->set('leave.late_arrival_max_per_month', 2);

    $u = nhanVienXinNghi();

    foreach (['2026-08-17', '2026-08-18'] as $ngay) {
        LateArrivalRequest::query()->create([
            'user_id' => $u->id,
            'date' => $ngay,
            'expected_arrival' => '09:30',
            'reason' => 'Đưa con đi khám.',
            'status' => LeaveStatus::Approved,
        ]);
    }

    $this->actingAs($u)
        ->postJson('/api/v1/late-arrivals', [
            'date' => '2026-09-01',
            'expected_arrival' => '09:30',
            'reason' => 'Đưa con đi khám.',
        ])
        ->assertCreated();
});

it('hạn mức đi muộn của người này không ảnh hưởng người khác', function (): void {
    config()->set('leave.late_arrival_max_per_month', 1);

    $a = nhanVienXinNghi();
    $b = nhanVienXinNghi();

    LateArrivalRequest::query()->create([
        'user_id' => $a->id,
        'date' => '2026-08-17',
        'expected_arrival' => '09:30',
        'reason' => 'Đưa con đi khám.',
        'status' => LeaveStatus::Approved,
    ]);

    $this->actingAs($b)
        ->postJson('/api/v1/late-arrivals', [
            'date' => '2026-08-18',
            'expected_arrival' => '09:30',
            'reason' => 'Đưa con đi khám.',
        ])
        ->assertCreated();
});

it('giám đốc đổi được cả hai hạn mức', function (): void {
    $this->actingAs(giamDoc())
        ->putJson('/api/v1/settings', [
            'values' => [
                'leave_unpaid_max_days_year' => 20,
                'late_arrival_max_per_month' => 5,
            ],
        ])
        ->assertOk();

    // Đọc lại từ tầng cài đặt chứ không đọc `config()`: giá trị được nạp vào
    // Config một lần lúc khởi động ứng dụng, nên trong cùng một request nó vẫn
    // là giá trị cũ. Việc nạp đó có test riêng ở SiteSettingsTest.
    $s = app(SiteSettings::class);

    expect($s->get(SettingKey::LeaveUnpaidMaxDaysYear))->toBe(20)
        ->and($s->get(SettingKey::LateArrivalMaxPerMonth))->toBe(5);
});
