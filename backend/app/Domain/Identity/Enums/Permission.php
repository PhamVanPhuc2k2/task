<?php

declare(strict_types=1);

namespace App\Domain\Identity\Enums;

/**
 * Quyền chi tiết.
 *
 * Dùng enum thay vì chuỗi rời rạc để gõ sai tên quyền là lỗi lúc biên dịch chứ
 * không phải lỗi âm thầm cho qua ở production — `$user->can('task.craete')`
 * luôn trả về false mà không báo gì.
 *
 * Quyền chỉ trả lời "được làm loại việc này không". Việc "được đụng vào đúng
 * bản ghi nào" do Policy quyết định — xem README, bảng pattern.
 */
enum Permission: string
{
    // ── Công việc ────────────────────────────────────
    case ViewOwnTasks = 'task.view.own';
    /** Task của phòng mình và mọi phòng trực thuộc bên dưới. */
    case ViewTeamTasks = 'task.view.team';
    case ViewAllTasks = 'task.view.all';
    case CreateTask = 'task.create';
    case UpdateTask = 'task.update';
    case DeleteTask = 'task.delete';
    case AssignTask = 'task.assign';
    /** Dời hạn — quyền của người giao việc, không phải của người làm. */
    case ChangeTaskDueDate = 'task.due_date.change';

    // ── Dự án ────────────────────────────────────────
    case ManageProjects = 'project.manage';

    // ── Chấm công ────────────────────────────────────
    /** Xem giờ làm của phòng mình và mọi phòng trực thuộc. */
    case ViewTeamAttendance = 'attendance.view.team';
    case ViewAllAttendance = 'attendance.view.all';
    /**
     * Ghi nhận hoặc bỏ qua một ngày công.
     *
     * Tách khỏi quyền xem: có người cần nhìn số (kế toán, giám đốc) mà không
     * nên là người ra quyết định bỏ qua cho nhân viên phòng khác.
     */
    case ReviewAttendance = 'attendance.review';

    /*
     * Chốt sổ và mở khoá là HAI quyền, không phải một.
     *
     * Chốt là việc hành chính cuối kỳ. Mở khoá là việc đổi số liệu đã dùng để
     * trả lương — mức trách nhiệm khác hẳn, nên công ty chốt: giám đốc và admin
     * chốt được, nhưng chỉ giám đốc mở khoá được.
     */
    case ClosePeriod = 'attendance.period.close';
    case ReopenPeriod = 'attendance.period.reopen';

    // ── Nghỉ phép ────────────────────────────────────
    /**
     * Nộp đơn và xem đơn của CHÍNH MÌNH không cần quyền nào — cùng khuôn với
     * chấm công, báo cáo và lương. Ba quyền dưới đây chỉ dành cho việc nhìn và
     * quyết định thay người khác.
     *
     * `ApproveLeave` tách khỏi `ReviewAttendance`: duyệt đơn nghỉ là quyết định
     * nhân sự (người này có được nghỉ không), còn duyệt ngày công là quyết định
     * về một con số đã đo. Ở công ty nhỏ thường cùng một người, nhưng gộp
     * chung thì sau này không tách ra được nữa.
     */
    case ViewTeamLeave = 'leave.view.team';
    case ViewAllLeave = 'leave.view.all';
    case ApproveLeave = 'leave.approve';

    // ── Báo cáo ngày ─────────────────────────────────
    /**
     * `ViewReports` cũ (`report.view`) giữ nguyên cho báo cáo tổng hợp của lãnh
     * đạo ở đợt 5. Ba quyền dưới đây dành riêng cho báo cáo tiến độ hằng ngày —
     * hai thứ khác nhau: một cái là số liệu công ty, một cái là nội dung nhân
     * viên tự viết về ngày làm việc của họ.
     */
    case ViewTeamReports = 'report.view.team';
    case ReviewReports = 'report.review';

    // ── Lương ────────────────────────────────────────
    /**
     * Ba quyền RIÊNG, cố ý không dùng chung `user.manage`.
     *
     * Quản trị nhân sự và quản trị lương là hai vai khác nhau: người thêm tài
     * khoản và xếp phòng ban không đương nhiên được xem người khác lĩnh bao
     * nhiêu. Gộp chung thì không tách được nữa.
     */
    case ViewOwnSalary = 'payroll.view.own';
    case ViewAllSalary = 'payroll.view.all';
    case ManageSalary = 'payroll.manage';

