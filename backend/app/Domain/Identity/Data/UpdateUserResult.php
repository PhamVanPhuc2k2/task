<?php

declare(strict_types=1);

namespace App\Domain\Identity\Data;

use App\Domain\Identity\Models\User;

/**
 * Kết quả sửa hồ sơ nhân viên.
 *
 * Mang theo `warnings` — những hệ quả **đúng nhưng dễ bất ngờ** của thao tác
 * vừa làm. Đây không phải lỗi: thao tác đã thành công, và chặn lại thì sai.
 * Nhưng im lặng cũng sai, vì người bấm nút thường không nghĩ tới. Ví dụ điển
 * hình: chuyển một trưởng phòng sang phòng khác thì từ giây đó họ không còn
 * nhìn thấy công việc của đội cũ nữa, mà trên màn hình chẳng có gì nói vậy.
 */
final readonly class UpdateUserResult
{
    /**
     * @param  list<string>  $warnings
     */
    public function __construct(
        public User $user,
        public array $warnings = [],
    ) {}
}
