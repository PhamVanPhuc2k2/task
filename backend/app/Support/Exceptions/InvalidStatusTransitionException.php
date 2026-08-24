<?php

declare(strict_types=1);

namespace App\Support\Exceptions;

/**
 * Chuyển trạng thái task không hợp lệ.
 *
 * Luồng hợp lệ khai báo ở `TaskStatus::allowedTransitions()`. Không cho nhảy
 * tuỳ tiện — một task chưa bắt đầu mà báo hoàn thành thì mọi thống kê tiến độ
 * đều sai.
 */
final class InvalidStatusTransitionException extends DomainException
{
    public function __construct(
        private readonly string $from,
        private readonly string $to,
    ) {
        parent::__construct(sprintf(
            'Không thể chuyển task từ trạng thái "%s" sang "%s".',
            $from,
            $to,
        ));
    }

    public function errorCode(): string
    {
        return 'INVALID_STATUS_TRANSITION';
    }

    /**
     * @return array<string, list<string>>
     */
    public function fieldErrors(): array
    {
        return ['status' => [$this->getMessage()]];
    }
}
