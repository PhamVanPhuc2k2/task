<?php

declare(strict_types=1);

use App\Domain\Identity\Mail\TwoFactorCodeMail;
use App\Domain\Identity\Models\TwoFactorCode;
use App\Domain\Identity\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;

const MAT_KHAU_EMAIL = 'MatKhauDung@2026';

beforeEach(function (): void {
    config()->set('two-factor.driver', 'email');
    $this->seed(RolePermissionSeeder::class);
    $this->withHeader('Origin', 'http://localhost:3000');
    RateLimiter::clear('login:nv@congty.vn|127.0.0.1');
    Mail::fake();
});

function nguoiDung(bool $daBat = true): User
{
    return User::factory()->create([
        'email' => 'nv@congty.vn',
        'password' => Hash::make(MAT_KHAU_EMAIL),
        'two_factor_confirmed_at' => $daBat ? now() : null,
    ]);
}

function buocMot(): void
{
    test()->postJson('/api/v1/auth/login', [
        'email' => 'nv@congty.vn',
        'password' => MAT_KHAU_EMAIL,
    ])->assertOk();
}

/**
 * Mã mới nhất đã gửi.
 *
 * Đọc từ email đã gửi chứ không từ database — database chỉ lưu bản băm, và đó
 * chính là điều cần giữ.
 */
function maVuaGui(): string
{
    $daGui = Mail::queued(TwoFactorCodeMail::class)
        ->map(fn (TwoFactorCodeMail $mail): string => $mail->code)
        ->values()
        ->all();

    return $daGui === [] ? '' : (string) end($daGui);
}

/*
|--------------------------------------------------------------------------
| Gửi mã
|--------------------------------------------------------------------------
*/

it('gửi mã sáu số tới email ngay khi nhập đúng mật khẩu', function (): void {
    $user = nguoiDung();

    buocMot();

    Mail::assertQueued(TwoFactorCodeMail::class, fn (TwoFactorCodeMail $mail): bool => $mail->hasTo('nv@congty.vn'));

    expect(TwoFactorCode::query()->where('user_id', $user->id)->count())->toBe(1)
        ->and(maVuaGui())->toMatch('/^\d{6}$/');
});

it('không lưu mã dạng rõ trong database', function (): void {
    // Mã OTP là thông tin xác thực. Ai đọc được dump database không được phép
    // đăng nhập thay người khác.
    $user = nguoiDung();
    buocMot();

    $ban_ghi = TwoFactorCode::query()->where('user_id', $user->id)->firstOrFail();

    expect($ban_ghi->code_hash)->not->toBe(maVuaGui())
        ->and(strlen((string) $ban_ghi->code_hash))->toBeGreaterThan(20);
});

it('không gửi mã khi mật khẩu sai', function (): void {
    nguoiDung();

    $this->postJson('/api/v1/auth/login', [
        'email' => 'nv@congty.vn',
        'password' => 'sai-be-bet',
    ])->assertStatus(422);

    Mail::assertNothingSent();
    expect(TwoFactorCode::query()->count())->toBe(0);
});

/*
|--------------------------------------------------------------------------
| Nhập mã
|--------------------------------------------------------------------------
*/

it('vào được sau khi nhập đúng mã trong email', function (): void {
    $user = nguoiDung();
    buocMot();

    $this->postJson('/api/v1/auth/two-factor-challenge', ['code' => maVuaGui()])
        ->assertOk()
        ->assertJsonPath('data.email', 'nv@congty.vn');

    $this->assertAuthenticatedAs($user, 'web');
});

it('từ chối mã sai', function (): void {
    nguoiDung();
    buocMot();

    $this->postJson('/api/v1/auth/two-factor-challenge', ['code' => '000000'])
        ->assertStatus(422)
        ->assertJsonPath('code', 'INVALID_TWO_FACTOR_CODE');

    $this->assertGuest('web');
});

it('mã chỉ dùng được một lần', function (): void {
    $user = nguoiDung();
    buocMot();
    $ma = maVuaGui();

    $this->postJson('/api/v1/auth/two-factor-challenge', ['code' => $ma])->assertOk();
    $this->postJson('/api/v1/auth/logout');

    buocMot();

    // Mã cũ không còn giá trị, dù mã mới vừa được gửi.
    $this->postJson('/api/v1/auth/two-factor-challenge', ['code' => $ma])
        ->assertStatus(422);
});

