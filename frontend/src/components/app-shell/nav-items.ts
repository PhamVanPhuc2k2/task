import type { ComponentType, SVGProps } from "react";

import {
  IconAttendance,
  IconBoard,
  IconBonus,
  IconEmployees,
  IconLeave,
  IconOverview,
  IconPayroll,
  IconProjects,
  IconReport,
  IconTasks,
  IconTeam,
  IconToday,
} from "@/components/ui/icon";

/**
 * Mục điều hướng chính.
 *
 * `permissions` là danh sách quyền, có **một trong số đó** là thấy mục. Ẩn mục
 * người dùng không có quyền vào là để họ không bấm rồi ăn 403 — KHÔNG phải để
 * bảo mật. Chặn thật nằm ở backend, mọi endpoint đều qua Policy.
 *
 * Từng mục tự khai quyền của mình, không có luật "quyền X mở mọi thứ". Bản
 * trước dùng `task.view.all` làm quyền vạn năng, và ngay khi thêm mục Nhân sự
 * thì giám đốc — người có `task.view.all` nhưng không quản trị nhân sự — nhìn
 * thấy mục đó rồi bấm vào và ăn 403.
 */
/**
 * Sắc của một khu vực. Khớp với `[data-tone]` ở globals.css.
 *
 * Đây là chỉ dẫn đường chứ không phải trang trí: bản trước dùng chung một màu
 * cho cả mười một mục nên Chấm công, Lương và Thưởng nhìn y hệt nhau.
 */
export type Tone =
  "all" | "home" | "task" | "report" | "time" | "pay" | "bonus" | "people";

export interface NavItem {
  href: string;
  label: string;
  /** Sắc của khu vực. Bốn mục họ công việc dùng chung `task` — chúng là một
   *  họ nghiệp vụ, tách sắc ra sẽ nói sai rằng chúng không liên quan nhau. */
  tone: Tone;
  /**
   * Icon của mục.
   *
   * Không phải trang trí: sáu mục chữ xếp dọc nhìn như một đoạn văn, mắt phải
   * đọc mới tìm được. Có hình thì sau vài ngày người dùng nhớ vị trí theo hình
   * và không đọc nữa. Chữ vẫn luôn hiện bên cạnh, icon không bao giờ đứng một
   * mình.
   */
  icon: ComponentType<SVGProps<SVGSVGElement>>;
  /** Khớp cả đường dẫn con, ví dụ `/tasks/abc` vẫn sáng mục "Công việc". */
  matchPrefix?: boolean;
  /** Rỗng hoặc không khai = ai đăng nhập cũng thấy. */
  permissions?: string[];
}

export const NAV_ITEMS: NavItem[] = [
  // Đứng đầu với người nhìn được toàn công ty — đó là màn hình họ mở mỗi sáng.
  // Người không có quyền này không thấy mục, nên với họ "Hôm nay của tôi" vẫn
  // là mục đầu tiên.
  {
    href: "/overview",
    label: "Tổng quan",
    tone: "all",
    icon: IconOverview,
    permissions: ["task.view.all"],
  },
  { href: "/", label: "Hôm nay của tôi", tone: "home", icon: IconToday },
  {
    href: "/tasks",
    label: "Công việc",
    tone: "task",
    icon: IconTasks,
    matchPrefix: true,
  },
  { href: "/board", label: "Bảng Kanban", tone: "task", icon: IconBoard },
  {
    href: "/team",
    label: "Việc của đội",
    tone: "task",
    icon: IconTeam,
    permissions: ["task.view.team", "task.view.all"],
  },
  {
    href: "/projects",
    label: "Dự án",
    tone: "task",
    icon: IconProjects,
    matchPrefix: true,
  },
  // Ai cũng thấy: mục này mở màn giờ làm của CHÍNH MÌNH. Bảng công của cả
  // phòng nằm trong cùng trang, chỉ hiện thêm với người có quyền.
  // Ai cũng thấy: mục này mở báo cáo của CHÍNH MÌNH. Báo cáo cả phòng nằm
  // trong cùng trang, chỉ hiện với người có `report.view.team`.
  { href: "/reports", label: "Báo cáo ngày", tone: "report", icon: IconReport },
  {
    href: "/attendance",
    label: "Chấm công",
    tone: "time",
    icon: IconAttendance,
  },
  // Cùng sắc với Chấm công, có chủ ý: nghỉ phép và chấm công là MỘT họ nghiệp
  // vụ — ngày nghỉ đã duyệt chính là thứ miễn chấm công cho ngày đó. Tách sắc
  // ra sẽ nói sai rằng chúng không liên quan nhau.
  { href: "/leave", label: "Nghỉ phép", tone: "time", icon: IconLeave },
  // Ai cũng thấy: mục này mở màn lương của CHÍNH MÌNH. Bảng lương cả công ty
  // nằm trong cùng trang, chỉ hiện với người có `payroll.view.all`.
  { href: "/payroll", label: "Lương", tone: "pay", icon: IconPayroll },
  // Ai cũng thấy: mục này mở phần thưởng của CHÍNH MÌNH. Quỹ theo dự án nằm
  // trong cùng trang, chỉ hiện với người có `bonus.view.all`.
  { href: "/bonus", label: "Thưởng dự án", tone: "bonus", icon: IconBonus },
  {
    href: "/employees",
    label: "Nhân sự",
    tone: "people",
    icon: IconEmployees,
    permissions: ["user.manage"],
  },
];

export function isActive(item: NavItem, pathname: string): boolean {
  if (item.href === "/") return pathname === "/";

  return item.matchPrefix === true
    ? pathname === item.href || pathname.startsWith(`${item.href}/`)
    : pathname === item.href;
}

export function visibleNavItems(permissions: string[]): NavItem[] {
  return NAV_ITEMS.filter(
    (item) =>
      item.permissions === undefined ||
      item.permissions.length === 0 ||
      item.permissions.some((quyen) => permissions.includes(quyen)),
  );
}
