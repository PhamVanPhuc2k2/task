<?php

declare(strict_types=1);

namespace App\Domain\Task\Enums;

enum ProjectStatus: string
{
    case Planning = 'planning';
    case Active = 'active';
    case OnHold = 'on_hold';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Planning => 'Đang lên kế hoạch',
            self::Active => 'Đang chạy',
            self::OnHold => 'Tạm dừng',
            self::Completed => 'Hoàn thành',
            self::Cancelled => 'Đã huỷ',
        };
    }

    /** Dự án còn mở thì mới cho tạo task mới vào. */
    public function isOpen(): bool
    {
        return match ($this) {
            self::Planning, self::Active, self::OnHold => true,
            self::Completed, self::Cancelled => false,
        };
    }

    /**
     * Giá trị của các trạng thái còn mở, dùng trực tiếp trong câu truy vấn.
     *
     * @return list<string>
     */
    public static function openValues(): array
    {
        return array_values(array_map(
            fn (self $status): string => $status->value,
            array_filter(self::cases(), fn (self $status): bool => $status->isOpen()),
        ));
    }
}
