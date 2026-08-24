<?php

declare(strict_types=1);

namespace App\Support\Exceptions;

/**
 * Chặn sửa báo cáo mà quản lý đã đọc.
 *
 * Nộp rồi vẫn sửa được — cố ý, vì khoá ngay sau khi nộp chỉ khiến người ta ngại
 * nộp sớm rồi dồn hết vào cuối tuần. Nhưng khi đã có người đọc thì thôi: sửa
 * lúc đó là đổi thứ người khác đã đọc và đã dựa vào để nhận xét.
 */
final class ReportNotEditableException extends DomainException
{
    public function __construct(string $trangThai)
    {
        parent::__construct(sprintf(
            'Báo cáo đang ở trạng thái "%s" nên không sửa được nữa. Trao đổi thêm với quản lý nếu cần bổ sung.',
            $trangThai,
        ));
    }

    public function errorCode(): string
    {
        return 'REPORT_NOT_EDITABLE';
    }
}
