<?php

declare(strict_types=1);

use App\Domain\Identity\Enums\Role;
use App\Domain\Identity\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Testing\TestResponse;
use PragmaRX\Google2FA\Google2FA;

const MAT_KHAU = 'MatKhauDung@2026';

beforeEach(function (): void {
    // File này kiểm kênh TOTP. Kênh mặc định của hệ thống là email — xem
    // EmailOtpTest. Không ghim ở đây thì test chạy nhầm kênh.
    config()->set('two-factor.driver', 'totp');

    $this->seed(RolePermissionSeeder::class);
    // Origin phải là domain của frontend và phải nằm trong SANCTUM_STATEFUL_DOMAINS,
    // nếu không Sanctum rơi về chế độ token và request không có session —
    // trong khi luồng đăng nhập hai bước bắt buộc cần session.
    $this->withHeader('Origin', 'http://localhost:3000');
    RateLimiter::clear('login:nv@congty.vn|127.0.0.1');
});

/**
 * Nhân viên đã bật OTP xong.
 *
 * @return array{User, string} [người dùng, khoá bí mật TOTP]
 */
function nhanVienCoOtp(): array
{
    $secret = app(Google2FA::class)->generateSecretKey();

    $user = User::factory()->create([
        'email' => 'nv@congty.vn',
        'password' => Hash::make(MAT_KHAU),
        'two_factor_secret' => $secret,
        'two_factor_recovery_codes' => ['AAAAA-11111', 'BBBBB-22222'],
        'two_factor_confirmed_at' => now(),
    ]);
    $user->assignRole(Role::NhanVien->value);

    return [$user, $secret];
}

function maHopLe(string $secret): string
{
    return app(Google2FA::class)->getCurrentOtp($secret);
}

/** @return TestResponse<Response> */
function dangNhapBuocMot(): TestResponse
{
    return test()->postJson('/api/v1/auth/login', [
        'email' => 'nv@congty.vn',
        'password' => MAT_KHAU,
    ]);
}

/*
|--------------------------------------------------------------------------
| Mật khẩu đúng vẫn chưa được vào
|--------------------------------------------------------------------------
*/

it('không đăng nhập ngay khi mới nhập đúng mật khẩu, mà đòi mã OTP', function (): void {
    nhanVienCoOtp();

    dangNhapBuocMot()
        ->assertOk()
        ->assertJsonPath('data.two_factor_required', true)
        // Chưa được lộ bất kỳ thông tin nào của người dùng ở bước này.
        ->assertJsonMissingPath('data.email');

    $this->assertGuest('web');
});

it('vào được sau khi nhập đúng mã OTP', function (): void {
    [$user, $secret] = nhanVienCoOtp();

    dangNhapBuocMot()->assertOk();

    $this->postJson('/api/v1/auth/two-factor-challenge', ['code' => maHopLe($secret)])
        ->assertOk()
        ->assertJsonPath('data.email', 'nv@congty.vn');

    $this->assertAuthenticatedAs($user, 'web');
});

it('từ chối mã OTP sai', function (): void {
    nhanVienCoOtp();
    dangNhapBuocMot()->assertOk();

    $this->postJson('/api/v1/auth/two-factor-challenge', ['code' => '000000'])
        ->assertStatus(422)
        ->assertJsonPath('code', 'INVALID_TWO_FACTOR_CODE');

    $this->assertGuest('web');
});

it('không cho nhập mã OTP khi chưa qua bước mật khẩu', function (): void {
    [, $secret] = nhanVienCoOtp();

    $this->postJson('/api/v1/auth/two-factor-challenge', ['code' => maHopLe($secret)])
        ->assertStatus(422)
        ->assertJsonPath('code', 'NO_PENDING_LOGIN');
});

it('khoá tạm sau nhiều lần nhập sai mã OTP', function (): void {
    nhanVienCoOtp();
    dangNhapBuocMot()->assertOk();

    foreach (range(1, 5) as $ignored) {
        $this->postJson('/api/v1/auth/two-factor-challenge', ['code' => '000000']);
    }

    $this->postJson('/api/v1/auth/two-factor-challenge', ['code' => '000000'])
        ->assertStatus(429);
});

/*
|--------------------------------------------------------------------------
| Mã khôi phục
|--------------------------------------------------------------------------
*/

it('vào được bằng mã khôi phục khi mất điện thoại', function (): void {
    [$user] = nhanVienCoOtp();
    dangNhapBuocMot()->assertOk();

    $this->postJson('/api/v1/auth/two-factor-challenge', ['recovery_code' => 'AAAAA-11111'])
        ->assertOk();

    $this->assertAuthenticatedAs($user, 'web');
});

