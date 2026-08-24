<?php

declare(strict_types=1);

namespace App\Support\Exceptions;

/**
 * Chặn vòng lặp trong chuỗi quản lý trực tiếp.
 *
 * A quản lý B, rồi đặt quản lý của A là B — sơ đồ tổ chức thành một vòng tròn
 * không có người đứng đầu. Đợt 1 chưa có chỗ nào duyệt ngược lên theo
 * `manager_id` nên vòng lặp chưa gây treo, nhưng đợt 2 (duyệt đơn nghỉ phép
 * theo cấp trên) thì có, và lúc đó dữ liệu hỏng đã nằm sẵn trong database từ
 * lâu. Chặn ngay lúc ghi rẻ hơn nhiều so với dò tìm sau.
 *
 * Bao gồm cả trường hợp tự làm quản lý của chính mình — đó là vòng lặp độ dài
 * một.
 */
final class ManagerCycleException extends DomainException
{
    public function __construct(string $tenNguoiQuanLy)
    {
        parent::__construct(sprintf(
            'Không đặt được %s làm quản lý trực tiếp: người này đang nằm dưới quyền quản lý của nhân viên được sửa, sẽ tạo thành vòng lặp trong sơ đồ tổ chức.',
            $tenNguoiQuanLy,
        ));
    }

    public function errorCode(): string
    {
        return 'MANAGER_CYCLE';
    }

    /**
     * @return array<string, list<string>>
     */
    public function fieldErrors(): array
    {
        return ['manager_id' => [$this->getMessage()]];
    }
}
