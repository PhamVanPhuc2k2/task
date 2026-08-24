<?php

declare(strict_types=1);

namespace App\Support\Exceptions;

/**
 * Chặn ẩn danh người còn đang làm việc.
 *
 * Ẩn danh **không đảo ngược được**: sau khi chạy, không có cách nào biết dòng
 * đó từng là ai. Áp nhầm lên người đang làm nghĩa là họ mất tài khoản giữa ngày
 * làm việc và không ai khôi phục lại được.
 *
 * Quy trình đúng là vô hiệu hoá trước (`is_active = false`, ghi `terminated_at`),
 * để một khoảng đủ dài cho mọi việc còn dở được xử lý, rồi mới ẩn danh.
 */
final class CannotAnonymiseActiveUserException extends DomainException
{
    public function __construct()
    {
        parent::__construct(
            'Chỉ ẩn danh được người đã nghỉ việc. Hãy vô hiệu hoá tài khoản trước.',
        );
    }

    public function errorCode(): string
    {
        return 'USER_STILL_ACTIVE';
    }
}
