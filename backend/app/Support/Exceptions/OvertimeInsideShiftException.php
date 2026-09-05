<?php

declare(strict_types=1);

namespace App\Support\Exceptions;

/**
 * Khoảng giờ đăng ký làm thêm nằm trong ca làm bình thường.
 *
 * "Làm thêm từ 9h tới 11h" vào một ngày làm việc là hai tiếng đã được trả lương
 * bình thường rồi. Cho qua thì công ty trả 150% cho giờ vốn đã trả 100%, và
 * người nộp cũng không nhận ra mình vừa khai sai.
 *
 * Chỉ áp cho NGÀY CÓ CA. Ngày nghỉ hằng tuần và ngày lễ không có ca, nên mọi
 * mốc giờ đều là làm thêm.
 */
final class OvertimeInsideShiftException extends DomainException
{
    public function __construct(string $caTu, string $caDen)
    {
        parent::__construct(sprintf(
            'Hôm đó là ngày làm việc, ca từ %s đến %s — giờ làm thêm phải nằm ngoài khoảng này.',
            $caTu,
            $caDen,
        ));
    }

    public function errorCode(): string
    {
        return 'OVERTIME_INSIDE_SHIFT';
    }

    /**
     * @return array<string, list<string>>
     */
    public function fieldErrors(): array
    {
        return ['start_time' => [$this->getMessage()]];
    }
}
