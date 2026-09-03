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

/*
|--------------------------------------------------------------------------
| Biểu tượng (favicon)
|--------------------------------------------------------------------------
|
| Tách hẳn khỏi logo, và đó là điểm chính. Logo công ty thường nằm ngang —
| một dấu hiệu cộng với tên viết bằng chữ. Ảnh đó co xuống 16×16 pixel trên
| tab trình duyệt thì thành vệt mờ, chữ biến mất trước tiên. Nên biểu tượng
| có ô tải riêng với ràng buộc riêng: vuông, và chỉ nhận định dạng có nền
| trong suốt.
|
*/

it('tải biểu tượng lên và trả về đường dẫn công khai', function (): void {
    Storage::fake('public');

    $duong = $this->actingAs(giamDoc())
        ->post('/api/v1/settings/icon', [
            'icon' => UploadedFile::fake()->image('icon.png', 256, 256),
        ])
        ->assertOk()
        ->json('data.icon_url');

    expect($duong)->toBeString()->not->toContain('X-Amz-Signature');

    // Và nó phải lộ ra ở đường công khai, vì màn cài đặt xem trước bằng đó.
    $this->getJson('/api/v1/site')->assertOk()->assertJsonPath('data.icon_url', $duong);
});

it('từ chối biểu tượng không vuông', function (): void {
    /*
    | Ràng buộc quan trọng nhất của tính năng này. Trình duyệt không cắt ảnh,
    | nó BÓP — một logo ngang lọt qua đây sẽ thành biểu tượng méo trên mọi tab,
    | và không có gì báo lỗi. Đây đúng là lý do biểu tượng không dùng chung ô
    | tải với logo.
    */
    Storage::fake('public');

    $this->actingAs(giamDoc())
        ->post('/api/v1/settings/icon', [
            'icon' => UploadedFile::fake()->image('logo-ngang.png', 512, 128),
        ])
        ->assertJsonValidationErrors('icon');
});

it('từ chối JPG làm biểu tượng', function (): void {
    // JPG không có nền trong suốt: biểu tượng sẽ là một ô vuông trắng trên
    // thanh tab nền tối. Logo thì nhận JPG được, vì nó nằm trên nền của trang.
    Storage::fake('public');

    $this->actingAs(giamDoc())
        ->post('/api/v1/settings/icon', [
            'icon' => UploadedFile::fake()->image('icon.jpg', 256, 256),
        ])
        ->assertJsonValidationErrors('icon');
});

it('nhân viên thường không đổi được biểu tượng', function (): void {
    Storage::fake('public');

    $u = User::factory()->create();
    $u->assignRole(Role::NhanVien->value);

    $this->actingAs($u)
        ->post('/api/v1/settings/icon', [
            'icon' => UploadedFile::fake()->image('icon.png', 256, 256),
        ])
        ->assertForbidden();
});

it('đường biểu tượng luôn trả về một ảnh, kể cả khi chưa ai đặt', function (): void {
    /*
    | Không đăng nhập vẫn phải gọi được: trình duyệt xin biểu tượng của tab
    | trước cả trang đăng nhập, và không kèm cookie nào.
    |
    | Trả 404 ở đây thì trình duyệt hiện biểu tượng trang trắng và NHỚ điều đó
    | rất lâu — tệ hơn hẳn việc chuyển hướng về ảnh mặc định.
    */
    $this->get('/api/v1/site/icon')
        ->assertRedirect(rtrim((string) config('app.frontend_url'), '/').'/icon.svg');
});

it('đường biểu tượng trỏ sang ảnh đã tải lên', function (): void {
    Storage::fake('public');

    $duong = $this->actingAs(giamDoc())
        ->post('/api/v1/settings/icon', [
            'icon' => UploadedFile::fake()->image('icon.png', 256, 256),
        ])
        ->json('data.icon_url');

    $this->get('/api/v1/site/icon')->assertRedirect($duong);
});

it('xoá biểu tượng thì quay về ảnh mặc định', function (): void {
    Storage::fake('public');

    $this->actingAs(giamDoc())
        ->post('/api/v1/settings/icon', [
            'icon' => UploadedFile::fake()->image('icon.png', 256, 256),
        ])
        ->assertOk();

    $this->actingAs(giamDoc())
        ->deleteJson('/api/v1/settings/icon')
        ->assertOk()
        ->assertJsonPath('data.icon_url', null);

    $this->get('/api/v1/site/icon')
        ->assertRedirect(rtrim((string) config('app.frontend_url'), '/').'/icon.svg');
});

it('không đặt được đường dẫn ảnh qua form cài đặt', function (): void {
    /*
    | `values` có luật của chính nó (`array`), nên `validated()` trả về CẢ mảng
    | — kể cả khoá không có luật riêng. Chỉ bỏ qua `logo_path` khi dựng luật là
    | chưa đủ: nó vẫn đi thẳng xuống `setRaw()` và ghi đè đường dẫn tệp bằng
    | chuỗi tuỳ ý, im lặng. Ảnh chỉ được đặt qua đường tải tệp.
    */
    foreach (['logo_path', 'icon_path'] as $khoa) {
        $this->actingAs(giamDoc())
            ->putJson('/api/v1/settings', ['values' => [$khoa => 'branding/gia-mao.png']])
            ->assertJsonValidationErrors("values.{$khoa}");
    }

    expect(app(SiteSettings::class)->get(SettingKey::LogoPath))->toBeNull()
        ->and(app(SiteSettings::class)->get(SettingKey::IconPath))->toBeNull();
});
