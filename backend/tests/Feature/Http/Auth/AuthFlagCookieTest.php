<?php

declare(strict_types=1);

use App\Domain\Identity\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Hash;
use PragmaRX\Google2FA\Google2FA;

/*
|--------------------------------------------------------------------------
| Cờ "đã đăng nhập" cho frontend
|--------------------------------------------------------------------------
|
| Các test này tồn tại vì một bug thật đã xảy ra: route guard phía Next.js ban
| đầu dùng cookie phiên của Laravel (`explus_session`) làm tín hiệu "đã đăng
| nhập". Nhưng Laravel cấp cookie đó cho MỌI người, kể cả khách vừa mở trang
| đăng nhập — nên `/login` đá về `/`, `/` phát hiện chưa đăng nhập lại đá về
| `/login`, quay vòng mãi và người dùng chỉ thấy màn hình trắng.
|
| Cờ `explus_auth` chỉ đặt khi đã đăng nhập THẬT SỰ.
|
*/

beforeEach(function (): void {
    config()->set('two-factor.driver', 'totp');
    $this->seed(RolePermissionSeeder::class);
    $this->withHeader('Origin', 'http://localhost:3000');
});

/** @return array{User, string} [người dùng, khoá bí mật TOTP] */
function nguoiDungCoTotp(): array
{
    $secret = app(Google2FA::class)->generateSecretKey();

    $user = User::factory()->create([
        'email' => 'co@congty.vn',
        'password' => Hash::make('MatKhauDung@2026'),
        'two_factor_secret' => $secret,
        'two_factor_confirmed_at' => now(),
    ]);

    return [$user, $secret];
}

it('không đặt cờ khi mới qua bước mật khẩu', function (): void {
    // Đây là mấu chốt: chưa xong xác thực hai lớp thì chưa phải đã đăng nhập.
    nguoiDungCoTotp();

    $this->postJson('/api/v1/auth/login', [
        'email' => 'co@congty.vn',
        'password' => 'MatKhauDung@2026',
    ])->assertOk()->assertCookieMissing('explus_auth');
});

it('không đặt cờ khi mật khẩu sai', function (): void {
    nguoiDungCoTotp();

    $this->postJson('/api/v1/auth/login', [
        'email' => 'co@congty.vn',
        'password' => 'sai-be-bet',
    ])->assertStatus(422)->assertCookieMissing('explus_auth');
});

it('đặt cờ sau khi qua đủ hai bước', function (): void {
    [, $secret] = nguoiDungCoTotp();

    $this->postJson('/api/v1/auth/login', [
        'email' => 'co@congty.vn',
        'password' => 'MatKhauDung@2026',
    ])->assertOk();

    $this->postJson('/api/v1/auth/two-factor-challenge', [
        'code' => app(Google2FA::class)->getCurrentOtp($secret),
    ])
        ->assertOk()
        ->assertCookie('explus_auth');
});

it('xoá cờ khi đăng xuất', function (): void {
    [, $secret] = nguoiDungCoTotp();

    $this->postJson('/api/v1/auth/login', [
        'email' => 'co@congty.vn',
        'password' => 'MatKhauDung@2026',
    ]);
    $this->postJson('/api/v1/auth/two-factor-challenge', [
        'code' => app(Google2FA::class)->getCurrentOtp($secret),
    ])->assertOk();

    $this->postJson('/api/v1/auth/logout')
        ->assertNoContent()
        ->assertCookieExpired('explus_auth');
});
