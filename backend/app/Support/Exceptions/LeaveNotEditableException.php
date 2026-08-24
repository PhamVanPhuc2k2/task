<?php

declare(strict_types=1);

namespace App\Support\Exceptions;

/**
 * Chặn sửa hoặc rút đơn đã có người xử lý.
 *
 * Đơn đã duyệt là căn cứ để bảng công miễn chấm ngày đó. Rút ngược lại sau khi
 * duyệt nghĩa là bảng công của một ngày trong quá khứ đổi nghĩa mà không ai
 * biết — cùng lý do quỹ thưởng đã chốt thì không mở lại được.
 *
 * Cần sửa thì nộp đơn mới và giữ nguyên vết cũ.
 */
final class LeaveNotEditableException extends DomainException
{
    public function __construct(string $trangThai)
    {
        parent::__construct(sprintf(
            'Đơn đang ở trạng thái "%s" nên không đổi được nữa. Trao đổi với quản lý nếu cần điều chỉnh.',
            $trangThai,
        ));
    }

    public function errorCode(): string
    {
        return 'LEAVE_NOT_EDITABLE';
    }
}
