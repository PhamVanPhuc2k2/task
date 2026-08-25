<?php

declare(strict_types=1);

namespace App\Support\Exceptions;

/**
 * Chặn vòng trong cây phòng ban.
 *
 * Chuyển một phòng ban vào nằm dưới chính cấp dưới của nó — hoặc dưới chính
 * nó — biến một nhánh cây thành vòng tròn không có gốc.
 *
 * Hậu quả không dừng ở "sơ đồ nhìn kỳ". `Department::descendantIds()` duyệt
 * cây bằng hàng đợi, và một vòng khiến hàng đợi không bao giờ rỗng: request
 * treo tới hết timeout, php-fpm giữ nguyên tiến trình, log không có dòng nào.
 * Mà hàm đó đỡ 13 chỗ phân quyền, nên một bản ghi hỏng làm chết cả chấm công
 * lẫn nghỉ phép lẫn báo cáo cùng lúc.
 *
 * Chặn ngay lúc ghi rẻ hơn nhiều so với dò tìm sau — cùng lý do với
 * [[ManagerCycleException]].
 */
final class DepartmentCycleException extends DomainException
{
    public function __construct(string $tenPhongBanCha)
    {
        parent::__construct(sprintf(
            'Không chuyển được vào "%s": phòng ban này đang nằm dưới phòng ban được sửa, sẽ tạo thành vòng trong cơ cấu tổ chức.',
            $tenPhongBanCha,
        ));
    }

    public function errorCode(): string
    {
        return 'DEPARTMENT_CYCLE';
    }

    /**
     * @return array<string, list<string>>
     */
    public function fieldErrors(): array
    {
        return ['parent_id' => [$this->getMessage()]];
    }
}
