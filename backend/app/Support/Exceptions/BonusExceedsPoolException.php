<?php

declare(strict_types=1);

namespace App\Support\Exceptions;

/**
 * Chặn tổng phần chia vượt quá quỹ.
 *
 * Kiểm ở đây chứ không để người dùng tự cộng: chia vượt quỹ là lỗi kế toán phát
 * hiện ra sau, lúc tiền đã hứa với nhân viên rồi — và không có cách nào rút lại
 * mà không mất mặt với người bị rút.
 */
final class BonusExceedsPoolException extends DomainException
{
    public function __construct(string $daChia, string $quy)
    {
        parent::__construct(sprintf(
            'Tổng phần chia là %s, vượt quá quỹ %s. Giảm bớt phần của ai đó hoặc nâng quỹ lên trước.',
            number_format((float) $daChia, 0, ',', '.'),
            number_format((float) $quy, 0, ',', '.'),
        ));
    }

    public function errorCode(): string
    {
        return 'BONUS_EXCEEDS_POOL';
    }
}
