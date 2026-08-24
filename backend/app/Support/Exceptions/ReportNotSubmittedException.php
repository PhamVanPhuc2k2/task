<?php

declare(strict_types=1);

namespace App\Support\Exceptions;

/**
 * Chặn đọc/nhận xét một báo cáo còn đang là bản nháp.
 *
 * Bản nháp là chỗ người dùng viết dở rồi quay lại, không phải thứ chờ ai đọc.
 * Cho phép nhận xét lên bản nháp nghĩa là quản lý đọc được câu chữ mà nhân viên
 * chưa muốn cho ai xem.
 */
final class ReportNotSubmittedException extends DomainException
{
    public function __construct()
    {
        parent::__construct('Báo cáo này vẫn là bản nháp, nhân viên chưa nộp.');
    }

    public function errorCode(): string
    {
        return 'REPORT_NOT_SUBMITTED';
    }
}
