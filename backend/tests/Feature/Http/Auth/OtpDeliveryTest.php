<?php

declare(strict_types=1);

use App\Domain\Identity\Mail\TwoFactorCodeMail;
use App\Domain\Identity\Models\TwoFactorCode;
use App\Domain\Identity\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;

/*
|--------------------------------------------------------------------------
| Đường đi của mã OTP: qua hàng đợi, không chặn request đăng nhập
|--------------------------------------------------------------------------
|
| Bản đầu gửi đồng bộ, nên request đăng nhập ôm trọn vòng đi-về SMTP với
| Gmail — đo thật là 4,0–4,7 giây người dùng nhìn nút quay ở MỌI lần đăng nhập.
| Qua hàng đợi còn 0,49–0,52 giây.
|
| Những test dưới đây khoá lại ba điều dễ vô tình phá vỡ khi sửa mã sau này:
| mail phải đi hàng đợi, phải đúng hàng `auth`, và hàng đó phải được Horizon
| xử lý trước mọi hàng việc nền.
|
*/

const MAT_KHAU_OTP = 'MatKhauDung@2026';

beforeEach(function (): void {
    config()->set('two-factor.driver', 'email');
    $this->seed(RolePermissionSeeder::class);
    $this->withHeader('Origin', 'http://localhost:3000');
    RateLimiter::clear('login:otp@congty.vn|127.0.0.1');
    Mail::fake();
});

function nguoiDungOtp(): User
{
    return User::factory()->create([
        'email' => 'otp@congty.vn',
        'password' => Hash::make(MAT_KHAU_OTP),
        'two_factor_confirmed_at' => now(),
    ]);
}

it('không gửi mail ngay trong request đăng nhập', function (): void {
    // `assertNothingSent` là phần quan trọng: nếu ai đó bỏ `implements
    // ShouldQueue` khỏi TwoFactorCodeMail, mail quay về gửi đồng bộ, mọi test
    // khác vẫn xanh, và người dùng lại phải chờ 1–3 giây mà không ai biết vì
    // sao.
    nguoiDungOtp();

    $this->postJson('/api/v1/auth/login', [
        'email' => 'otp@congty.vn',
        'password' => MAT_KHAU_OTP,
    ])->assertOk()->assertJsonPath('data.two_factor_required', true);

    Mail::assertNothingSent();
    Mail::assertQueued(TwoFactorCodeMail::class);
});

it('mail mã OTP khai báo là job hàng đợi', function (): void {
    $mail = new TwoFactorCodeMail('123456', 'Nguyễn Văn A', 10);

    expect($mail)->toBeInstanceOf(ShouldQueue::class);
});

it('đẩy mã OTP vào hàng đợi riêng, không dùng chung hàng việc nền', function (): void {
    nguoiDungOtp();

    $this->postJson('/api/v1/auth/login', [
        'email' => 'otp@congty.vn',
        'password' => MAT_KHAU_OTP,
    ])->assertOk();

    Mail::assertQueued(
        TwoFactorCodeMail::class,
        fn (TwoFactorCodeMail $mail): bool => $mail->queue === config()->string('two-factor.queue'),
    );
});

it('Horizon xử lý hàng đợi mã OTP trước mọi hàng việc nền', function (): void {
    // Không có ràng buộc này thì một đợt quét deadline đẩy hai trăm email vào
    // hàng `notifications` sẽ khiến mã đăng nhập của cả công ty xếp sau — đúng
    // kịch bản mà việc gửi đồng bộ trước đây cố tránh.
    $hangDoi = config()->array('horizon.defaults.supervisor-1.queue');
    $cuaOtp = config()->string('two-factor.queue');

    expect($hangDoi)->toContain($cuaOtp)
        ->and(array_search($cuaOtp, $hangDoi, strict: true))->toBe(0);
});

it('nút gửi lại cũng đi qua hàng đợi', function (): void {
    nguoiDungOtp();

    $this->postJson('/api/v1/auth/login', [
        'email' => 'otp@congty.vn',
        'password' => MAT_KHAU_OTP,
    ])->assertOk();

    $this->postJson('/api/v1/auth/two-factor/resend')->assertOk();

    Mail::assertNothingSent();
    Mail::assertQueued(TwoFactorCodeMail::class, 2);
});

it('vẫn ghi mã đã băm vào database ngay trong request', function (): void {
    // Phần này KHÔNG được đẩy sang hàng đợi: nếu bản ghi mã chưa có mà người
    // dùng đã ở màn nhập mã, họ nhập đúng mã trong hộp thư vẫn bị báo sai.
    $user = nguoiDungOtp();

    $this->postJson('/api/v1/auth/login', [
        'email' => 'otp@congty.vn',
        'password' => MAT_KHAU_OTP,
    ])->assertOk();

    expect(TwoFactorCode::query()->where('user_id', $user->id)->usable()->exists())->toBeTrue();
});
