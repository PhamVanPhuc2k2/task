<?php

declare(strict_types=1);

use App\Domain\Identity\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use PragmaRX\Google2FA\Google2FA;

/*
|--------------------------------------------------------------------------
| Đổi kênh xác thực hai lớp trên hệ thống đang chạy
|--------------------------------------------------------------------------
|
| README khuyến nghị chuyển `TWO_FACTOR_DRIVER=totp` khi số nhân sự tăng, để bỏ
| email ra khỏi đường đăng nhập. Bộ test này kiểm điều PHẢI đúng khi làm việc
| đó: **không ai bị khoá ra ngoài**.
|
| Người đã bật OTP qua email có `two_factor_confirmed_at` nhưng KHÔNG có
| `two_factor_secret` — kênh email chẳng lưu secret nào. Nếu hệ thống chỉ nhìn
| cột `confirmed_at` để quyết định thì sau khi đổi driver, họ bị đẩy thẳng sang
| màn nhập mã TOTP mà không có mã nào để nhập.
|
*/

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->withHeader('Origin', 'http://localhost:3000');
    RateLimiter::clear('login:cu@congty.vn|127.0.0.1');
});

/** Người đã bật OTP qua EMAIL: có confirmed_at, không có secret. */
function nguoiDungOtpEmail(): User
{
    config()->set('two-factor.driver', 'email');

    return User::factory()->create([
        'email' => 'cu@congty.vn',
        'password' => Hash::make('MatKhauDung@2026'),
        'two_factor_secret' => null,
        'two_factor_confirmed_at' => now(),
    ]);
}

it('đổi sang TOTP thì người dùng email OTP được đưa đi thiết lập lại, KHÔNG bị khoá', function (): void {
    /*
    | Đây là test quan trọng nhất của file.
    |
    | Nếu nó đỏ với `two_factor_required = true` thì nghĩa là: đổi một dòng
    | trong .env sẽ khoá TOÀN BỘ công ty ra ngoài — kể cả quản trị viên, nên
    | không còn ai vào được để sửa. Đường thoát duy nhất là chạy tay trong
    | database.
    */
    $u = nguoiDungOtpEmail();

    config()->set('two-factor.driver', 'totp');

    $this->postJson('/api/v1/auth/login', [
        'email' => $u->email,
        'password' => 'MatKhauDung@2026',
    ])
        ->assertOk()
        ->assertJsonPath('data.two_factor_setup_required', true)
        ->assertJsonMissingPath('data.two_factor_required');
});

it('người đã thiết lập TOTP đầy đủ vẫn vào thẳng màn nhập mã', function (): void {
    config()->set('two-factor.driver', 'totp');

    $u = User::factory()->create([
        'email' => 'cu@congty.vn',
        'password' => Hash::make('MatKhauDung@2026'),
        'two_factor_secret' => app(Google2FA::class)->generateSecretKey(),
        'two_factor_confirmed_at' => now(),
    ]);

    $this->postJson('/api/v1/auth/login', [
        'email' => $u->email,
        'password' => 'MatKhauDung@2026',
    ])
        ->assertOk()
        ->assertJsonPath('data.two_factor_required', true);
});

it('kênh email không đòi secret — quay ngược driver cũng không khoá ai', function (): void {
    // Đổi sang TOTP rồi đổi ngược lại là kịch bản thật khi thử nghiệm.
    $u = nguoiDungOtpEmail();

    config()->set('two-factor.driver', 'email');

    $this->postJson('/api/v1/auth/login', [
        'email' => $u->email,
        'password' => 'MatKhauDung@2026',
    ])
        ->assertOk()
        ->assertJsonPath('data.two_factor_required', true);
});
