<?php

declare(strict_types=1);

namespace App\Domain\Task\Enums;

enum TaskPriority: string
{
    case Low = 'low';
    case Normal = 'normal';
    case High = 'high';
    case Urgent = 'urgent';

    public function label(): string
    {
        return match ($this) {
            self::Low => 'Thấp',
            self::Normal => 'Bình thường',
            self::High => 'Cao',
            self::Urgent => 'Khẩn cấp',
        };
    }

    /** Dùng để sắp xếp danh sách: số càng lớn càng gấp. */
    public function weight(): int
    {
        return match ($this) {
            self::Low => 0,
            self::Normal => 1,
            self::High => 2,
            self::Urgent => 3,
        };
    }
}
