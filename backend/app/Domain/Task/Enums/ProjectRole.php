<?php

declare(strict_types=1);

namespace App\Domain\Task\Enums;

/**
 * Vai trò trong phạm vi một dự án.
 *
 * Khác với vai trò hệ thống (`admin`, `truong_phong`...): một trưởng phòng có
 * thể chỉ là thành viên bình thường ở dự án của phòng khác.
 */
enum ProjectRole: string
{
    case Manager = 'manager';
    case Member = 'member';
    case Viewer = 'viewer';

    public function label(): string
    {
        return match ($this) {
            self::Manager => 'Quản lý dự án',
            self::Member => 'Thành viên',
            self::Viewer => 'Chỉ xem',
        };
    }

    /** Có được giao task cho người khác trong dự án này không. */
    public function canAssignTasks(): bool
    {
        return $this === self::Manager;
    }
}
