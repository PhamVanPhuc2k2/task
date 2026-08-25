<?php

declare(strict_types=1);

use App\Domain\Identity\Enums\Role;
use App\Domain\Identity\Models\LoginAttempt;
use App\Domain\Identity\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use PragmaRX\Google2FA\Google2FA;

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    RateLimiter::clear('login:an@congty.vn|127.0.0.1');

    // Origin phải khớp domain frontend trong SANCTUM_STATEFUL_DOMAINS để Sanctum
    // coi đây là request từ SPA và bật middleware phiên — đúng như trình duyệt
    // thật. Thiếu thì Sanctum rơi về chế độ token và không có session.
    $this->withHeader('Origin', 'http://localhost:3000');
});

/** Nhân viên dùng chung cho các test đăng nhập trong file này. */
function nhanVien(bool $dangLamViec = true): User
{
    /** @var User */
    return User::factory()->create([
        'email' => 'an@congty.vn',
        'password' => Hash::make('MatKhauDung@2026'),
        'is_active' => $dangLamViec,
        'terminated_at' => $dangLamViec ? null : now(),
    ]);
}

it('mật khẩu đúng chưa cho vào ngay, còn phải qua bước xác thực hai lớp', function (): void {
    // Hệ thống bắt buộc OTP với mọi tài khoản. Bước mật khẩu chỉ mở đường sang
    // bước hai, không tạo phiên đăng nhập. Xem TwoFactorTest cho luồng đầy đủ.
    nhanVien();

    $this->postJson('/api/v1/auth/login', [
        'email' => 'an@congty.vn',
        'password' => 'MatKhauDung@2026',
    ])
        ->assertOk()
        // Đi THẲNG sang bước nhập mã, kể cả với người chưa từng đăng nhập.
        //
        // Kênh email không có gì để thiết lập: địa chỉ đã nằm sẵn trên tài
        // khoản. Bản trước đẩy nhân viên mới qua một màn "Bảo vệ tài khoản"
        // làm đúng việc mà bước này cũng làm — gửi mã sáu số — rồi bắt lưu một
        // danh sách mã khôi phục. Xem EmailOtpProvider::isEnrolled.
        ->assertJsonPath('data.two_factor_required', true)
        ->assertJsonMissingPath('data.two_factor_setup_required')
        // Chưa được lộ thông tin người dùng ở bước này.
        ->assertJsonMissingPath('data.email');

    $this->assertGuest('web');
});

it('từ chối mật khẩu sai bằng thông báo tiếng Việt theo định dạng thống nhất', function (): void {
    nhanVien();

    $this->postJson('/api/v1/auth/login', [
        'email' => 'an@congty.vn',
        'password' => 'sai-be-bet',
    ])
        ->assertStatus(422)
        ->assertJsonPath('code', 'INVALID_CREDENTIALS')
        ->assertJsonStructure(['message', 'code']);

    $this->assertGuest();
});

it('trả lỗi validate theo đúng định dạng thống nhất', function (): void {
    // Dạng lỗi này là hợp đồng với frontend — xem README mục 1.4.
    $this->postJson('/api/v1/auth/login', ['email' => 'khong-phai-email'])
        ->assertStatus(422)
        ->assertJsonPath('code', 'VALIDATION_FAILED')
        ->assertJsonStructure(['message', 'code', 'errors' => ['email', 'password']]);
});

it('chặn tài khoản đã nghỉ việc kể cả khi mật khẩu đúng', function (): void {
    nhanVien(dangLamViec: false);

    $this->postJson('/api/v1/auth/login', [
        'email' => 'an@congty.vn',
        'password' => 'MatKhauDung@2026',
    ])
        ->assertStatus(403)
        ->assertJsonPath('code', 'ACCOUNT_DISABLED');

    $this->assertGuest();
});

it('ghi nhật ký cả lần đăng nhập thành công lẫn thất bại', function (): void {
    // Nhật ký đăng nhập là yêu cầu ở mục 1.9 Bảo mật: phải biết ai đăng nhập
    // từ đâu, và ai đang bị dò mật khẩu.
    nhanVien();

    $this->postJson('/api/v1/auth/login', ['email' => 'an@congty.vn', 'password' => 'sai']);
    $this->postJson('/api/v1/auth/login', ['email' => 'an@congty.vn', 'password' => 'MatKhauDung@2026']);

    $lanDau = LoginAttempt::query()->oldest('id')->firstOrFail();
    $lanSau = LoginAttempt::query()->latest('id')->firstOrFail();

    expect(LoginAttempt::query()->count())->toBe(2)
        ->and($lanDau->successful)->toBeFalse()
        ->and($lanDau->failure_reason)->toBe('invalid_credentials')
        ->and($lanDau->email)->toBe('an@congty.vn')
        ->and($lanSau->successful)->toBeTrue()
        ->and($lanSau->ip_address)->not->toBeNull();
});

it('khoá tạm sau nhiều lần sai liên tiếp', function (): void {
    nhanVien();

    foreach (range(1, 5) as $ignored) {
        $this->postJson('/api/v1/auth/login', ['email' => 'an@congty.vn', 'password' => 'sai']);
    }

    $this->postJson('/api/v1/auth/login', [
        'email' => 'an@congty.vn',
        'password' => 'MatKhauDung@2026',
    ])
        ->assertStatus(429)
        ->assertJsonPath('code', 'TOO_MANY_ATTEMPTS');
});

it('trả về thông tin người dùng hiện tại kèm vai trò và quyền', function (): void {
    $user = nhanVien();
    $user->assignRole(Role::TruongPhong->value);

    $this->actingAs($user)
        ->getJson('/api/v1/auth/me')
        ->assertOk()
        ->assertJsonPath('data.email', 'an@congty.vn')
        ->assertJsonPath('data.roles', [Role::TruongPhong->value])
        ->assertJsonFragment(['task.assign']);
});

it('từ chối khi chưa đăng nhập', function (): void {
    $this->getJson('/api/v1/auth/me')
        ->assertStatus(401)
        ->assertJsonPath('code', 'UNAUTHENTICATED');
});

it('đăng xuất làm mất phiên', function (): void {
    // Đăng nhập thật qua cả hai bước chứ không dùng actingAs: actingAs ép sẵn
    // user vào guard nên không kiểm được vòng đời phiên.
    //
    // Dùng kênh TOTP cho test này để sinh mã tại chỗ, khỏi phải qua hộp thư —
    // thứ đang kiểm là vòng đời phiên, không phải kênh xác thực.
    config()->set('two-factor.driver', 'totp');

    $secret = app(Google2FA::class)->generateSecretKey();

    $user = User::factory()->create([
        'email' => 'an@congty.vn',
        'password' => Hash::make('MatKhauDung@2026'),
        'two_factor_secret' => $secret,
        'two_factor_confirmed_at' => now(),
    ]);

    $this->postJson('/api/v1/auth/login', [
        'email' => 'an@congty.vn',
        'password' => 'MatKhauDung@2026',
    ])->assertOk();

    $this->postJson('/api/v1/auth/two-factor-challenge', [
        'code' => app(Google2FA::class)->getCurrentOtp($secret),
    ])->assertOk();

    // Chỉ rõ guard 'web': middleware `auth:sanctum` gọi Auth::shouldUse('sanctum'),
    // nên guard mặc định sau request không còn là 'web'. Phiên cookie nằm ở
    // guard 'web' — đó mới là thứ cần kiểm.
    $this->assertAuthenticatedAs($user, 'web');

    $this->postJson('/api/v1/auth/logout')->assertNoContent();

    $this->assertGuest('web');
});
