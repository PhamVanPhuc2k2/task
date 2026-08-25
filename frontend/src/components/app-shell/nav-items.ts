import type { ComponentType, SVGProps } from "react";

import {
  IconAttendance,
  IconBoard,
  IconBonus,
  IconEmployees,
  IconLeave,
  IconOrgChart,
  IconOverview,
  IconPayroll,
  IconProjects,
  IconReport,
  IconSettings,
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
 * Nhóm của một mục: mục này phục vụ VAI nào.
 *
 * ## Vì sao cần nhóm
 *
 * Danh sách phẳng mười hai mục không nói được mục nào là việc của nhân viên,
 * mục nào là công cụ quản lý. Giám đốc mở thanh bên ra thấy "Chấm công" nằm
 * ngay cạnh "Nhân sự" và không có gì gợi ý rằng một cái là bảng công của chính
 * mình còn cái kia là quản trị cả công ty.
 *
 * Nhóm ở đây chia theo **ai được phục vụ**, không phải theo chủ đề nghiệp vụ:
 *
 *   - `toi`     — mọi người đều thấy, và mở ra là dữ liệu của chính mình
 *   - `quanly`  — nhìn người khác: cả phòng, cả công ty
 *   - `quantri` — sửa chính hệ thống: nhân sự, cơ cấu, cài đặt
 *
 * Nhân viên thường chỉ thấy nhóm đầu, nên với họ thanh bên **không có tiêu đề
 * nhóm nào** — `visibleNavGroups` bỏ nhóm rỗng, và bỏ luôn tiêu đề khi chỉ còn
 * đúng một nhóm. Một cái nhãn "Của tôi" trên danh sách mà mọi mục đều là của
 * mình thì không nói thêm được gì.
 */
export type NavGroup = "toi" | "quanly" | "quantri";

export const NAV_GROUP_LABELS: Record<NavGroup, string> = {
  toi: "Của tôi",
  quanly: "Quản lý",
  quantri: "Quản trị",
};

/** Thứ tự hiển thị. Việc hằng ngày lên trên, quản trị xuống dưới cùng. */
const THU_TU_NHOM: NavGroup[] = ["toi", "quanly", "quantri"];

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
  group: NavGroup;
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
  /**
   * Quyền mở thêm phần "cả đội" NGAY TRONG trang này.
   *
   * Năm trang có hai tầng: phần của chính mình ở trên, phần cả phòng ở dưới và
   * chỉ hiện với người có quyền. Nghĩa là cùng một nhãn trên thanh bên dẫn tới
   * hai thứ khác nhau tuỳ người đang đăng nhập — và đó chính là chỗ khó hình
   * dung nhất của thanh bên cũ.
   *
   * Với người có quyền, mục sẽ mang thêm một viên nhãn "cả đội". Người không có
   * quyền không thấy gì thêm, vì với họ trang đó thật sự chỉ có một tầng.
   *
   * Không tách thành hai mục riêng: chúng là MỘT trang, và một mục điều hướng
   * trỏ tới giữa trang bằng neo thì trạng thái "đang mở" sẽ sai ở cả hai mục.
   */
  teamPermissions?: string[];
}

