<?php

declare(strict_types=1);

namespace App\Support\Exceptions;

use Illuminate\Http\Response;

/**
 * Sai email hoặc sai mật khẩu.
 *
 * Thông báo cố tình không nói rõ sai cái nào: nói "email không tồn tại" là tặng
 * cho người dò một cách liệt kê tài khoản có thật trong hệ thống.
 */
final class InvalidCredentialsException extends DomainException
{
    public function __construct()
    {
        parent::__construct('Email hoặc mật khẩu không đúng.');
    }

    public function errorCode(): string
    {
        return 'INVALID_CREDENTIALS';
    }

    public function httpStatus(): int
    {
        return Response::HTTP_UNPROCESSABLE_ENTITY;
    }
}
