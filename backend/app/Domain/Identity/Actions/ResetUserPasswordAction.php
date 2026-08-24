<?php

declare(strict_types=1);

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Enums\UserActivityEvent;
use App\Domain\Identity\Models\User;
use App\Support\TemporaryPassword;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Quản trị viên đặt lại mật khẩu hộ người dùng.
 *
 * Trả về mật khẩu tạm dạng rõ đúng một lần để quản trị viên đọc cho người dùng.
 * Không lưu, không gửi email — hệ thống nội bộ, HR đọc trực tiếp là đủ, và
 * không nằm trong hộp thư của ai.
 */
final class ResetUserPasswordAction
{
    public function __construct(
        private readonly RecordUserActivityAction $ghiNhatKy,
    ) {}

    /**
     * @param  User|null  $actor  Người thao tác. Null khi lệnh dòng lệnh gọi —
     *                            nhật ký vẫn ghi, chỉ là không có ai đứng sau.
     */
    public function execute(User $user, ?User $actor = null): string
    {
        // Bảng chữ không có ký hiệu và không có ký tự dễ nhầm — chuỗi này để
        // đọc cho người khác chép tay. Xem App\Support\TemporaryPassword.
        $temporary = TemporaryPassword::generate();

        $user->forceFill(['password' => Hash::make($temporary)])->save();

        // Đặt lại mật khẩu phải đá mọi phiên cũ ra ngoài — nếu tài khoản bị
        // chiếm, kẻ chiếm không được giữ phiên sau khi đổi mật khẩu.
        $user->tokens()->delete();
        $user->forceFill(['remember_token' => Str::random(60)])->save();

        // Ghi việc đã xảy ra, KHÔNG ghi mật khẩu. Nhật ký kiểm toán mà chứa
        // thông tin xác thực thì bản thân nó trở thành chỗ rò rỉ.
        $this->ghiNhatKy->execute(
            user: $user,
            event: UserActivityEvent::PasswordReset,
            causer: $actor,
        );

        return $temporary;
    }
}
