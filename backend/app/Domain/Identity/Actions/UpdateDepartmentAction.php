<?php

declare(strict_types=1);

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Data\DepartmentData;
use App\Domain\Identity\Models\Department;
use App\Support\Exceptions\DepartmentCycleException;

/**
 * Sửa một phòng ban, gồm cả việc chuyển nó sang nhánh khác của cây.
 *
 * Ngữ nghĩa "thay thế toàn bộ" (PUT), giống `UpdateUserAction` và vì cùng một
 * lý do: với PATCH thì `parent_id: null` có hai nghĩa không phân biệt được —
 * "chuyển lên làm phòng ban gốc" và "tôi không đụng tới cha". Form ở giao diện
 * gửi đủ mọi trường mỗi lần lưu, nên PUT cũng chính là điều người dùng thấy.
 */
final class UpdateDepartmentAction
{
    public function execute(Department $phongBan, DepartmentData $data): Department
    {
        $this->chanVong($phongBan, $data->parentId);

        $phongBan->fill([
            'name' => $data->name,
            'code' => $this->rong($data->code),
            'description' => $this->rong($data->description),
            'parent_id' => $data->parentId,
            'is_active' => $data->isActive,
        ]);

        $phongBan->save();

        return $phongBan;
    }

    /**
     * Cha mới không được là chính nó, cũng không được là con cháu của nó.
     *
     * Dùng `descendantIds()` — đã có sẵn và đã chống lặp vô hạn — thay vì tự đi
     * ngược lên theo `parent_id` như `UpdateUserAction` làm với chuỗi quản lý.
     * Hai cách tương đương về kết quả; cách này chỉ một truy vấn thay vì một
     * truy vấn cho mỗi cấp.
     */
    private function chanVong(Department $phongBan, ?int $parentId): void
    {
        // Chuyển lên làm phòng ban gốc thì không bao giờ tạo vòng.
        if ($parentId === null) {
            return;
        }

        // Vòng độ dài một: tự làm cha của chính mình.
        if ($parentId === (int) $phongBan->id) {
            throw new DepartmentCycleException($phongBan->name);
        }

        if (! in_array($parentId, $phongBan->descendantIds(), strict: true)) {
            return;
        }

        $tenCha = Department::query()->whereKey($parentId)->value('name');

        throw new DepartmentCycleException(
            is_string($tenCha) ? $tenCha : 'phòng ban đã chọn',
        );
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
