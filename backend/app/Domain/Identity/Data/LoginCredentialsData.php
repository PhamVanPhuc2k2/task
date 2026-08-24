<?php

declare(strict_types=1);

namespace App\Domain\Identity\Data;

/**
 * Thông tin một lần thử đăng nhập.
 *
 * `ipAddress` và `userAgent` được truyền vào từ tầng Http chứ không tự đi lấy —
 * tầng Domain không được biết tới Request. Xem README, "Quy tắc phụ thuộc".
 */
final readonly class LoginCredentialsData
{
    public function __construct(
        public string $email,
        public string $password,
        public ?string $ipAddress = null,
        public ?string $userAgent = null,
    ) {}
}
