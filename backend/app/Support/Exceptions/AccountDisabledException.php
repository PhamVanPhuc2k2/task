<?php

declare(strict_types=1);

namespace App\Support\Exceptions;

use Illuminate\Http\Response;

final class AccountDisabledException extends DomainException
{
    public function __construct()
    {
        parent::__construct('Tài khoản của bạn đã bị vô hiệu hoá. Liên hệ bộ phận nhân sự để biết thêm chi tiết.');
    }

    public function errorCode(): string
    {
        return 'ACCOUNT_DISABLED';
    }

    public function httpStatus(): int
    {
        return Response::HTTP_FORBIDDEN;
    }
}
