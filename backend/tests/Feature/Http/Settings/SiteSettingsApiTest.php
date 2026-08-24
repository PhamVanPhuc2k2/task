<?php

declare(strict_types=1);

use App\Domain\Identity\Enums\Role;
use App\Domain\Identity\Models\User;
use App\Support\Settings\SettingKey;
use App\Support\Settings\SiteSettings;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/*
|--------------------------------------------------------------------------
| API cài đặt trang
|--------------------------------------------------------------------------
|
| Đây là màn hình đổi được **cách tính công của cả công ty**. Nên phần kiểm dữ
| liệu ở đây quan trọng hơn phần lưu: một ca làm vô lý (tan làm trước giờ vào
| làm) không làm hệ thống báo lỗi — nó chỉ khiến mọi phép tính giờ ra số âm hoặc
| số 0, im lặng.
|
*/

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->withHeader('Origin', 'http://localhost:3000');
});

it('giám đốc đọc được toàn bộ cài đặt', function (): void {
    $this->actingAs(giamDoc())
        ->getJson('/api/v1/settings')
        ->assertOk()
        ->assertJsonPath('data.values.shift_morning_start', '08:15')
        ->assertJsonStructure(['data' => ['values', 'fields']]);
});

it('nhân viên thường không vào được', function (): void {
    $u = User::factory()->create();
    $u->assignRole(Role::NhanVien->value);

    $this->actingAs($u)->getJson('/api/v1/settings')->assertForbidden();
    $this->actingAs($u)
        ->putJson('/api/v1/settings', ['values' => ['company_name' => 'Đổi thử']])
        ->assertForbidden();
});

it('lưu được và có tác dụng ngay', function (): void {
    $this->actingAs(giamDoc())
        ->putJson('/api/v1/settings', [
            'values' => [
                'company_name' => 'HBR Holdings',
                'shift_morning_start' => '09:00',
                'shift_grace_minutes' => 5,
            ],
        ])
        ->assertOk();

    // Đọc lại từ tầng dưới, không tin phản hồi của chính lệnh vừa gọi.
    $s = app(SiteSettings::class);

    expect($s->get(SettingKey::CompanyName))->toBe('HBR Holdings')
        ->and($s->get(SettingKey::ShiftMorningStart))->toBe('09:00')
        ->and($s->get(SettingKey::ShiftGraceMinutes))->toBe(5);
});

it('từ chối ca làm vô lý — tan làm trước giờ vào làm', function (): void {
    /*
    | Không chặn thì không có gì báo lỗi cả: hệ thống vẫn lưu, vẫn chạy, và mọi
    | phép tính giờ công ra số âm hoặc số 0. Giám đốc chỉ biết khi có người thắc
    | mắc "sao tháng này ai cũng 0 giờ".
    */
    $this->actingAs(giamDoc())
        ->putJson('/api/v1/settings', [
            'values' => ['shift_morning_start' => '18:00', 'shift_end' => '09:00'],
        ])
        // Lỗi gắn vào `shift_morning_start` vì đó là vi phạm ĐẦU TIÊN theo thứ
        // tự bốn mốc (18:00 vào làm > 12:00 nghỉ trưa), và cũng là trường người
        // dùng vừa sửa. Câu lỗi hiện cạnh đúng ô họ đang gõ.
        ->assertJsonValidationErrors('values.shift_morning_start');
});

it('từ chối nghỉ trưa nằm ngoài ca làm', function (): void {
    $this->actingAs(giamDoc())
        ->putJson('/api/v1/settings', [
            'values' => ['shift_lunch_start' => '06:00'],
        ])
        ->assertJsonValidationErrors('values.shift_lunch_start');
});

it('từ chối khoá không có trong danh mục', function (): void {
    $this->actingAs(giamDoc())
        ->putJson('/api/v1/settings', ['values' => ['gio_lam' => '09:00']])
        ->assertJsonValidationErrors('values.gio_lam');
});

it('tải logo lên và trả về đường dẫn công khai', function (): void {
    /*
    | Logo phải nằm ở ổ CÔNG KHAI, không phải R2 ký có hạn: nó hiện trên trang
    | đăng nhập — tức là trước khi có ai xác thực. Một đường dẫn ký 30 phút thì
    | trang đăng nhập không lấy được.
    */
    Storage::fake('public');

    $duong = $this->actingAs(giamDoc())
        ->post('/api/v1/settings/logo', [
            'logo' => UploadedFile::fake()->image('logo.png', 200, 200),
        ])
        ->assertOk()
        ->json('data.logo_url');

    expect($duong)->toBeString()->not->toContain('X-Amz-Signature');
});

it('từ chối tệp không phải ảnh', function (): void {
    Storage::fake('public');

    $this->actingAs(giamDoc())
        ->post('/api/v1/settings/logo', [
            'logo' => UploadedFile::fake()->create('ke-toan.xlsx', 40),
        ])
        ->assertJsonValidationErrors('logo');
});

it('ai chưa đăng nhập cũng lấy được nhận diện, nhưng KHÔNG lấy được chính sách', function (): void {
    /*
    | Trang đăng nhập cần tên và logo, mà lúc đó chưa có phiên nào. Nên có một
    | đường công khai riêng — và nó chỉ trả nhận diện. Trả cả chính sách ở đây
    | là phơi giờ làm, cửa sổ nộp đơn và cấu hình nội bộ cho bất kỳ ai gọi.
    */
    app(SiteSettings::class)->set(SettingKey::CompanyName, 'HBR Holdings');

    $this->getJson('/api/v1/site')
        ->assertOk()
        ->assertJsonPath('data.company_name', 'HBR Holdings')
        ->assertJsonMissingPath('data.shift_morning_start')
        ->assertJsonMissingPath('data.leave_max_days');
});

it('ghi lại ai đổi lần cuối', function (): void {
    // Cài đặt đổi cả cách tính công của công ty. Không ghi người sửa thì sau
    // này không ai trả lời được "ai bấm nút này, hôm nào".
    $gd = giamDoc();

    $this->actingAs($gd)
        ->putJson('/api/v1/settings', ['values' => ['company_name' => 'HBR']])
        ->assertOk();

    $this->assertDatabaseHas('site_settings', [
        'key' => 'company_name',
        'updated_by' => $gd->id,
    ]);
});
