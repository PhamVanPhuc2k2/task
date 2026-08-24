<?php

declare(strict_types=1);

namespace App\Support\Exceptions;

/**
 * Chặn mức lương mới có ngày hiệu lực không sau mức đang áp dụng.
 *
 * Nếu cho phép, hai dòng sẽ cùng hiệu lực trong một khoảng và câu hỏi "tháng
 * này người này hưởng mức nào" có hai đáp án. Bảng lương lúc đó không sai một
 * cách ồn ào — nó chỉ chọn bừa một dòng, thường là dòng có id nhỏ hơn.
 *
 * Ghi lùi ngày vẫn được phép, miễn là sau ngày bắt đầu của mức hiện hành: nhân
 * sự nhập muộn vài ngày là chuyện bình thường.
 */
final class SalaryPeriodOverlapException extends DomainException
{
    public function __construct(string $ngayHienHanh)
    {
        parent::__construct(sprintf(
            'Ngày hiệu lực phải sau %s — là ngày bắt đầu của mức lương đang áp dụng. Hai mức cùng hiệu lực một lúc thì không xác định được người này hưởng mức nào.',
            $ngayHienHanh,
        ));
    }

    public function errorCode(): string
    {
        return 'SALARY_PERIOD_OVERLAP';
    }

    /**
     * @return array<string, list<string>>
     */
    public function fieldErrors(): array
    {
        return ['effective_from' => [$this->getMessage()]];
    }
}
