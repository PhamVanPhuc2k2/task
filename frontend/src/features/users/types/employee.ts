import type { AuthUser } from "@/features/auth/types/user";

/**
 * Nhân viên trong màn hình quản trị nhân sự.
 *
 * Dùng lại `AuthUser` vì backend trả về cùng một `UserResource` cho cả
 * `/auth/me` lẫn `/users` — khai một kiểu thứ hai chỉ tạo ra hai chỗ phải sửa
 * mỗi lần Resource đổi.
 */
export type Employee = AuthUser;

/** Khớp App\Domain\Identity\Enums\Role. */
export type RoleValue = "admin" | "giam_doc" | "truong_phong" | "nhan_vien";

export const ROLES: { value: RoleValue; label: string; description: string }[] =
  [
    {
      value: "nhan_vien",
      label: "Nhân viên",
      description: "Xem và làm việc của mình",
    },
    {
      value: "truong_phong",
      label: "Trưởng phòng",
      description: "Thêm: xem và giao việc cả phòng, quản lý dự án",
    },
    {
      value: "giam_doc",
      label: "Giám đốc",
      description: "Thêm: xem việc toàn công ty, xem báo cáo",
    },
    {
      value: "admin",
      label: "Quản trị hệ thống",
      description: "Toàn quyền, gồm cả quản trị người dùng và phân quyền",
    },
  ];

export function roleLabel(value: string): string {
  return ROLES.find((r) => r.value === value)?.label ?? value;
}

export interface Department {
  id: string;
  name: string;
  code: string | null;
  description: string | null;
  is_active: boolean;
  parent_id: string | null;
  parent_name: string | null;
  /** Số phòng ban trực thuộc trực tiếp. Trang cơ cấu tổ chức dùng để chặn xoá. */
  child_count: number;
  /** Số nhân sự đang thuộc phòng ban này, tính cả người đã nghỉ việc. */
  user_count: number;
}

export interface Position {
  id: string;
  name: string;
  code: string | null;
  level: number;
}

export interface EmployeeFilters {
  search?: string;
  department_id?: string;
  role?: RoleValue | "";
  include_inactive?: boolean;
  page?: number;
}

/**
 * Kết quả tạo nhân viên.
 *
 * `temporary_password` chỉ có ở phản hồi tạo mới và **không lấy lại được** —
 * database chỉ lưu bản băm. Mất là phải đặt lại mật khẩu.
 */
export interface CreateEmployeeResult {
  data: Employee;
  meta: { temporary_password: string };
}

/**
 * Kết quả sửa hồ sơ.
 *
 * `warnings` là hệ quả **đúng nhưng dễ bất ngờ** của thao tác vừa làm, không
 * phải lỗi — lưu đã thành công. Ví dụ: chuyển một trưởng phòng sang phòng khác
 * thì từ giây đó họ không còn nhìn thấy công việc của đội cũ.
 */
export interface UpdateEmployeeResult {
  data: Employee;
  meta: { warnings: string[] };
}
