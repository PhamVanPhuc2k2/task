<?php

declare(strict_types=1);

namespace App\Support\Exceptions;

/**
 * Chặn đơn nghỉ nằm ngoài khoảng ngày cho phép.
 *
 * Ném từ Action chứ không chỉ dựa vào FormRequest: đây là chính sách nghiệp vụ,
 * không phải luật định dạng dữ liệu. Cùng lý do với ReportDateOutOfWindow.
 */
final class LeaveDateOutOfWindowException extends DomainException
{
    public function errorCode(): string
    {
        return 'LEAVE_DATE_OUT_OF_WINDOW';
    }

    /**
     * @return array<string, list<string>>
     */
    public function fieldErrors(): array
    {
        return ['start_date' => [$this->getMessage()]];
    }
}
