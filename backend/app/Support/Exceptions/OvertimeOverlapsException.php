<?php

declare(strict_types=1);

namespace App\Support\Exceptions;

/**
 * Khoảng giờ làm thêm chồng lấn với một đơn còn hiệu lực.
 *
 * Một người làm thêm hai lần trong ngày là chuyện bình thường — sáng sớm một
 * tiếng, tối hai tiếng. Thứ bị cấm là **hai đơn phủ cùng một khoảng giờ**: cộng
 * cả hai vào là trả tiền hai lần cho cùng một giờ làm.
 *
 * Câu chữ nói ra đơn nào đang chắn, vì màn hình có thể đang cuộn ở chỗ khác.
 */
final class OvertimeOverlapsException extends DomainException
{
    public function __construct(string $ngay, string $tuGio, string $denGio)
    {
        parent::__construct(sprintf(
            'Ngày %s bạn đã có đơn làm thêm từ %s đến %s. Hai khoảng giờ không được chồng lên nhau.',
            implode('/', array_reverse(explode('-', $ngay))),
            $tuGio,
            $denGio,
        ));
    }

    public function errorCode(): string
    {
        return 'OVERTIME_OVERLAPS';
    }

    /**
     * @return array<string, list<string>>
     */
    public function fieldErrors(): array
    {
        return ['start_time' => [$this->getMessage()]];
    }
}
