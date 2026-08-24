<?php

declare(strict_types=1);

namespace App\Support\Exceptions;

/**
 * Chặn ghi một khoá cài đặt không có trong danh mục.
 *
 * Bảng key/value mà cho gõ khoá tuỳ ý thì database đầy những dòng sai chính tả
 * mà không ai biết — và `get()` của khoá đúng vẫn im lặng trả về mặc định. Lỗi
 * kiểu đó chỉ lộ ra khi có người thắc mắc "sao bấm lưu rồi mà không đổi gì".
 */
final class UnknownSettingException extends DomainException
{
    public function __construct(string $khoa)
    {
        parent::__construct(sprintf('Không có cài đặt nào tên "%s".', $khoa));
    }

    public function errorCode(): string
    {
        return 'UNKNOWN_SETTING';
    }
}
