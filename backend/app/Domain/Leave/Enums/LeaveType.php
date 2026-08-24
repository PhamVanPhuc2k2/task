<?php

declare(strict_types=1);

namespace App\Domain\Leave\Enums;

/**
 * Loại nghỉ.
 *
 * Ở phạm vi này, loại nghỉ **chỉ là nhãn** — nó không trừ vào quỹ nào, không
 * đổi cách tính công. Nó tồn tại vì quản lý cần biết mình đang duyệt cái gì:
 * duyệt nghỉ ốm và duyệt nghỉ việc riêng là hai quyết định khác nhau, dù hệ
 * thống xử lý giống hệt.
 *
 * Khi có quỹ phép (đợt 4 đầy đủ) thì chính enum này là chỗ gắn "loại nào trừ
 * quỹ nào" — nên tách sẵn từ bây giờ, không gộp thành một ô ghi chú tự do.
 */
enum LeaveType: string
{
    case Annual = 'annual';
    case Sick = 'sick';
    case Unpaid = 'unpaid';
    case Personal = 'personal';

    public function label(): string
    {
        return match ($this) {
            self::Annual => 'Nghỉ phép năm',
            self::Sick => 'Nghỉ ốm',
            self::Unpaid => 'Nghỉ không lương',
            self::Personal => 'Nghỉ việc riêng',
        };
    }
}
