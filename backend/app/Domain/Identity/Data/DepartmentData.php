<?php

declare(strict_types=1);

namespace App\Domain\Identity\Data;

/**
 * Dữ liệu tạo hoặc sửa một phòng ban.
 *
 * `parentId` dùng `int|null` cho phòng ban gốc, nên riêng nó KHÔNG phân biệt
 * được "không đổi" với "chuyển lên gốc" — cả hai đều là null. Vì vậy
 * `UpdateDepartmentData` tách riêng, có cờ nói rõ có đụng tới cha hay không.
 */
final readonly class DepartmentData
{
    public function __construct(
        public string $name,
        public ?string $code = null,
        public ?string $description = null,
        public ?int $parentId = null,
        public bool $isActive = true,
    ) {}
}
