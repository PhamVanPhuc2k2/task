<?php

declare(strict_types=1);

namespace App\Domain\Payroll\Data;

/**
 * Một dòng làm thêm giờ trên phiếu lương, gom theo hệ số.
 *
 * Gom theo hệ số chứ không liệt kê từng đơn: phiếu lương của một tháng có thể
 * có mười lăm đơn làm thêm, và người đọc cần biết *"6 giờ ở 150%, 4 giờ ở
 * 200%"* chứ không cần mười lăm dòng ngày giờ. Chi tiết từng đơn vẫn tra được
 * ở màn Làm thêm.
 */
final readonly class OvertimeLine
{
    /**
     * @param  int  $percent  Hệ số phần trăm — 150, 200 hoặc 300 (Điều 98).
     * @param  numeric-string  $amount  Tiền của dòng này, số nguyên đồng.
     */
    public function __construct(
        public int $percent,
        public int $minutes,
        public string $amount,
    ) {}
}