    // ── Thưởng dự án ─────────────────────────────────
    /**
     * Tách khỏi quyền lương: thưởng dự án do người phụ trách dự án chia, mà
     * người đó thường không phải người quyết định lương. Gộp chung thì muốn
     * cho trưởng phòng chia thưởng là phải mở luôn quyền xem lương cả công ty.
     */
    case ViewOwnBonus = 'bonus.view.own';
    case ViewAllBonus = 'bonus.view.all';
    case ManageBonus = 'bonus.manage';

    // ── Quản trị ─────────────────────────────────────
    case ManageUsers = 'user.manage';
    case ManageRoles = 'role.manage';
    case ManageSettings = 'setting.manage';
    case ViewReports = 'report.view';

    /**
     * Sửa cây phòng ban.
     *
     * Quyền RIÊNG, cố ý không gộp vào `user.manage`, và lý do không phải là sự
     * ngăn nắp: **cây phòng ban là thứ quyết định ai nhìn thấy dữ liệu của ai.**
     *
     * `Department::subtreeIds()` đỡ 13 chỗ — chấm công, nghỉ phép, đi muộn, báo
     * cáo ngày, task của đội, danh sách người được giao việc. Chuyển một phòng
     * ban sang nhánh khác là đổi phạm vi nhìn của mọi trưởng phòng nằm trên
     * đường đi, **cùng lúc, không có màn hình nào báo**.
     *
     * Thêm một nhân viên thì ảnh hưởng gói gọn ở một người. Đổi cây phòng ban
     * thì ảnh hưởng cả hệ thống. Hai việc đó không nên đi chung một quyền.
     */
    case ManageOrganization = 'organization.manage';

    public function label(): string
    {
        return match ($this) {
            self::ViewOwnTasks => 'Xem task của mình',
            self::ViewTeamTasks => 'Xem task của phòng ban mình quản lý',
            self::ViewAllTasks => 'Xem task toàn công ty',
            self::CreateTask => 'Tạo task',
            self::UpdateTask => 'Sửa task',
            self::DeleteTask => 'Xoá task',
            self::AssignTask => 'Giao task cho người khác',
            self::ChangeTaskDueDate => 'Dời hạn task',
            self::ManageProjects => 'Quản lý dự án',
            self::ViewTeamAttendance => 'Xem giờ làm của phòng ban mình quản lý',
            self::ViewAllAttendance => 'Xem giờ làm toàn công ty',
            self::ReviewAttendance => 'Ghi nhận và bỏ qua ngày công',
            self::ClosePeriod => 'Chốt sổ kỳ công',
            self::ReopenPeriod => 'Mở khoá kỳ công đã chốt',
            self::ViewTeamLeave => 'Xem đơn nghỉ của phòng ban mình quản lý',
            self::ViewAllLeave => 'Xem đơn nghỉ toàn công ty',
            self::ApproveLeave => 'Duyệt đơn nghỉ',
            self::ViewTeamReports => 'Xem báo cáo ngày của phòng ban mình quản lý',
            self::ReviewReports => 'Duyệt và hỏi lại báo cáo ngày',
            self::ViewOwnSalary => 'Xem mức lương của chính mình',
            self::ViewAllSalary => 'Xem mức lương toàn công ty',
            self::ManageSalary => 'Đặt và điều chỉnh mức lương',
            self::ViewOwnBonus => 'Xem thưởng dự án của chính mình',
            self::ViewAllBonus => 'Xem quỹ thưởng và phần chia của mọi người',
            self::ManageBonus => 'Lập quỹ thưởng và chia thưởng dự án',
            self::ManageUsers => 'Quản lý người dùng',
            self::ManageRoles => 'Quản lý vai trò và quyền',
            self::ManageSettings => 'Đổi cài đặt trang và chính sách công ty',
            self::ViewReports => 'Xem báo cáo',
            self::ManageOrganization => 'Sửa cơ cấu phòng ban',
        };
    }
}
