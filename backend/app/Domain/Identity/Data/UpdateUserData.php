<?php

declare(strict_types=1);

namespace App\Domain\Identity\Data;

use App\Domain\Identity\Enums\Role;

/**
 * Hồ sơ nhân viên sau khi sửa — **toàn bộ**, không phải phần chênh lệch.
 *
 * Cố ý chọn ngữ nghĩa "thay thế toàn bộ" (PUT) thay vì "sửa vài trường"
 * (PATCH). Với PATCH, một trường mang giá trị `null` có hai nghĩa không phân
 * biệt được: "xoá quản lý trực tiếp của người này" và "tôi không đụng tới
 * trường quản lý". Muốn tách hai nghĩa đó phải dựng thêm khái niệm "có gửi
 * hay không" cho từng trường — chi phí đó chỉ đáng bỏ ra khi thật sự có nhiều
 * chỗ gọi khác nhau, mà ở đây chỉ có đúng một form.
 *
 * Với PUT thì `null` luôn có đúng một nghĩa: bỏ trống. Form ở giao diện gửi
 * đủ mọi trường mỗi lần lưu, nên đây cũng chính là điều người dùng thấy.
 */
final readonly class UpdateUserData
{
    public function __construct(
        public string $name,
        public string $email,
        public string $employeeCode,
        public Role $role,
        public ?string $phone = null,
        public ?int $departmentId = null,
        public ?int $positionId = null,
        public ?int $managerId = null,
        public ?string $joinedAt = null,
    ) {}
}
