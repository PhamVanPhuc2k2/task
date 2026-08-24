<?php

declare(strict_types=1);

namespace App\Domain\Task\Notifications;

use Illuminate\Support\Str;

/**
 * Trích một đoạn ngắn của bình luận để xem trước trong thông báo.
 *
 * Bỏ dấu nhắc `@[Tên](uuid)` về còn `@Tên`: người đọc thông báo không cần thấy
 * cú pháp nội bộ, và một uuid 36 ký tự sẽ chiếm gần hết đoạn trích.
 */
trait QuotesComment
{
    private function trichDan(string $body): string
    {
        $sach = preg_replace('/@\[([^\]]{1,255})\]\([0-9a-fA-F-]{36}\)/', '@$1', $body) ?? $body;

        return Str::limit(trim($sach), 120);
    }
}
