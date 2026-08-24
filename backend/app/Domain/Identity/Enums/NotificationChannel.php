<?php

declare(strict_types=1);

namespace App\Domain\Identity\Enums;

/**
 * Kênh gửi thông báo mà người dùng bật/tắt được.
 *
 * Cố tình KHÔNG dùng thẳng chuỗi 'database' và 'mail' của Laravel: tên kênh của
 * framework là chi tiết kỹ thuật, còn đây là lựa chọn người dùng nhìn thấy.
 * Ngày mai đổi kênh trong ứng dụng sang broadcast thì chỉ sửa một chỗ ánh xạ.
 *
 * Zalo OA và Telegram để đợt 2 — đó mới là kênh nhân viên thực sự đọc, nhưng
 * cần thời gian xin cấu hình phía nhà cung cấp.
 */
enum NotificationChannel: string
{
    case InApp = 'in_app';
    case Email = 'email';

    public function label(): string
    {
        return match ($this) {
            self::InApp => 'Trong ứng dụng',
            self::Email => 'Email',
        };
    }

    /** Tên kênh tương ứng phía Laravel. */
    public function laravelChannel(): string
    {
        return match ($this) {
            self::InApp => 'database',
            self::Email => 'mail',
        };
    }
}
