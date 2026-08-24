<?php

declare(strict_types=1);

namespace App\Domain\Identity\Enums;

/**
 * Vai trò trong hệ thống.
 *
 * Khác với `Position` (chức vụ trên giấy tờ nhân sự): vai trò quyết định làm
 * được gì trong phần mềm. Một trưởng phòng trên danh nghĩa có thể chỉ được cấp
 * vai trò nhân viên nếu công ty muốn vậy.
 *
 * Khác với `ProjectRole` (vai trò trong phạm vi một dự án cụ thể).
 */
enum Role: string
{
    case Admin = 'admin';
    case GiamDoc = 'giam_doc';
    case TruongPhong = 'truong_phong';
    case NhanVien = 'nhan_vien';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Quản trị hệ thống',
            self::GiamDoc => 'Giám đốc',
            self::TruongPhong => 'Trưởng phòng',
            self::NhanVien => 'Nhân viên',
        };
    }

    /**
     * Quyền mặc định của vai trò.
     *
     * Đây chỉ là điểm khởi đầu do seeder gán. Sau khi cài đặt, quản trị viên
     * chỉnh lại quyền trong giao diện mà không cần sửa mã nguồn.
     *
     * @return list<Permission>
     */
    public function defaultPermissions(): array
    {
        return match ($this) {
            /*
             * Toàn quyền, kể cả quyền thêm mới ở các đợt sau — `Permission::cases()`
             * cuốn hết vào.
             *
             * Với quyền lương đây là điều cần biết rõ chứ không phải mặc nhiên:
             * người quản trị *hệ thống* thường là IT, không phải nhân sự, nên ở
             * nhiều công ty họ không nên xem được lương cả công ty. Công ty này
             * chọn để admin quản lý lương, nên giữ nguyên. Muốn tách sau này thì
             * liệt kê quyền tường minh ở đây thay vì `cases()`.
             */
            self::Admin => Permission::cases(),

            self::GiamDoc => [
                Permission::ViewOwnTasks,
                Permission::ViewTeamTasks,
                Permission::ViewAllTasks,
                Permission::CreateTask,
                Permission::UpdateTask,
                Permission::DeleteTask,
                Permission::AssignTask,
                Permission::ChangeTaskDueDate,
                Permission::ManageProjects,
                Permission::ViewReports,
                Permission::ViewTeamAttendance,
                Permission::ViewAllAttendance,
                Permission::ReviewAttendance,
                Permission::ViewTeamReports,
                Permission::ReviewReports,
                Permission::ViewTeamLeave,
                Permission::ViewAllLeave,
                Permission::ApproveLeave,
                Permission::ViewOwnSalary,
                Permission::ViewAllSalary,
                Permission::ManageSalary,
                Permission::ViewOwnBonus,
                Permission::ViewAllBonus,
                Permission::ManageBonus,

                // Giám đốc đổi được chính sách công ty: ca làm, giờ nhắc báo
                // cáo, cửa sổ nộp đơn. Trước đây những thứ này chỉ sửa được
                // bằng cách vào máy chủ sửa .env rồi khởi động lại.
                Permission::ManageSettings,
            ],

            self::TruongPhong => [
                Permission::ViewOwnTasks,
                Permission::ViewTeamTasks,
                Permission::CreateTask,
                Permission::UpdateTask,
                Permission::DeleteTask,
                Permission::AssignTask,
                Permission::ChangeTaskDueDate,
                Permission::ManageProjects,
                // Trưởng phòng xem và duyệt công của phòng mình, không phải
                // toàn công ty.
                Permission::ViewTeamAttendance,
                Permission::ReviewAttendance,
                // Đọc và nhận xét báo cáo ngày của phòng mình — đây là việc
                // chính của trưởng phòng mỗi sáng.
                Permission::ViewTeamReports,
                Permission::ReviewReports,
                // Duyệt đơn nghỉ của phòng mình. Đây là việc thay thế cho cách
                // đang làm hiện tại — nhân viên nhắn Zalo, trưởng phòng gật
                // đầu, không ai ghi lại.
                Permission::ViewTeamLeave,
                Permission::ApproveLeave,
                // KHÔNG có quyền lương nào ngoài của chính mình. Trưởng phòng
                // xem được lương cấp dưới là quyết định chính sách của công ty,
                // và mặc định an toàn là không.
                Permission::ViewOwnSalary,
                // Trưởng phòng CHIA được thưởng dự án — họ là người biết ai
                // đóng góp gì. Nhưng vẫn không xem được lương của ai, kể cả
                // cấp dưới: hai khoản đó khác nhau về bản chất.
                Permission::ViewOwnBonus,
                Permission::ViewAllBonus,
                Permission::ManageBonus,
            ],

            self::NhanVien => [
                Permission::ViewOwnTasks,
                Permission::CreateTask,
                Permission::UpdateTask,
                Permission::ViewOwnSalary,
                Permission::ViewOwnBonus,
            ],
        };
    }
}
