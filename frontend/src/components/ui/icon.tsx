import type { SVGProps } from "react";

import { cn } from "@/lib/cn";

/**
 * Bộ icon của hệ thống — tự vẽ, không thêm thư viện.
 *
 * Dự án cần đúng vài hình cho thanh điều hướng và vài nút. Kéo cả một thư viện
 * icon về chỉ để lấy chừng đó là đổi vài KB lấy vài chục KB, trong khi ràng
 * buộc "viết mới hoàn toàn" của dự án vẫn phải giữ.
 *
 * Quy ước để cả bộ nhìn cùng một tay vẽ:
 *
 *   - Khung 24×24, nét vẽ 1,75 — đủ dày để thấy rõ ở cỡ 18px trên màn thường.
 *   - Chỉ dùng nét (`stroke`), không tô đặc: icon tô đặc nặng hơn chữ bên cạnh
 *     và kéo mắt ra khỏi nhãn.
 *   - `currentColor`, nên màu do chỗ dùng quyết định.
 *   - Bo đầu và bo góc, hợp với bo góc của thẻ và nút.
 *   - `aria-hidden` — icon ở đây luôn đi kèm chữ, đọc lên thành thừa.
 */
type IconProps = SVGProps<SVGSVGElement> & { className?: string };

function Base({ className, children, ...props }: IconProps) {
  return (
    <svg
      viewBox="0 0 24 24"
      aria-hidden="true"
      fill="none"
      stroke="currentColor"
      strokeWidth="1.75"
      strokeLinecap="round"
      strokeLinejoin="round"
      className={cn("size-[1.15rem] shrink-0", className)}
      {...props}
    >
      {children}
    </svg>
  );
}

/** Tổng quan — ba cột số liệu cao thấp khác nhau trên một đường nền. */
export function IconOverview(props: IconProps) {
  return (
    <Base {...props}>
      <path d="M3.5 20.5h17" />
      <path d="M6.5 20.5v-6M12 20.5V6.5M17.5 20.5v-9" />
    </Base>
  );
}

/** Hôm nay của tôi — tờ lịch có dấu chấm ở ngày đang xét. */
export function IconToday(props: IconProps) {
  return (
    <Base {...props}>
      <rect x="3" y="5" width="18" height="16" rx="3" />
      <path d="M3 10h18M8 3v4M16 3v4" />
      <circle cx="12" cy="15.5" r="1.6" fill="currentColor" stroke="none" />
    </Base>
  );
}

/** Công việc — danh sách có dấu tích. */
export function IconTasks(props: IconProps) {
  return (
    <Base {...props}>
      <path d="m3 6.5 2 2 3-3.5M3 14.5l2 2 3-3.5" />
      <path d="M12 7h9M12 15h9" />
    </Base>
  );
}

/** Bảng Kanban — ba cột cao thấp khác nhau, đúng như bảng thật. */
export function IconBoard(props: IconProps) {
  return (
    <Base {...props}>
      <rect x="3" y="4" width="5" height="16" rx="1.6" />
      <rect x="9.5" y="4" width="5" height="10" rx="1.6" />
      <rect x="16" y="4" width="5" height="13" rx="1.6" />
    </Base>
  );
}

/** Việc của đội — hai người, người sau lùi lại phía sau. */
export function IconTeam(props: IconProps) {
  return (
    <Base {...props}>
      <circle cx="9" cy="8" r="3.2" />
      <path d="M3 19.5a6 6 0 0 1 12 0" />
      <path d="M16.5 5.2a3.2 3.2 0 0 1 0 5.6M18 14.4a6 6 0 0 1 3 5.1" />
    </Base>
  );
}

/** Dự án — các lớp xếp chồng, mỗi lớp là một phần việc. */
export function IconProjects(props: IconProps) {
  return (
    <Base {...props}>
      <path d="m12 3 9 4.5-9 4.5-9-4.5L12 3Z" />
      <path d="m3 12.5 9 4.5 9-4.5M3 17l9 4.5 9-4.5" />
    </Base>
  );
}

/** Nhân sự — thẻ nhân viên có ảnh và dòng thông tin. */
export function IconEmployees(props: IconProps) {
  return (
    <Base {...props}>
      <rect x="2.5" y="4.5" width="19" height="15" rx="3" />
      <circle cx="8.5" cy="10.5" r="2.2" />
      <path d="M5.2 16a3.6 3.6 0 0 1 6.6 0M14.5 10h4M14.5 14h4" />
    </Base>
  );
}

/** Chấm công — mặt đồng hồ. */
export function IconAttendance(props: IconProps) {
  return (
    <Base {...props}>
      <circle cx="12" cy="12" r="8.5" />
      <path d="M12 7.5V12l3 2" />
    </Base>
  );
}

/** Lương — tờ tiền. */
export function IconPayroll(props: IconProps) {
  return (
    <Base {...props}>
      <rect x="2.5" y="6" width="19" height="12" rx="2.5" />
      <circle cx="12" cy="12" r="2.4" />
      <path d="M6 12h.01M18 12h.01" />
    </Base>
  );
}

/** Thưởng dự án — ngôi sao. */
export function IconBonus(props: IconProps) {
  return (
    <Base {...props}>
      <path d="m12 3.5 2.6 5.3 5.9.85-4.25 4.15 1 5.85L12 16.9l-5.25 2.75 1-5.85L3.5 9.65l5.9-.85L12 3.5Z" />
    </Base>
  );
}

/** Báo cáo ngày — trang giấy có dòng chữ. */
export function IconReport(props: IconProps) {
  return (
    <Base {...props}>
      <path d="M5.5 3.5h9l4.5 4.5v12.5H5.5V3.5Z" />
      <path d="M14 3.5V8h4.5M9 12h6M9 16h4" />
    </Base>
  );
}

/** Dấu cộng — motif của thương hiệu, dùng cho nút tạo mới. */
export function IconPlus(props: IconProps) {
  return (
    <Base {...props}>
      <path d="M12 5v14M5 12h14" />
    </Base>
  );
}

/** Nghỉ phép — tờ lịch có dấu tích, phân biệt với IconToday có chấm. */
export function IconLeave(props: IconProps) {
  return (
    <Base {...props}>
      <rect x="3" y="5" width="18" height="16" rx="3" />
      <path d="M3 10h18M8 3v4M16 3v4" />
      <path d="m8.5 15.5 2.2 2.2 4.3-4.6" />
    </Base>
  );
}

/** Cơ cấu tổ chức — sơ đồ cây một gốc hai nhánh. */
export function IconOrgChart(props: IconProps) {
  return (
    <Base {...props}>
      <rect x="9" y="2.5" width="6" height="5" rx="1.5" />
      <rect x="2.5" y="16.5" width="6" height="5" rx="1.5" />
      <rect x="15.5" y="16.5" width="6" height="5" rx="1.5" />
      <path d="M12 7.5v4M5.5 16.5v-2a1 1 0 0 1 1-1h11a1 1 0 0 1 1 1v2" />
    </Base>
  );
}

/** Cài đặt trang — bánh răng. */
export function IconSettings(props: IconProps) {
  return (
    <Base {...props}>
      <circle cx="12" cy="12" r="3" />
      <path d="M12 2.5v2.2M12 19.3v2.2M4.9 4.9l1.6 1.6M17.5 17.5l1.6 1.6M2.5 12h2.2M19.3 12h2.2M4.9 19.1l1.6-1.6M17.5 6.5l1.6-1.6" />
    </Base>
  );
}