it('mã khôi phục chỉ dùng được một lần', function (): void {
    [$user] = nhanVienCoOtp();

    dangNhapBuocMot();
    $this->postJson('/api/v1/auth/two-factor-challenge', ['recovery_code' => 'AAAAA-11111'])->assertOk();

    expect($user->refresh()->two_factor_recovery_codes)->toBe(['BBBBB-22222']);

    $this->postJson('/api/v1/auth/logout');
    dangNhapBuocMot();

    $this->postJson('/api/v1/auth/two-factor-challenge', ['recovery_code' => 'AAAAA-11111'])
        ->assertStatus(422);
});

/*
|--------------------------------------------------------------------------
| Nhân viên mới bắt buộc thiết lập OTP
|--------------------------------------------------------------------------
*/

it('bắt nhân viên chưa có OTP phải thiết lập trước khi vào được', function (): void {
    User::factory()->create([
        'email' => 'nv@congty.vn',
        'password' => Hash::make(MAT_KHAU),
        'two_factor_confirmed_at' => null,
    ]);

    dangNhapBuocMot()
        ->assertOk()
        ->assertJsonPath('data.two_factor_setup_required', true);

    $this->assertGuest('web');
});

it('trả về mã QR và secret để quét bằng ứng dụng xác thực', function (): void {
    User::factory()->create([
        'email' => 'nv@congty.vn',
        'password' => Hash::make(MAT_KHAU),
        'two_factor_confirmed_at' => null,
    ]);
    dangNhapBuocMot();

    $response = $this->getJson('/api/v1/auth/two-factor/setup')->assertOk();

    expect($response->json('data.secret'))->toBeString()
        ->and($response->json('data.qr_code_svg'))->toContain('<svg');
});

it('bật OTP và đăng nhập luôn sau khi nhập đúng mã lần đầu', function (): void {
    $user = User::factory()->create([
        'email' => 'nv@congty.vn',
        'password' => Hash::make(MAT_KHAU),
        'two_factor_confirmed_at' => null,
    ]);
    dangNhapBuocMot();

    $secret = $this->getJson('/api/v1/auth/two-factor/setup')->json('data.secret');

    $response = $this->postJson('/api/v1/auth/two-factor/confirm', [
        'code' => maHopLe($secret),
    ])->assertOk();

    // Mã khôi phục chỉ hiện đúng một lần này.
    expect($response->json('data.recovery_codes'))->toHaveCount(8);

    $user->refresh();

    expect($user->two_factor_confirmed_at)->not->toBeNull();
    $this->assertAuthenticatedAs($user, 'web');
});

it('không bật OTP khi nhập sai mã lúc thiết lập', function (): void {
    // Quét QR hỏng mà vẫn bật là khoá người dùng ra ngoài vĩnh viễn.
    $user = User::factory()->create([
        'email' => 'nv@congty.vn',
        'password' => Hash::make(MAT_KHAU),
        'two_factor_confirmed_at' => null,
    ]);
    dangNhapBuocMot();
    $this->getJson('/api/v1/auth/two-factor/setup');

    $this->postJson('/api/v1/auth/two-factor/confirm', ['code' => '000000'])
        ->assertStatus(422);

    expect($user->refresh()->two_factor_confirmed_at)->toBeNull();
    $this->assertGuest('web');
});

/*
|--------------------------------------------------------------------------
| Quản trị viên gỡ OTP hộ
|--------------------------------------------------------------------------
*/

it('quản trị viên gỡ được OTP cho nhân viên mất điện thoại', function (): void {
    [$nhanVien] = nhanVienCoOtp();

    $admin = User::factory()->create();
    $admin->assignRole(Role::Admin->value);

    $this->actingAs($admin)
        ->postJson("/api/v1/users/{$nhanVien->uuid}/reset-two-factor")
        ->assertNoContent();

    $nhanVien->refresh();

    expect($nhanVien->two_factor_confirmed_at)->toBeNull()
        ->and($nhanVien->two_factor_secret)->toBeNull();
});

it('nhân viên thường không được gỡ OTP của người khác', function (): void {
    [$nhanVien] = nhanVienCoOtp();

    $nguoiKhac = User::factory()->create();
    $nguoiKhac->assignRole(Role::NhanVien->value);

    $this->actingAs($nguoiKhac)
        ->postJson("/api/v1/users/{$nhanVien->uuid}/reset-two-factor")
        ->assertStatus(403);
});
