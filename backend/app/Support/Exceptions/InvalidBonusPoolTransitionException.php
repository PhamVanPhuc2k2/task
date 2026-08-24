<?php

declare(strict_types=1);

namespace App\Support\Exceptions;

/**
 * Chuyển trạng thái quỹ thưởng không hợp lệ.
 *
 * Luồng một chiều: đang lập → đã chốt → đã chi. Không có đường quay lại, và đó
 * là điểm mấu chốt — mở lại một quỹ đã chốt nghĩa là đổi được con số mà nhân
 * viên đã nhìn thấy.
 */
final class InvalidBonusPoolTransitionException extends DomainException
{
    public function __construct(string $tu, string $den)
    {
        parent::__construct(sprintf(
            'Không chuyển quỹ thưởng từ "%s" sang "%s" được.',
            $tu,
            $den,
        ));
    }

    public function errorCode(): string
    {
        return 'INVALID_BONUS_POOL_TRANSITION';
    }
}
