<?php

declare(strict_types=1);

namespace App\Support\Exceptions;

/**
 * Chặn quản trị viên tự đổi vai trò của chính mình.
 *
 * Cùng một loại bẫy với `CannotDisableSelfException`, chỉ là cửa khác: quản
 * trị viên cuối cùng hạ vai trò mình xuống "Nhân viên" là mất luôn quyền
 * `user.manage`, và không còn ai trong hệ thống nâng lại được — phải sửa thẳng
 * database.
 *
 * Chặn ở chính mình là đủ. Đổi vai trò NGƯỜI KHÁC không tạo ra tình huống đó,
 * vì người thao tác vẫn giữ nguyên quyền của họ sau khi đổi.
 */
final class CannotChangeOwnRoleException extends DomainException
{
    public function __construct()
    {
        parent::__construct(
            'Bạn không thể tự đổi vai trò của chính mình. Nhờ một quản trị viên khác thực hiện.',
        );
    }

    public function errorCode(): string
    {
        return 'CANNOT_CHANGE_OWN_ROLE';
    }

    /**
     * @return array<string, list<string>>
     */
    public function fieldErrors(): array
    {
        return ['role' => [$this->getMessage()]];
    }
}
