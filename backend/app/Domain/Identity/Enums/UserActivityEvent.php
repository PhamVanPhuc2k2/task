<?php

declare(strict_types=1);

namespace App\Domain\Identity\Enums;

/**
 * Loại thay đổi được ghi vào nhật ký nhân sự.
 *
 * Là enum chứ không phải chuỗi tự do — khác `task_activities` vốn chỉ có ba
 * biến cố do Observer sinh ra. Ở đây mỗi biến cố đến từ một Action riêng, và
 * chuỗi tự do thì chỉ cần một lần gõ nhầm `"deactivated"` thành `"deactivate"`
 * là bộ lọc nhật ký im lặng bỏ sót, không có gì báo.
 */
enum UserActivityEvent: string
{
    case Created = 'created';
    case ProfileUpdated = 'profile_updated';
    case RoleChanged = 'role_changed';
    case Deactivated = 'deactivated';
    case Activated = 'activated';
    case PasswordReset = 'password_reset';
    case TwoFactorReset = 'two_factor_reset';

    /** Đã xoá dữ liệu cá nhân theo Nghị định 13 — không đảo ngược được. */
    case Anonymised = 'anonymised';

    public function label(): string
    {
        return match ($this) {
            self::Created => 'Tạo tài khoản',
            self::ProfileUpdated => 'Cập nhật hồ sơ',
            self::RoleChanged => 'Đổi vai trò',
            self::Deactivated => 'Vô hiệu hoá',
            self::Activated => 'Kích hoạt lại',
            self::PasswordReset => 'Đặt lại mật khẩu',
            self::TwoFactorReset => 'Gỡ xác thực hai lớp',
            self::Anonymised => 'Xoá dữ liệu cá nhân',
        };
    }
}
