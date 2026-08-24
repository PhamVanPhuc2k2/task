<?php

declare(strict_types=1);

use App\Domain\Identity\Enums\NotificationType;
use App\Domain\Identity\Enums\UserActivityEvent;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Models\UserActivity;
use App\Domain\Identity\Notifications\ResetPasswordNotification;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Testing\TestResponse;

/*
|--------------------------------------------------------------------------
| Quên mật khẩu
|--------------------------------------------------------------------------
|
| Trước phần này, nhân viên quên mật khẩu phải nhắn admin — và admin phải đặt
| mật khẩu hộ người khác.
|
| Thứ đáng kiểm ở đây KHÔNG phải "gửi được email". Ba điều dưới đây mới là chỗ
| hỏng âm thầm:
|
|   1. Phản hồi khác nhau giữa email có và không có → trang này thành công cụ
|      dò danh sách nhân sự của cả công ty.
|   2. Người đã nghỉ việc đặt lại được mật khẩu → một đường quay lại hệ thống
|      sau khi đã bị thu hồi quyền.
|   3. Đổi mật khẩu mà không đá phiên cũ ra → người bị chiếm tài khoản đổi mật
|      khẩu xong, kẻ chiếm vẫn còn nguyên phiên.
|
*/

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->withHeader('Origin', 'http://localhost:3000');
    RateLimiter::clear('quen-mk:ip:127.0.0.1');
});

/** @return TestResponse<JsonResponse> */
function xinDatLai(string $email): TestResponse
{
    return test()->postJson('/api/v1/auth/forgot-password', ['email' => $email]);
}

/*
|--------------------------------------------------------------------------
| Không để lộ email nào có trong hệ thống
|--------------------------------------------------------------------------
*/

it('trả về đúng một câu dù email có tồn tại hay không', function (): void {
    Notification::fake();

    $u = User::factory()->create(['email' => 'co-that@explus.vn']);

    $coThat = xinDatLai($u->email)->assertOk();
    $khongCo = xinDatLai('khong-he-co@explus.vn')->assertOk();

    // So cả nội dung phản hồi, không chỉ mã HTTP: chỉ cần một chữ khác nhau là
    // đủ để dò.
    expect($coThat->getContent())->toBe($khongCo->getContent());
});

it('vẫn trả về câu đó cho người đã nghỉ việc, nhưng không gửi gì', function (): void {
    Notification::fake();

    $daNghi = User::factory()->create([
        'email' => 'da-nghi@explus.vn',
        'is_active' => false,
    ]);

    xinDatLai($daNghi->email)->assertOk();

    // Nói "tài khoản đã bị khoá" là xác nhận người này từng làm ở đây.
    Notification::assertNothingSent();
});

/*
|--------------------------------------------------------------------------
| Gửi link
|--------------------------------------------------------------------------
*/

it('gửi link cho tài khoản còn hoạt động', function (): void {
    Notification::fake();

    $u = User::factory()->create(['email' => 'nhan-vien@explus.vn']);

    xinDatLai($u->email)->assertOk();

    Notification::assertSentTo($u, ResetPasswordNotification::class);
});

it('link trỏ về giao diện chứ không phải về API', function (): void {
    Notification::fake();

    $u = User::factory()->create(['email' => 'nhan-vien@explus.vn']);

    xinDatLai($u->email);

    Notification::assertSentTo($u, ResetPasswordNotification::class, function (
        ResetPasswordNotification $tb,
    ) use ($u): bool {
        $mail = $tb->toMail($u);
        /** @var array<string, mixed> $data */
        $data = $mail->viewData;
        $url = (string) $data['actionUrl'];

        // Người dùng bấm vào email và phải thấy một trang nhập mật khẩu mới,
        // không phải một dòng JSON.
        return str_starts_with($url, config()->string('app.frontend_url').'/reset-password?')
            && str_contains($url, 'token=')
            && str_contains($url, urlencode($u->email));
    });
});

it('không đi qua tuỳ chọn tắt thông báo của người dùng', function (): void {
    /*
    | Nếu email đặt lại mật khẩu tôn trọng tuỳ chọn "tắt email" thì người từng
    | tắt sẽ KHÔNG BAO GIỜ lấy lại được tài khoản, và không hiểu vì sao. Đây là
    | email hạ tầng, không phải thông báo nghiệp vụ.
    */
    Notification::fake();

    $u = User::factory()->create(['email' => 'tat-het@explus.vn']);

    foreach (NotificationType::cases() as $loai) {
        $u->notificationSettings()->updateOrCreate(
            ['type' => $loai->value],
            ['in_app' => false, 'email' => false],
        );
    }

    xinDatLai($u->email)->assertOk();

    Notification::assertSentTo($u, ResetPasswordNotification::class);
});

/*
|--------------------------------------------------------------------------
| Hạn mức
|--------------------------------------------------------------------------
*/

