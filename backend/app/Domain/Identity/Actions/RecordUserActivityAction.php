<?php

declare(strict_types=1);

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Enums\UserActivityEvent;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Models\UserActivity;

/**
 * Ghi một dòng vào nhật ký nhân sự.
 *
 * Là Action riêng chứ không phải Observer trên model — khác hẳn với
 * `TaskActivityObserver`. Lý do: thay đổi nhân sự cần biết **ai** gây ra, mà
 * Observer thì không có đường lấy người thao tác nếu không đi vòng qua `Auth`,
 * và tầng Domain không được biết tới phiên đăng nhập (xem README, "Quy tắc phụ
 * thuộc"). Với task thì không vướng vì Observer ở đó chỉ ghi lại chính nội dung
 * đã đổi.
 *
 * Hệ quả có chủ ý: sửa `users` bằng tay ở tinker hay ở seeder sẽ KHÔNG sinh
 * nhật ký. Nhật ký này ghi lại hành động của con người qua giao diện, không
 * phải mọi lần cột bị ghi.
 */
final class RecordUserActivityAction
{
    /**
     * @param  array<string, mixed>|null  $old
     * @param  array<string, mixed>|null  $new
     */
    public function execute(
        User $user,
        UserActivityEvent $event,
        ?User $causer = null,
        ?array $old = null,
        ?array $new = null,
    ): UserActivity {
        return UserActivity::query()->create([
            'user_id' => $user->id,
            'causer_id' => $causer?->id,
            'event' => $event,
            'old_values' => $old,
            'new_values' => $new,
        ]);
    }
}
