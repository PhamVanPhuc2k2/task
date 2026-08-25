<?php

declare(strict_types=1);

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Models\Department;
use App\Domain\Identity\Models\User;
use App\Support\Exceptions\DepartmentHasChildrenException;
use App\Support\Exceptions\DepartmentHasUsersException;

/**
 * Xoá mềm một phòng ban.
 *
 * ── Vì sao phải tự kiểm, khi database đã có ràng buộc ────────────────────────
 *
 * Migration khai `restrictOnDelete` cho `parent_id` và `nullOnDelete` cho
 * `users.department_id`. Cả hai chỉ có hiệu lực khi xoá **cứng**. Phòng ban
 * dùng xoá mềm, nên với database thì không có gì bị xoá cả — chỉ là một cột
 * ngày được điền — và cả hai ràng buộc đều im lặng.
 *
 * Không kiểm ở đây thì xoá một phòng ban làm mọi phòng con và mọi nhân sự bên
 * dưới **rơi khỏi cây** mà không có lỗi nào: `subtreeIds()` của phòng cấp trên
 * không còn với tới họ, nên họ biến mất khỏi bảng công, khỏi danh sách đơn
 * nghỉ, khỏi báo cáo của phòng. Người quản lý chỉ thấy màn hình ngắn đi.
 *
 * Đây đúng loại hỏng im lặng dự án này liên tục phải trả giá, nên chặn thẳng
 * và nói rõ phải làm gì trước.
 */
final class DeleteDepartmentAction
{
    public function execute(Department $phongBan): void
    {
        $soPhongCon = $phongBan->children()->count();

        if ($soPhongCon > 0) {
            throw new DepartmentHasChildrenException($phongBan->name, $soPhongCon);
        }

        // Đếm CẢ người đã nghỉ việc, không chỉ người đang làm.
        //
        // Người đã nghỉ vẫn giữ nguyên `department_id` để lịch sử chấm công và
        // bảng lương cũ còn đọc được theo phòng ban. Bỏ qua họ ở đây thì xoá
        // phòng ban làm hỏng đúng phần lịch sử đó, mà lại không có gì cảnh báo.
        $soNhanSu = User::query()
            ->where('department_id', $phongBan->id)
            ->count();

        if ($soNhanSu > 0) {
            throw new DepartmentHasUsersException($phongBan->name, $soNhanSu);
        }

        $phongBan->delete();
    }
}
