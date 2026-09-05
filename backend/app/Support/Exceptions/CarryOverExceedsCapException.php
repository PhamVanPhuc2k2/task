<?php

declare(strict_types=1);

namespace App\Support\Exceptions;

/**
 * Số phép tồn nhân sự nhập vượt trần công ty cho phép.
 *
 * Trần nằm ở cấu hình chứ không viết cứng, và `0` nghĩa là công ty **cấm**
 * chuyển tiếp phép — nên câu chữ phải phân biệt hai trường hợp, vì "tối đa 0
 * ngày" là câu không ai hiểu.
 */
final class CarryOverExceedsCapException extends DomainException
{
    public function __construct(int $tran)
    {
        parent::__construct(
            $tran <= 0
                ? 'Công ty không cho chuyển phép tồn sang năm sau. Đổi ở màn Cài đặt nếu chính sách đã khác.'
                : sprintf('Phép tồn chuyển sang năm sau tối đa %d ngày.', $tran),
        );
    }

    public function errorCode(): string
    {
        return 'CARRY_OVER_EXCEEDS_CAP';
    }

    /**
     * @return array<string, list<string>>
     */
    public function fieldErrors(): array
    {
        return ['carried_over_days' => [$this->getMessage()]];
    }
}
