<?php

declare(strict_types=1);

namespace App\Support\Exceptions;

/**
 * Chặn quản trị viên tự vô hiệu hoá chính mình.
 *
 * Nếu người quản trị cuối cùng tự khoá tài khoản thì không còn ai vào được hệ
 * thống để mở lại — phải sửa thẳng trong database.
 */
final class CannotDisableSelfException extends DomainException
{
    public function __construct()
    {
        parent::__construct('Bạn không thể tự vô hiệu hoá tài khoản của chính mình.');
    }

    public function errorCode(): string
    {
        return 'CANNOT_DISABLE_SELF';
    }
}
