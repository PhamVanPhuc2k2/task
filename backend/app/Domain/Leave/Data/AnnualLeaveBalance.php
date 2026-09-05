<?php

declare(strict_types=1);

namespace App\Domain\Leave\Data;

/**
 * Quỹ phép năm của một người trong một năm, đã tính xong.
 *
 * Giữ **cả bốn số nguồn** chứ không chỉ số còn lại, vì câu hỏi thật của người
 * dùng không phải *"tôi còn mấy ngày"* mà là *"vì sao tôi còn ngần này"*. Trả
 * mỗi số dư thì mọi thắc mắc đều phải hỏi nhân sự, và nhân sự cũng phải mở
 * database ra xem.
 *
 * `computedEntitledDays` giữ lại con số **hệ thống tự tính** kể cả khi đã bị
 * ghi đè: màn hình nói được *"tự tính 12, nhân sự đặt 15"*, và ba tháng sau vẫn
 * trả lời được câu "ai đổi, từ bao nhiêu".
 */
final readonly class AnnualLeaveBalance
{
    public function __construct(
        public int $year,
        /** Số ngày được hưởng — đã áp ghi đè nếu có. */
        public float $entitledDays,
        /** Số hệ thống tự tính theo Điều 113 và 114, trước khi ghi đè. */
        public float $computedEntitledDays,
        /** Phép tồn năm trước do nhân sự chuyển sang. */
        public float $carriedOverDays,
        /** Điều chỉnh tay, được phép âm. */
        public float $adjustmentDays,
        /** Đã dùng — tính từ `leave_requests`, gồm cả đơn ĐANG CHỜ DUYỆT. */
        public float $usedDays,
        public bool $isOverridden,
        public ?string $note = null,
    ) {}

    /** Tổng quỹ trước khi trừ đi phần đã dùng. */
    public function totalDays(): float
    {
        return $this->entitledDays + $this->carriedOverDays + $this->adjustmentDays;
    }

    /**
     * Số ngày còn lại. **Được phép âm.**
     *
     * Âm nghĩa là ai đó đã duyệt vượt quỹ — có thể do nhân sự trừ bớt quỹ sau
     * khi đơn đã duyệt, hoặc do đổi chính sách giữa năm. Kẹp về 0 sẽ giấu mất
     * đúng cái tình huống cần người nhìn tới.
     */
    public function remainingDays(): float
    {
        return $this->totalDays() - $this->usedDays;
    }

    /** Có đủ chỗ cho thêm ngần này ngày không. */
    public function fits(float $themNgay): bool
    {
        return $this->usedDays + $themNgay <= $this->totalDays();
    }
}
