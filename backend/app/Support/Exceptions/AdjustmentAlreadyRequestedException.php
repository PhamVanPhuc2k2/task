<?php

declare(strict_types=1);

namespace App\Support\Exceptions;

/**
 * Đã có đơn giải trình còn hiệu lực cho ngày này.
 *
 * "Còn hiệu lực" là chờ duyệt hoặc đã duyệt. Đơn bị từ chối hay đã rút KHÔNG
 * chặn — giải trình lại cho rõ hơn sau khi bị từ chối là chuyện bình thường, và
 * cấm điều đó thì người ta quay về nhắn tin cho quản lý, đúng cái việc module
 * này sinh ra để thay.
 */
final class AdjustmentAlreadyRequestedException extends DomainException
{
    public function __construct(string $ngay)
    {
        parent::__construct(sprintf(
            'Ngày %s đã có đơn giải trình đang chờ duyệt hoặc đã được duyệt.',
            implode('/', array_reverse(explode('-', $ngay))),
        ));
    }

    public function errorCode(): string
    {
        return 'ADJUSTMENT_ALREADY_REQUESTED';
    }

    /**
     * @return array<string, list<string>>
     */
    public function fieldErrors(): array
    {
        return ['work_date' => [$this->getMessage()]];
    }
}
