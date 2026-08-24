<?php

declare(strict_types=1);

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Enums\UserActivityEvent;
use App\Domain\Identity\Models\User;
use App\Support\Exceptions\CannotDisableSelfException;

/**
 * Vô hiệu hoá tài khoản nhân viên nghỉ việc.
 *
 * KHÔNG xoá bản ghi: task họ từng làm, báo cáo họ từng nộp và bảng công của họ
 * đều phải còn nguyên vết, và vẫn phải hiển thị được tên người đứng sau.
 *
 * Người thao tác được truyền vào chứ không tự đi lấy từ Auth — tầng Domain
 * không được biết tới phiên đăng nhập.
 */
final class DeactivateUserAction
{
    public function __construct(
        private readonly RecordUserActivityAction $ghiNhatKy,
    ) {}

    public function execute(User $user, User $actor): void
    {
        if ($user->is($actor)) {
            throw new CannotDisableSelfException;
        }

        $user->forceFill([
            'is_active' => false,
            'terminated_at' => now(),
        ])->save();

        // Thu hồi mọi token API ngay lập tức — không chờ tới lúc token hết hạn.
        // Xem README mục 1.9 Bảo mật.
        $user->tokens()->delete();

        $this->ghiNhatKy->execute(
            user: $user,
            event: UserActivityEvent::Deactivated,
            causer: $actor,
            old: ['is_active' => true],
            new: ['is_active' => false, 'terminated_at' => $user->terminated_at?->toDateTimeString()],
        );
    }
}
