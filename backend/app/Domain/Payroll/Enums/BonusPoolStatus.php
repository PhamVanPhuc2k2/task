<?php

declare(strict_types=1);

namespace App\Domain\Payroll\Enums;

/**
 * Trạng thái quỹ thưởng. Một chiều, không quay lại.
 *
 * Thiếu bước khoá thì con số thay đổi sau khi đã báo cho nhân viên — thứ phá
 * niềm tin nhanh nhất, và cũng là thứ khiến mọi tranh cãi về sau không có mốc
 * nào để đối chiếu.
 */
enum BonusPoolStatus: string
{
    /** Đang lập: sửa tổng quỹ và phần chia thoải mái. */
    case Draft = 'draft';

    /** Đã chốt: không sửa được nữa, nhân viên xem được phần của mình. */
    case Locked = 'locked';

    /** Đã chi: kế toán xác nhận đã trả. */
    case Distributed = 'distributed';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Đang lập',
            self::Locked => 'Đã chốt',
            self::Distributed => 'Đã chi',
        };
    }

    /** Còn sửa được tổng quỹ và phần chia không. */
    public function isEditable(): bool
    {
        return $this === self::Draft;
    }

    /**
     * Nhân viên đã được xem phần của mình chưa.
     *
     * Bản nháp không cho xem: con số lúc đó còn đổi, mà đã cho xem một lần thì
     * mọi lần đổi sau đều bị đọc thành "bị cắt bớt".
     */
    public function isVisibleToRecipient(): bool
    {
        return $this !== self::Draft;
    }

    /**
     * Chuyển từ trạng thái này sang trạng thái kia có hợp lệ không.
     *
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::Locked],
            self::Locked => [self::Distributed],
            self::Distributed => [],
        };
    }
}
