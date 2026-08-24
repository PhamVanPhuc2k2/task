<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Domain\Identity\Actions\RecordUserActivityAction;
use App\Domain\Identity\Enums\UserActivityEvent;
use App\Domain\Identity\Models\User;
use App\Http\Requests\Auth\ResetPasswordRequest;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Đặt mật khẩu mới bằng token nhận qua email.
 *
 * Ba việc bắt buộc phải làm cùng lúc khi mật khẩu đổi, và bỏ sót cái nào cũng
 * để lại một đường vào:
 *
 *   1. **Đổi `remember_token`** — mọi thiết bị còn cookie "ghi nhớ đăng nhập"
 *      bị đá ra. Người đặt lại mật khẩu vì nghi bị chiếm tài khoản mà kẻ kia
 *      vẫn còn phiên thì việc đổi mật khẩu chẳng giải quyết gì.
 *   2. **Xoá token Sanctum** — cùng lý do, cho các tích hợp về sau.
 *   3. **Ghi nhật ký nhân sự** — cột `password_reset` đã có sẵn từ mục quản trị
 *      nhân sự, dùng chung với việc admin đặt lại hộ.
 *
 * Xác thực hai lớp **không** bị đụng tới: đổi mật khẩu không phải lý do để tắt
 * lớp bảo vệ thứ hai. Lần đăng nhập kế tiếp vẫn phải qua mã OTP như thường —
 * và đó chính là thứ khiến một token đặt lại mật khẩu bị lộ vẫn chưa đủ để vào
 * được hệ thống.
 */
final class ResetPasswordController
{
    public function __invoke(
        ResetPasswordRequest $request,
        RecordUserActivityAction $ghiNhatKy,
    ): JsonResponse {
        $trangThai = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $matKhauMoi) use ($ghiNhatKy): void {
                $user->forceFill([
                    'password' => Hash::make($matKhauMoi),
                    'remember_token' => Str::random(60),
                ])->save();

                $user->tokens()->delete();

                event(new PasswordReset($user));

                // Ghi việc đã xảy ra, KHÔNG ghi mật khẩu. `causer` để null vì
                // đây là người dùng tự làm, không ai thay mặt — khác hẳn với
                // việc admin đặt lại hộ, và phân biệt được hai trường hợp đó là
                // toàn bộ giá trị của dòng nhật ký này.
                $ghiNhatKy->execute(
                    user: $user,
                    event: UserActivityEvent::PasswordReset,
                    causer: null,
                );
            },
        );

        if ($trangThai !== Password::PasswordReset) {
            /*
            | Gộp mọi lý do thất bại vào một câu: token sai, token hết hạn,
            | email không tồn tại đều ra cùng một thông báo. Tách ra thì đường
            | này lại thành công cụ dò email — đúng thứ ForgotPasswordController
            | đã cẩn thận tránh, và nó sẽ vô nghĩa nếu đường bên cạnh để lộ.
            */
            throw ValidationException::withMessages([
                'token' => 'Đường dẫn không hợp lệ hoặc đã hết hạn. Hãy yêu cầu gửi lại.',
            ]);
        }

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
