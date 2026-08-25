<?php

declare(strict_types=1);

namespace App\Support\Exceptions;

/**
 * Chặn xoá phòng ban còn phòng ban con.
 *
 * Database đã chặn xoá CỨNG bằng `restrictOnDelete`, nhưng phòng ban dùng xoá
 * mềm — mà xoá mềm thì ràng buộc khoá ngoại không nói gì cả. Phòng con vẫn trỏ
 * `parent_id` vào một bản ghi đã biến mất khỏi mọi truy vấn, nên chúng rơi ra
 * khỏi cây: không còn hiện trong sơ đồ, và `subtreeIds()` của phòng cấp trên
 * không còn với tới chúng.
 *
 * Nghĩa là nhân sự trong các phòng con **lặng lẽ biến mất khỏi tầm nhìn** của
 * mọi trưởng phòng ở nhánh trên. Không có lỗi nào, chỉ là bảng công ngắn đi.
 */
final class DepartmentHasChildrenException extends DomainException
{
    public function __construct(string $ten, int $soPhongCon)
    {
        parent::__construct(sprintf(
            'Không xoá được "%s": còn %d phòng ban trực thuộc. Chuyển hoặc xoá các phòng ban con trước.',
            $ten,
            $soPhongCon,
        ));
    }

    public function errorCode(): string
    {
        return 'DEPARTMENT_HAS_CHILDREN';
    }
}
