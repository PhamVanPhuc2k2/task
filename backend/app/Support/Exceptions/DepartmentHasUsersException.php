<?php

declare(strict_types=1);

namespace App\Support\Exceptions;

/**
 * Chặn xoá phòng ban còn nhân sự.
 *
 * `users.department_id` khai `nullOnDelete`, nhưng điều đó chỉ có hiệu lực khi
 * xoá CỨNG. Xoá mềm để lại nhân sự trỏ vào một phòng ban mà mọi truy vấn đều
 * lọc mất, nên `$user->department` trả về null: họ không thuộc phòng nào, mà
 * cũng không hiện ra ở đâu để ai sửa.
 *
 * Với người quản lý thì đây là mất người, không phải mất một dòng dữ liệu — cả
 * chấm công, đơn nghỉ lẫn báo cáo ngày của họ đều rơi khỏi màn hình của phòng.
 *
 * Muốn dừng dùng một phòng ban mà vẫn giữ lịch sử thì tắt `is_active`: nó biến
 * mất khỏi các ô chọn nhưng vẫn nằm trong cây, và mọi người trong đó vẫn nhìn
 * thấy được.
 */
final class DepartmentHasUsersException extends DomainException
{
    public function __construct(string $ten, int $soNhanSu)
    {
        parent::__construct(sprintf(
            'Không xoá được "%s": còn %d nhân sự đang thuộc phòng ban này. Chuyển họ sang phòng ban khác trước, hoặc tắt "Đang hoạt động" để ngừng dùng mà vẫn giữ lịch sử.',
            $ten,
            $soNhanSu,
        ));
    }

    public function errorCode(): string
    {
        return 'DEPARTMENT_HAS_USERS';
    }
}