export const NAV_ITEMS: NavItem[] = [
  // ── Của tôi ────────────────────────────────────────
  {
    href: "/",
    label: "Hôm nay của tôi",
    group: "toi",
    tone: "home",
    icon: IconToday,
  },
  {
    href: "/tasks",
    label: "Công việc",
    group: "toi",
    tone: "task",
    icon: IconTasks,
    matchPrefix: true,
  },
  {
    href: "/board",
    label: "Bảng Kanban",
    group: "toi",
    tone: "task",
    icon: IconBoard,
  },
  {
    href: "/projects",
    label: "Dự án",
    group: "toi",
    tone: "task",
    icon: IconProjects,
    matchPrefix: true,
  },
  {
    href: "/reports",
    label: "Báo cáo ngày",
    group: "toi",
    tone: "report",
    icon: IconReport,
    teamPermissions: ["report.view.team"],
  },
  {
    href: "/attendance",
    label: "Chấm công",
    group: "toi",
    tone: "time",
    icon: IconAttendance,
    teamPermissions: ["attendance.view.team", "attendance.view.all"],
  },
  // Cùng sắc với Chấm công, có chủ ý: nghỉ phép và chấm công là MỘT họ nghiệp
  // vụ — ngày nghỉ đã duyệt chính là thứ miễn chấm công cho ngày đó. Tách sắc
  // ra sẽ nói sai rằng chúng không liên quan nhau.
  {
    href: "/leave",
    label: "Nghỉ phép",
    group: "toi",
    tone: "time",
    icon: IconLeave,
    teamPermissions: ["leave.view.team", "leave.view.all"],
  },
  {
    href: "/payroll",
    label: "Lương",
    group: "toi",
    tone: "pay",
    icon: IconPayroll,
    teamPermissions: ["payroll.view.all"],
  },
  {
    href: "/bonus",
    label: "Thưởng dự án",
    group: "toi",
    tone: "bonus",
    icon: IconBonus,
    teamPermissions: ["bonus.view.all"],
  },

  // ── Quản lý ────────────────────────────────────────
  // Màn hình mở mỗi sáng của người nhìn được toàn công ty.
  {
    href: "/overview",
    label: "Tổng quan công ty",
    group: "quanly",
    tone: "all",
    icon: IconOverview,
    permissions: ["task.view.all"],
  },
  {
    href: "/team",
    label: "Việc của đội",
    group: "quanly",
    tone: "task",
    icon: IconTeam,
    permissions: ["task.view.team", "task.view.all"],
  },

  // ── Quản trị ───────────────────────────────────────
  // Ba mục này trước đây nằm rải rác: Nhân sự trên thanh bên, còn Cơ cấu tổ
  // chức và Cài đặt trang giấu trong menu tài khoản. Gom về một chỗ có nhãn
  // để câu "phần quản trị nằm ở đâu" có đúng một câu trả lời.
  {
    href: "/employees",
    label: "Nhân sự",
    group: "quantri",
    tone: "people",
    icon: IconEmployees,
    permissions: ["user.manage"],
  },
  {
    href: "/settings/departments",
    label: "Cơ cấu tổ chức",
    group: "quantri",
    tone: "people",
    icon: IconOrgChart,
    permissions: ["organization.manage"],
  },
  {
    href: "/settings/site",
    label: "Cài đặt trang",
    group: "quantri",
    tone: "all",
    icon: IconSettings,
    permissions: ["setting.manage"],
  },
];

export function isActive(item: NavItem, pathname: string): boolean {
  if (item.href === "/") return pathname === "/";

  return item.matchPrefix === true
    ? pathname === item.href || pathname.startsWith(`${item.href}/`)
    : pathname === item.href;
}

function coQuyen(canPhai: string[] | undefined, dangCo: string[]): boolean {
  return (
    canPhai === undefined ||
    canPhai.length === 0 ||
    canPhai.some((quyen) => dangCo.includes(quyen))
  );
}

export function visibleNavItems(permissions: string[]): NavItem[] {
  return NAV_ITEMS.filter((item) => coQuyen(item.permissions, permissions));
}

/** Mục này có mở thêm phần "cả đội" cho người đang đăng nhập không. */
export function coPhanDoi(item: NavItem, permissions: string[]): boolean {
  return (
    item.teamPermissions !== undefined &&
    item.teamPermissions.some((quyen) => permissions.includes(quyen))
  );
}

export interface NavSection {
  group: NavGroup;
  /** `null` = đừng vẽ tiêu đề. Xem `visibleNavGroups`. */
  label: string | null;
  items: NavItem[];
}

/**
 * Các mục đã gom nhóm, bỏ nhóm rỗng.
 *
 * Chỉ còn đúng một nhóm thì bỏ luôn tiêu đề: với nhân viên thường, mọi mục đều
 * là của họ, nên một cái nhãn "Của tôi" phía trên chỉ thêm một dòng chữ mà
 * không phân biệt được gì với dòng nào cả.
 */
export function visibleNavGroups(permissions: string[]): NavSection[] {
  const thay = visibleNavItems(permissions);

  const nhom = THU_TU_NHOM.map((group) => ({
    group,
    label: NAV_GROUP_LABELS[group],
    items: thay.filter((item) => item.group === group),
  })).filter((phan) => phan.items.length > 0);

  if (nhom.length <= 1) {
    return nhom.map((phan) => ({ ...phan, label: null }));
  }

  return nhom;
}
