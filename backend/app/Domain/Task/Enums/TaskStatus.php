<?php

declare(strict_types=1);

namespace App\Domain\Task\Enums;

/**
 * Trạng thái của một task.
 *
 * Giá trị lưu dưới dạng chuỗi để đọc dump database vẫn hiểu được, và thêm
 * trạng thái mới không phải migrate — xem README, "Quy ước dữ liệu".
 */
enum TaskStatus: string
{
    case Todo = 'todo';
    case InProgress = 'in_progress';
    case Review = 'review';
    case Done = 'done';
    case OnHold = 'on_hold';
    case Cancelled = 'cancelled';

    /** Nhãn hiển thị cho người dùng. */
    public function label(): string
    {
        return match ($this) {
            self::Todo => 'Chưa bắt đầu',
            self::InProgress => 'Đang làm',
            self::Review => 'Chờ duyệt',
            self::Done => 'Hoàn thành',
            self::OnHold => 'Tạm dừng',
            self::Cancelled => 'Đã huỷ',
        };
    }

    /**
     * Các trạng thái được phép chuyển tới từ trạng thái hiện tại.
     *
     * Không cho nhảy tuỳ tiện: một task chưa bắt đầu thì không thể nhảy thẳng
     * sang hoàn thành. Việc kiểm tra thực thi ở State machine tại mục 1.4.
     *
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Todo => [self::InProgress, self::OnHold, self::Cancelled],
            self::InProgress => [self::Review, self::OnHold, self::Cancelled],
            self::Review => [self::Done, self::InProgress],
            self::OnHold => [self::Todo, self::InProgress, self::Cancelled],
            self::Done, self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), strict: true);
    }

    /** Trạng thái kết thúc, không tính vào task đang mở. */
    public function isClosed(): bool
    {
        return $this === self::Done || $this === self::Cancelled;
    }

    /**
     * Giá trị của các trạng thái đã kết thúc, dùng trực tiếp trong truy vấn.
     *
     * Có hàm này để câu `whereNotIn('status', [...])` không phải liệt kê tay ở
     * mỗi chỗ dùng — thêm một trạng thái kết thúc mới ở đợt sau mà quên sửa một
     * chỗ thì con số thống kê sai lệch mà không có gì báo.
     *
     * @return list<string>
     */
    public static function closedValues(): array
    {
        return array_values(array_map(
            fn (self $status): string => $status->value,
            array_filter(self::cases(), fn (self $status): bool => $status->isClosed()),
        ));
    }
}
