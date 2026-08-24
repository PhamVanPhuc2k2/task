<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Domain\Identity\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * Gửi link đặt lại mật khẩu.
 *
 * ## Luôn trả về cùng một câu, dù email có tồn tại hay không
 *
 * Đây là điểm quan trọng nhất của endpoint này và cũng là chỗ hầu hết bản cài
 * đặt làm sai. Trả "email không tồn tại" nghĩa là **biến trang quên mật khẩu
 * thành công cụ dò danh sách nhân sự**: gõ vào vài trăm địa chỉ và biết chính
 * xác ai làm ở công ty này. Với một hệ thống nội bộ thì đó là danh sách để
 * nhắm lừa đảo.
 *
 * Cùng lý do, người bị vô hiệu hoá cũng nhận đúng câu trả lời đó — chỉ khác là
 * không có email nào được gửi. Nói "tài khoản đã bị khoá" là xác nhận người đó
 * từng làm ở đây.
 *
 * ## Hạn mức riêng, chặt hơn phần còn lại của API
 *
 * Hai lớp: theo email và theo địa chỉ IP. Chỉ chặn theo email thì một máy quét
 * đổi email mỗi lần là đi qua được; chỉ chặn theo IP thì cả văn phòng dùng
 * chung một IP sẽ chặn nhầm nhau. Laravel còn có `throttle` riêng của bộ đặt
 * lại mật khẩu (60 giây giữa hai lần cho cùng một người) — lớp đó chặn spam
 * hộp thư, lớp ở đây chặn dò quét.
 */
final class ForgotPasswordController
{
    private const int MAX_MOI_EMAIL = 3;

    private const int MAX_MOI_IP = 10;

    private const int CUA_SO_GIAY = 900;

    public function __invoke(Request $request): JsonResponse
    {
        $request->validate(
            ['email' => ['required', 'email', 'max:255']],
            [],
            ['email' => 'email'],
        );

        $email = mb_strtolower((string) $request->string('email'));

        $this->kiemHanMuc($email, (string) $request->ip());

        $nguoi = User::query()->where('email', $email)->first();

        // Chỉ gửi cho tài khoản còn hoạt động. Người đã nghỉ việc mà đặt lại
        // được mật khẩu là một đường quay lại hệ thống sau khi đã bị thu hồi
        // quyền — mà `EnsureUserIsActive` chỉ chặn ở tầng request, không chặn
        // được việc họ lấy lại quyền kiểm soát hộp thư đăng nhập.
        if ($nguoi instanceof User && $nguoi->is_active) {
            Password::sendResetLink(['email' => $email]);
        }

        return new JsonResponse([
            'message' => 'Nếu email này có trong hệ thống, chúng tôi vừa gửi một đường dẫn đặt lại mật khẩu. Kiểm tra cả hộp thư rác.',
        ]);
    }

    private function kiemHanMuc(string $email, string $ip): void
    {
        foreach ([
            'quen-mk:email:'.$email => self::MAX_MOI_EMAIL,
            'quen-mk:ip:'.$ip => self::MAX_MOI_IP,
        ] as $khoa => $tran) {
            if (RateLimiter::tooManyAttempts($khoa, $tran)) {
                throw ValidationException::withMessages([
                    'email' => sprintf(
                        'Bạn đã thử quá nhiều lần. Chờ %d phút rồi thử lại.',
                        (int) ceil(RateLimiter::availableIn($khoa) / 60),
                    ),
                ]);
            }

            RateLimiter::hit($khoa, self::CUA_SO_GIAY);
        }
    }
}
