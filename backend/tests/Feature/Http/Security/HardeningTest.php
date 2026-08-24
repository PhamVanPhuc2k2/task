<?php

declare(strict_types=1);

use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Enums\Role;
use App\Domain\Identity\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Rules\Password as PasswordRule;

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->withHeader('Origin', 'http://localhost:3000');
});

/*
|--------------------------------------------------------------------------
| Header bảo mật
|--------------------------------------------------------------------------
*/

it('gắn header bảo mật vào mọi phản hồi của API', function (): void {
    [, $nhanVien] = sepVaNhanVien();

    $response = $this->actingAs($nhanVien)->getJson('/api/v1/tasks')->assertOk();

    expect($response->headers->get('X-Content-Type-Options'))->toBe('nosniff')
        ->and($response->headers->get('X-Frame-Options'))->toBe('DENY')
        ->and($response->headers->get('Referrer-Policy'))->toBe('no-referrer')
        ->and($response->headers->get('Content-Security-Policy'))
        ->toContain("default-src 'none'")
        ->and($response->headers->get('Content-Security-Policy'))
        ->toContain("frame-ancestors 'none'");
});

it('gắn header cả khi phản hồi là lỗi', function (): void {
    // Trang lỗi cũng là một phản hồi trình duyệt xử lý — bỏ sót ở đây là bỏ
    // sót ở đúng chỗ người tấn công nhắm tới.
    $response = $this->getJson('/api/v1/tasks')->assertStatus(401);

    expect($response->headers->get('X-Content-Type-Options'))->toBe('nosniff');
});

