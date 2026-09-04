<?php

declare(strict_types=1);

namespace App\Http\Concerns;

use App\Domain\Identity\Models\User;
use App\Support\Exceptions\NoPendingLoginException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;

/**
 * Trạng thái giữa hai bước đăng nhập.
 *
 * Sau khi kiểm mật khẩu đúng, người dùng CHƯA được đăng nhập — chỉ được ghi
 * tạm vào session để bước nhập mã OTP biết đang nói về ai. Không phát token,
 * không tạo phiên, nên nếu họ bỏ dở giữa chừng thì không vào được gì.
 */
trait ManagesPendingLogin
{
    private const string PENDING_KEY = 'auth.pending_user_id';

    /**
     * Người dùng có tích "ghi nhớ đăng nhập" ở bước một hay không.
     *
     * Phải cất vào session chứ không truyền thẳng: bước nhập mật khẩu và bước
     * nhập mã OTP là **hai request khác nhau**, và cờ này ra đời ở bước một
     * nhưng chỉ được dùng ở bước hai.
     */
    private const string PENDING_REMEMBER_KEY = 'auth.pending_remember';

    /** Cờ để frontend biết đã đăng nhập. Xem markAsAuthenticated(). */
    private const string AUTH_FLAG_COOKIE = 'explus_auth';

    private function rememberPendingLogin(Request $request, User $user, bool $ghiNho = false): void
    {
        $request->session()->put(self::PENDING_KEY, $user->id);
        $request->session()->put(self::PENDING_REMEMBER_KEY, $ghiNho);
    }

    private function pendingUser(Request $request): User
    {
        $id = $request->session()->get(self::PENDING_KEY);

        if (! is_int($id) && ! is_string($id)) {
            throw new NoPendingLoginException;
        }

        $user = User::query()->find($id);

        if (! $user instanceof User || $user->is_active !== true) {
            throw new NoPendingLoginException;
        }

        return $user;
    }

    /**
     * Che bớt email trước khi trả ra màn hình: `ng***@congty.vn`.
     *
     * Người dùng cần nhận ra hộp thư nào của mình, nhưng màn nhập mã hiện ra
     * TRƯỚC khi họ chứng minh được danh tính — hiện nguyên địa chỉ là tặng
     * thông tin cho người chỉ đoán trúng mật khẩu.
     */
    private function maskEmail(string $email): string
    {
        [$ten, $mien] = array_pad(explode('@', $email, 2), 2, '');

        if ($mien === '') {
            return str_repeat('*', mb_strlen($email));
        }

        $hienThi = mb_substr($ten, 0, min(2, mb_strlen($ten)));

        return $hienThi.str_repeat('*', max(3, mb_strlen($ten) - mb_strlen($hienThi))).'@'.$mien;
    }

    /**
     * Hoàn tất đăng nhập sau khi đã qua cả hai bước.
     *
     * ## "Ghi nhớ đăng nhập" chính là refresh token, viết bằng cookie
     *
     * `remember: true` làm Laravel phát thêm một cookie sống **400 ngày** chứa
     * `user_id|remember_token|hash mật khẩu`. Khi phiên ngắn hết hạn,
     * `SessionGuard` tự đọc cookie đó và lập lại phiên — **không qua OTP, người
     * dùng không thấy gì**. Đúng vai trò của refresh token, chỉ khác tên.
     *
     * Đây là thứ đáng giá nhất với hệ thống này: đăng nhập lại ở đây tốn một
     * vòng email OTP chứ không phải chỉ gõ mật khẩu, nên mỗi lần phiên hết hạn
     * là một lần người dùng đứng chờ hộp thư.
     *
     * Thu hồi: xoay `remember_token` (đã làm sẵn ở cả ba đường đổi mật khẩu)
     * giết cookie, còn `AuthenticateSession` giết các phiên đang sống. Không có
     * vế thứ hai thì không nên bật vế thứ nhất.
     *
     * Người không tích thì `remember: false` — hành vi y như trước.
     */
    private function completeLogin(Request $request, User $user): void
    {
        // `pull` chứ không `get`: cờ chỉ dùng đúng một lần, để lại trong session
        // thì lần đăng nhập sau trên cùng trình duyệt sẽ thừa hưởng lựa chọn cũ
        // mà người dùng không hề tích lại.
        $ghiNho = $request->session()->pull(self::PENDING_REMEMBER_KEY) === true;

        $request->session()->forget(self::PENDING_KEY);

        Auth::guard('web')->login($user, remember: $ghiNho);

        // Đổi id phiên sau khi đăng nhập để chống session fixation.
        $request->session()->regenerate();

        $this->markAsAuthenticated();
    }

    /**
     * Đặt cờ "đã đăng nhập" cho frontend.
     *
     * KHÔNG dùng cookie phiên của Laravel làm tín hiệu này: Laravel cấp cookie
     * phiên cho MỌI người, kể cả khách vừa mở trang đăng nhập. Coi sự tồn tại
     * của nó là "đã đăng nhập" sẽ tạo vòng lặp chuyển hướng bất tận giữa `/`
     * và `/login`.
     *
     * Cờ này chỉ để frontend biết có nên hiển thị trang hay đá về đăng nhập —
     * KHÔNG phải lớp bảo vệ. Nó không mã hoá, không chứa gì bí mật, và ai cũng
     * tự đặt được. Phân quyền thật nằm ở `auth:sanctum` + `active` + Policy.
     */
    private function markAsAuthenticated(): void
    {
        Cookie::queue(Cookie::make(
            name: self::AUTH_FLAG_COOKIE,
            value: '1',
            minutes: (int) config('session.lifetime'),
            path: '/',
            httpOnly: false,
        ));
    }

    /** Xoá cờ khi đăng xuất. */
    private function clearAuthenticatedMark(): void
    {
        Cookie::queue(Cookie::forget(self::AUTH_FLAG_COOKIE, '/'));
    }
}
