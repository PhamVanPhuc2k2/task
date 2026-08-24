<?php

declare(strict_types=1);

namespace App\Support\Exceptions;

/**
 * Gọi bước xác thực hai lớp mà chưa qua bước mật khẩu.
 *
 * Ngăn việc nhảy thẳng vào bước hai để dò mã OTP mà không cần biết mật khẩu.
 */
final class NoPendingLoginException extends DomainException
{
    public function __construct()
    {
        parent::__construct('Phiên đăng nhập đã hết hạn. Vui lòng đăng nhập lại từ đầu.');
    }

    public function errorCode(): string
    {
        return 'NO_PENDING_LOGIN';
    }
}
