<?php

declare(strict_types=1);

namespace App\Support\Settings;

/**
 * Kiểu của một cài đặt.
 *
 * Database lưu mọi thứ dạng chuỗi. Không ép kiểu khi đọc thì `get()` trả về
 * `"0"` thay vì `0`, và mọi phép so sánh phía sau đều có thể sai — `"0"` là
 * falsy nhưng `"5"` và `5` hành xử khác nhau ở khá nhiều chỗ.
 */
enum SettingType: string
{
    case Text = 'text';
    case Integer = 'integer';
    case Boolean = 'boolean';

    public function ep(?string $tho): string|int|bool|null
    {
        if ($tho === null) {
            return null;
        }

        return match ($this) {
            self::Text => $tho,
            self::Integer => (int) $tho,
            // So với chuỗi thay vì dùng (bool): (bool) "0" trả về true, và đó
            // là đúng thứ sẽ biến "đã tắt nhắc báo cáo" thành "vẫn bật".
            self::Boolean => $tho === '1' || $tho === 'true',
        };
    }

    public function raw(string|int|bool|null $v): ?string
    {
        if ($v === null) {
            return null;
        }

        return match ($this) {
            self::Boolean => $v ? '1' : '0',
            default => (string) $v,
        };
    }
}
