<?php

declare(strict_types=1);

namespace App\Support\Exceptions;

/**
 * Chặn sửa quỹ thưởng đã chốt.
 *
 * Sau khi chốt, nhân viên đã xem được phần của mình. Sửa con số lúc đó — dù để
 * tăng — cũng bị đọc thành "bị cắt bớt", và không còn mốc nào để đối chiếu khi
 * có tranh cãi.
 */
final class BonusPoolNotEditableException extends DomainException
{
    public function __construct(string $trangThai)
    {
        parent::__construct(sprintf(
            'Quỹ thưởng đang ở trạng thái "%s" nên không sửa được nữa. Nhân viên đã xem được phần của mình.',
            $trangThai,
        ));
    }

    public function errorCode(): string
    {
        return 'BONUS_POOL_NOT_EDITABLE';
    }
}
