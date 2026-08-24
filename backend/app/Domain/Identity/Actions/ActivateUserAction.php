<?php

declare(strict_types=1);

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Enums\UserActivityEvent;
use App\Domain\Identity\Models\User;

/**
 * Mở lại tài khoản đã vô hiệu hoá.
 *
 * Đường ngược của `DeactivateUserAction`. Thiếu nó thì vô hiệu hoá là thao tác
 * một chiều: bấm nhầm một cái, hoặc nhân viên nghỉ rồi quay lại làm, là phải
 * sửa thẳng database — đúng thứ mà mọi tính năng quản trị nên tránh.
 *
 * `terminated_at` được xoá về null: người này đang làm việc trở lại, giữ lại
 * ngày nghỉ cũ sẽ khiến mọi báo cáo nhân sự về sau đọc sai.
 *
 * KHÔNG khôi phục token đã thu hồi lúc vô hiệu hoá, và cũng không nên: người
 * quay lại đăng nhập từ đầu, qua đủ mật khẩu và xác thực hai lớp.
 */
final class ActivateUserAction
{
    public function __construct(
        private readonly RecordUserActivityAction $ghiNhatKy,
    ) {}

    public function execute(User $user, User $actor): void
    {
        if ($user->is_active) {
            return;
        }

        $ngayNghiCu = $user->terminated_at?->toDateTimeString();

        $user->forceFill([
            'is_active' => true,
            'terminated_at' => null,
        ])->save();

        $this->ghiNhatKy->execute(
            user: $user,
            event: UserActivityEvent::Activated,
            causer: $actor,
            old: ['is_active' => false, 'terminated_at' => $ngayNghiCu],
            new: ['is_active' => true, 'terminated_at' => null],
        );
    }
}
