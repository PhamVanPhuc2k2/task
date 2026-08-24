/** Khớp với App\Http\Resources\ProjectResource phía backend. */

/** Giá trị khớp App\Domain\Task\Enums\ProjectStatus. */
export type ProjectStatusValue =
  "planning" | "active" | "on_hold" | "completed" | "cancelled";

/** Giá trị khớp App\Domain\Task\Enums\ProjectRole. */
export type ProjectRoleValue = "manager" | "member" | "viewer";

export interface Project {
  id: string;
  name: string;
  code: string | null;
  description: string | null;

  status: { value: ProjectStatusValue; label: string; is_open: boolean };

  /** Ngày thuần `yyyy-mm-dd`, không kèm giờ. */
  start_date: string | null;
  end_date: string | null;
  created_at: string | null;
  updated_at: string | null;

  owner?: { id: string; name: string; email: string } | null;
  department?: { id: string; name: string } | null;

  task_count?: number;
  member_count?: number;
}

export interface ProjectMember {
  id: string;
  name: string;
  email: string;
  role: ProjectRoleValue;
}

export interface ProjectStatusMeta {
  value: ProjectStatusValue;
  label: string;
  tone: string;
}

export const PROJECT_STATUSES: ProjectStatusMeta[] = [
  { value: "planning", label: "Đang lên kế hoạch", tone: "bg-ink-faint" },
  { value: "active", label: "Đang chạy", tone: "bg-accent" },
  { value: "on_hold", label: "Tạm dừng", tone: "bg-notice" },
  { value: "completed", label: "Hoàn thành", tone: "bg-emerald-500" },
  { value: "cancelled", label: "Đã huỷ", tone: "bg-danger" },
];

export const PROJECT_ROLES: { value: ProjectRoleValue; label: string }[] = [
  { value: "manager", label: "Quản lý dự án" },
  { value: "member", label: "Thành viên" },
  { value: "viewer", label: "Chỉ xem" },
];

export function projectStatusMeta(
  value: ProjectStatusValue,
): ProjectStatusMeta {
  return (
    PROJECT_STATUSES.find((s) => s.value === value) ?? {
      value,
      label: value,
      tone: "bg-ink-faint",
    }
  );
}