it('từ chối mã đã hết hạn', function (): void {
    $user = nguoiDung();
    buocMot();
    $ma = maVuaGui();

    $this->travel(11)->minutes();

    $this->postJson('/api/v1/auth/two-factor-challenge', ['code' => $ma])
        ->assertStatus(422);

    $this->assertGuest('web');
});

it('gửi mã mới thì mã cũ hết giá trị', function (): void {
    $user = nguoiDung();
    buocMot();
    $maCu = maVuaGui();

    $this->postJson('/api/v1/auth/two-factor/resend')->assertOk();

    expect(maVuaGui())->not->toBe($maCu);

    $this->postJson('/api/v1/auth/two-factor-challenge', ['code' => $maCu])
        ->assertStatus(422);
});

it('khoá tạm sau nhiều lần nhập sai', function (): void {
    nguoiDung();
    buocMot();

    foreach (range(1, 5) as $ignored) {
        $this->postJson('/api/v1/auth/two-factor-challenge', ['code' => '000000']);
    }

    $this->postJson('/api/v1/auth/two-factor-challenge', ['code' => '000000'])
        ->assertStatus(429);
});

it('chặn bấm gửi lại liên tục', function (): void {
    // Không chặn thì nút "gửi lại" thành công cụ spam hộp thư người khác.
    nguoiDung();
    buocMot();

    foreach (range(1, 3) as $ignored) {
        $this->postJson('/api/v1/auth/two-factor/resend');
    }

    $this->postJson('/api/v1/auth/two-factor/resend')->assertStatus(429);
});

it('không cho gửi lại khi chưa qua bước mật khẩu', function (): void {
    $this->postJson('/api/v1/auth/two-factor/resend')
        ->assertStatus(422)
        ->assertJsonPath('code', 'NO_PENDING_LOGIN');
});

/*
|--------------------------------------------------------------------------
| Nhân viên mới bật lần đầu
|--------------------------------------------------------------------------
*/

it('gửi mã tới email để nhân viên mới bật xác thực hai lớp', function (): void {
    $user = nguoiDung(daBat: false);

    buocMot();

    $this->getJson('/api/v1/auth/two-factor/setup')
        ->assertOk()
        ->assertJsonPath('data.qr_code_svg', null)
        ->assertJsonPath('data.sent_to', 'nv@congty.vn');

    Mail::assertQueued(TwoFactorCodeMail::class);

    $this->postJson('/api/v1/auth/two-factor/confirm', ['code' => maVuaGui()])
        ->assertOk()
        ->assertJsonCount(8, 'data.recovery_codes');

    expect($user->refresh()->two_factor_confirmed_at)->not->toBeNull();
    $this->assertAuthenticatedAs($user, 'web');
});

it('không bật khi nhập sai mã lúc thiết lập', function (): void {
    $user = nguoiDung(daBat: false);
    buocMot();
    $this->getJson('/api/v1/auth/two-factor/setup')->assertOk();

    $this->postJson('/api/v1/auth/two-factor/confirm', ['code' => '000000'])
        ->assertStatus(422);

    expect($user->refresh()->two_factor_confirmed_at)->toBeNull();
    $this->assertGuest('web');
});

/*
|--------------------------------------------------------------------------
| Mã khôi phục vẫn dùng được
|--------------------------------------------------------------------------
*/

it('vào được bằng mã khôi phục khi không nhận được email', function (): void {
    $user = nguoiDung();
    $user->forceFill(['two_factor_recovery_codes' => ['AAAAA-11111', 'BBBBB-22222']])->save();

    buocMot();

    $this->postJson('/api/v1/auth/two-factor-challenge', ['recovery_code' => 'AAAAA-11111'])
        ->assertOk();

    $this->assertAuthenticatedAs($user, 'web');
    expect($user->refresh()->two_factor_recovery_codes)->toBe(['BBBBB-22222']);
});
