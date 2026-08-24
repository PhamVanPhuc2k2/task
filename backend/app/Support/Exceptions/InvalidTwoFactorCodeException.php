<?php

declare(strict_types=1);

namespace App\Support\Exceptions;

/**
 * Mã OTP hoặc mã khôi phục không đúng.
 *
 * Không nói rõ sai vì lý do gì — mọi thông tin thêm đều giúp người dò.
 */
final class InvalidTwoFactorCodeException extends DomainException
{
    public function __construct()
    {
        parent::__construct('Mã xác thực không đúng hoặc đã hết hạn.');
    }

    public function errorCode(): string
    {
        return 'INVALID_TWO_FACTOR_CODE';
    }
}
