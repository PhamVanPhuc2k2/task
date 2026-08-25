<?php

declare(strict_types=1);

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Data\DepartmentData;
use App\Domain\Identity\Models\Department;

final class CreateDepartmentAction
{
    public function execute(DepartmentData $data): Department
    {
        $phongBan = new Department;

        $phongBan->fill([
            'name' => $data->name,
            // Chuỗi rỗng và null là hai thứ khác nhau ở cột này: `code` có ràng
            // buộc unique, nên hai phòng ban cùng để trống mà lưu thành '' sẽ
            // đụng nhau — và thông báo lỗi sẽ nói về "mã phòng ban đã tồn tại"
            // trong khi người dùng không hề nhập mã nào.
            'code' => $this->rong($data->code),
            'description' => $this->rong($data->description),
            'parent_id' => $data->parentId,
            'is_active' => $data->isActive,
        ]);

        $phongBan->save();

        return $phongBan;
    }

    private function rong(?string $giaTri): ?string
    {
        if ($giaTri === null) {
            return null;
        }

        $sach = trim($giaTri);

        return $sach === '' ? null : $sach;
    }
}
