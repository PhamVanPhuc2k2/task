<?php

declare(strict_types=1);

namespace App\Domain\Payroll\Data;

/**
 * Mức lương mới cần đặt.
 *
 * Tiền là **chuỗi**, không phải float. Nhận `"12500000.50"` từ HTTP rồi giữ
 * nguyên chuỗi đó tới lúc ghi vào cột DECIMAL — ép qua float ở giữa là mở đúng
 * cái cửa sai số mà kiểu DECIMAL sinh ra để đóng.
 */
final readonly class SetSalaryData
{
    public function __construct(
        public string $baseSalary,
        public string $allowance,
        public string $effectiveFrom,
        public string $reason,
        public string $currency = 'VND',
    ) {}
}