it('không gắn HSTS khi chưa chạy HTTPS', function (): void {
    // Gắn lúc dev chạy http sẽ khoá trình duyệt vào https://localhost và lập
    // trình viên không vào được nữa.
    [, $nhanVien] = sepVaNhanVien();

    $response = $this->actingAs($nhanVien)->getJson('/api/v1/tasks');

    expect($response->headers->has('Strict-Transport-Security'))->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| Giới hạn tần suất
|--------------------------------------------------------------------------
*/

/**
 * Khoá thật mà middleware `throttle` dùng trong bộ nhớ đệm.
 *
 * KHÔNG phải chuỗi trả về từ `Limit::by()`. `ThrottleRequests` băm lại thành
 * `md5(tên_limiter . khoá)` trước khi đếm. Bơm nhầm chuỗi chưa băm thì bộ đếm
 * thật vẫn trống, request vẫn qua, và test xanh mà không kiểm gì cả — đúng lỗi
 * bản đầu của test này mắc phải.
 */
function khoaThrottle(string $khoa): string
{
    return md5('api'.$khoa);
}

it('chặn khi gọi endpoint ghi quá nhanh', function (): void {
    [, $nhanVien] = sepVaNhanVien();

    // Đổ đầy bộ đếm ghi rồi mới gọi thật, thay vì bắn 61 request — mỗi request
    // trong bộ test là một vòng khởi động framework.
    $khoa = khoaThrottle('write|'.$nhanVien->id);
    RateLimiter::clear($khoa);

    for ($i = 0; $i < 60; $i++) {
        RateLimiter::hit($khoa, 60);
    }

    $this->actingAs($nhanVien)
        ->postJson('/api/v1/tasks', ['title' => 'Việc thứ sáu mươi mốt'])
        ->assertStatus(429)
        ->assertJsonPath('code', 'TOO_MANY_ATTEMPTS');
});

it('bộ đếm đọc và ghi tách riêng nhau', function (): void {
    // Dùng chung một bộ thì một màn hình gọi sáu endpoint đọc sẽ ăn hết hạn
    // mức của thao tác ghi ngay sau đó.
    [, $nhanVien] = sepVaNhanVien();

    $ghi = khoaThrottle('write|'.$nhanVien->id);
    RateLimiter::clear($ghi);
    RateLimiter::clear(khoaThrottle('read|'.$nhanVien->id));

    for ($i = 0; $i < 60; $i++) {
        RateLimiter::hit($ghi, 60);
    }

    // Ghi đã đầy, nhưng đọc vẫn phải chạy được.
    $this->actingAs($nhanVien)->getJson('/api/v1/tasks')->assertOk();
});

/*
|--------------------------------------------------------------------------
| Chính sách mật khẩu
|--------------------------------------------------------------------------
*/

it('từ chối mật khẩu ngắn hơn mười hai ký tự', function (): void {
    $ketQua = Validator::make(
        ['password' => 'Ngan1234'],
        ['password' => Password::defaults()],
    );

    expect($ketQua->fails())->toBeTrue();
});

it('nhận mật khẩu đủ dài, có chữ và số', function (): void {
    $ketQua = Validator::make(
        ['password' => 'MatKhauDuDai2026'],
        ['password' => Password::defaults()],
    );

    expect($ketQua->fails())->toBeFalse();
});

it('bắt buộc mật khẩu mới đạt chính sách khi người dùng tự đổi', function (): void {
    [, $nhanVien] = sepVaNhanVien();

    $this->actingAs($nhanVien)->patchJson('/api/v1/auth/password', [
        'current_password' => 'password',
        'password' => 'ngan',
        'password_confirmation' => 'ngan',
    ])->assertStatus(422)->assertJsonValidationErrors('password');
});

it('không gọi mạng ra ngoài khi chạy test', function (): void {
    // `uncompromised()` hỏi API của Have I Been Pwned. Bật trong test là test
    // rung: mất mạng thì cả bộ test đỏ vì lý do không liên quan.
    $luat = PasswordRule::defaults();

    expect($luat)->toBeInstanceOf(PasswordRule::class);

    // Mật khẩu này chắc chắn nằm trong kho đã lộ; test vẫn phải xanh vì luật
    // uncompromised bị tắt ở môi trường test.
    $ketQua = Validator::make(
        ['password' => 'Password12345'],
        ['password' => Password::defaults()],
    );

    expect($ketQua->fails())->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| Giao diện Horizon
|--------------------------------------------------------------------------
*/

it('chỉ người quản trị vai trò mới mở được Horizon', function (): void {
    // Horizon hiện thân của mọi job đang chạy — email nhân viên, nội dung bình
    // luận, và về sau là dữ liệu lương.
    [, $nhanVien] = sepVaNhanVien();

    $admin = User::factory()->create();
    $admin->assignRole(Role::Admin->value);

    expect($admin->can(Permission::ManageRoles->value))->toBeTrue()
        ->and(Gate::forUser($admin)->allows('viewHorizon'))->toBeTrue()
        ->and(Gate::forUser($nhanVien)->allows('viewHorizon'))->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| Thu hồi quyền truy cập
|--------------------------------------------------------------------------
*/

it('thu hồi token ngay khi tài khoản bị vô hiệu hoá', function (): void {
    [, $nhanVien] = sepVaNhanVien();
    $admin = User::factory()->create();
    $admin->assignRole(Role::Admin->value);

    $nhanVien->createToken('kiem-thu');
    expect($nhanVien->tokens()->count())->toBe(1);

    $this->actingAs($admin)
        ->postJson("/api/v1/users/{$nhanVien->uuid}/deactivate")
        ->assertNoContent();

    expect($nhanVien->refresh()->tokens()->count())->toBe(0);
});

it('chặn ngay giữa phiên khi tài khoản bị vô hiệu hoá', function (): void {
    // Không chờ phiên hết hạn: người vừa nghỉ việc phải mất quyền ngay.
    [, $nhanVien] = sepVaNhanVien();

    $this->actingAs($nhanVien)->getJson('/api/v1/tasks')->assertOk();

    $nhanVien->forceFill(['is_active' => false])->save();

    $this->actingAs($nhanVien)->getJson('/api/v1/tasks')->assertStatus(403);
});
