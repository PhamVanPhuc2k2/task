<?php

declare(strict_types=1);

namespace App\Support\Exceptions;

/**
 * Chặn nộp báo cáo cho ngày ngoài khoảng cho phép.
 *
 * Ném từ Action chứ không chỉ dựa vào `FormRequest`: luật này là **chính sách
 * nghiệp vụ**, không phải luật định dạng dữ liệu. Chỉ chặn ở tầng nhận request
 * thì bất kỳ đường nào khác gọi tới Action sau này — một lệnh nhập liệu, một
 * job đồng bộ — đều đi vòng qua được mà không ai nhận ra.
 */
final class ReportDateOutOfWindowException extends DomainException
{
    public function errorCode(): string
    {
        return 'REPORT_DATE_OUT_OF_WINDOW';
    }

    /**
     * @return array<string, list<string>>
     */
    public function fieldErrors(): array
    {
        return ['report_date' => [$this->getMessage()]];
    }
}