it('chặn sau vài lần thử liên tiếp trên cùng một email', function (): void {
    Notification::fake();

    $u = User::factory()->create(['email' => 'bi-doi@explus.vn']);

    xinDatLai($u->email)->assertOk();
    xinDatLai($u->email)->assertOk();
    xinDatLai($u->email)->assertOk();

    xinDatLai($u->email)
        ->assertStatus(422)
        ->assertJsonPath('errors.email.0', fn (string $m): bool => str_contains($m, 'quá nhiều lần'));
});

/*
|--------------------------------------------------------------------------
| Đặt mật khẩu mới
|--------------------------------------------------------------------------
*/

/** @return TestResponse<JsonResponse> */
function datLaiMatKhau(User $u, string $token, string $matKhau): TestResponse
{
    return test()->postJson('/api/v1/auth/reset-password', [
        'token' => $token,
        'email' => $u->email,
        'password' => $matKhau,
        'password_confirmation' => $matKhau,
    ]);
}

it('đặt được mật khẩu mới bằng token hợp lệ', function (): void {
    $u = User::factory()->create(['email' => 'nhan-vien@explus.vn']);
    $token = Password::createToken($u);

    datLaiMatKhau($u, $token, 'MatKhauMoi2026xyz')->assertNoContent();

    expect(Hash::check('MatKhauMoi2026xyz', (string) $u->fresh()?->password))->toBeTrue();
});

it('token chỉ dùng được một lần', function (): void {
    $u = User::factory()->create(['email' => 'nhan-vien@explus.vn']);
    $token = Password::createToken($u);

    datLaiMatKhau($u, $token, 'MatKhauMoi2026xyz')->assertNoContent();
    datLaiMatKhau($u, $token, 'MatKhauKhac2026ab')->assertStatus(422);
});

it('token sai không đổi được gì', function (): void {
    $u = User::factory()->create(['email' => 'nhan-vien@explus.vn']);
    $cu = $u->password;

    datLaiMatKhau($u, 'token-bia-ra', 'MatKhauMoi2026xyz')
        ->assertStatus(422)
        ->assertJsonPath('errors.token.0', fn (string $m): bool => str_contains($m, 'hết hạn'));

    expect($u->fresh()?->password)->toBe($cu);
});

it('vẫn áp dụng chính sách mật khẩu của hệ thống', function (): void {
    // Viết luật riêng ở đường này là biến nó thành cửa sau: mật khẩu yếu đi qua
    // được ở đây trong khi đường đổi mật khẩu thông thường vẫn chặn.
    $u = User::factory()->create(['email' => 'nhan-vien@explus.vn']);
    $token = Password::createToken($u);

    datLaiMatKhau($u, $token, 'abc123')
        ->assertStatus(422)
        ->assertJsonValidationErrors('password');
});

it('đá mọi phiên cũ ra sau khi đổi mật khẩu', function (): void {
    /*
    | Người đặt lại mật khẩu vì nghi bị chiếm tài khoản mà kẻ kia vẫn còn phiên
    | thì việc đổi mật khẩu chẳng giải quyết được gì.
    */
    $u = User::factory()->create(['email' => 'nhan-vien@explus.vn']);
    $u->createToken('thiet-bi-cu');
    $rememberCu = $u->remember_token;

    $token = Password::createToken($u);
    datLaiMatKhau($u, $token, 'MatKhauMoi2026xyz')->assertNoContent();

    $moi = $u->fresh();

    expect($moi?->tokens()->count())->toBe(0)
        ->and($moi?->remember_token)->not->toBe($rememberCu);
});

it('không tắt xác thực hai lớp', function (): void {
    // Đổi mật khẩu không phải lý do để hạ lớp bảo vệ thứ hai. Chính điều này
    // khiến một token đặt lại mật khẩu bị lộ vẫn chưa đủ để vào được hệ thống.
    $u = User::factory()->create([
        'email' => 'nhan-vien@explus.vn',
        'two_factor_confirmed_at' => now(),
    ]);

    $token = Password::createToken($u);
    datLaiMatKhau($u, $token, 'MatKhauMoi2026xyz')->assertNoContent();

    expect($u->fresh()?->hasTwoFactorEnabled())->toBeTrue();
});

it('ghi vào nhật ký nhân sự, và phân biệt được với admin đặt hộ', function (): void {
    $u = User::factory()->create(['email' => 'nhan-vien@explus.vn']);
    $token = Password::createToken($u);

    datLaiMatKhau($u, $token, 'MatKhauMoi2026xyz')->assertNoContent();

    $nhatKy = UserActivity::query()
        ->where('user_id', $u->id)
        ->where('event', UserActivityEvent::PasswordReset->value)
        ->firstOrFail();

    // `causer` null = người dùng tự làm. Admin đặt hộ thì cột này có tên admin.
    expect($nhatKy->causer_id)->toBeNull();
});
