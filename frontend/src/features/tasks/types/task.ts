/** Khớp với App\Http\Resources\TaskResource phía backend. */

/** Giá trị khớp App\Domain\Task\Enums\TaskStatus. */
export type TaskStatusValue =
  "todo" | "in_progress" | "review" | "done" | "on_hold" | "cancelled";

/** Giá trị khớp App\Domain\Task\Enums\TaskPriority. */
export type TaskPriorityValue = "low" | "normal" | "high" | "urgent";

export interface PersonRef {
  id: string;
  name: string;
  email: string;
}

export interface TaskLabel {
  id: string;
  name: string;
  color: string;
}

export interface Task {
  id: string;
  title: string;
  description: string | null;

  status: { value: TaskStatusValue; label: string; is_closed: boolean };
  priority: { value: TaskPriorityValue; label: string };

  /** ISO 8601 kèm offset. Đổi sang giờ Việt Nam ở tầng hiển thị. */
  due_date: string | null;
  started_at: string | null;
  completed_at: string | null;
  created_at: string | null;
  updated_at: string | null;

  estimate_hours: string | null;
  progress_percent: number;

  due_date_change_count: number;
  is_overdue: boolean;

  project?: { id: string; name: string } | null;
  assignee?: PersonRef | null;
  assigner?: PersonRef | null;
  reviewer?: PersonRef | null;
  labels?: TaskLabel[];

  subtask_count?: number;
  comment_count?: number;
}

/** Bốn nhóm của màn hình "Hôm nay của tôi". */
export interface MyTaskBuckets {
  overdue: Task[];
  today: Task[];
  this_week: Task[];
  later: Task[];
}

/** Khớp App\Http\Resources\TaskActivityResource. */
export interface TaskActivity {
  id: number;
  event: string;
  old_values: Record<string, unknown> | null;
  new_values: Record<string, unknown> | null;
  created_at: string | null;
  causer?: { id: string; name: string } | null;
}

/*
|--------------------------------------------------------------------------
| Bảng tra hiển thị
|--------------------------------------------------------------------------
|
| Nhãn tiếng Việt vẫn do backend trả về kèm mỗi task — đây là bản sao cho
| những chỗ cần dựng danh sách chọn hoặc cột Kanban khi CHƯA có task nào để
| đọc nhãn ra. Thứ tự trong mảng chính là thứ tự hiển thị.
|
*/

export interface StatusMeta {
  value: TaskStatusValue;
  label: string;
  /** Lớp Tailwind cho chấm màu. */
  tone: string;
}

export const TASK_STATUSES: StatusMeta[] = [
  { value: "todo", label: "Chưa bắt đầu", tone: "bg-ink-faint" },
  { value: "in_progress", label: "Đang làm", tone: "bg-accent" },
  { value: "review", label: "Chờ duyệt", tone: "bg-notice" },
  { value: "done", label: "Hoàn thành", tone: "bg-emerald-500" },
  { value: "on_hold", label: "Tạm dừng", tone: "bg-ink-faint" },
  { value: "cancelled", label: "Đã huỷ", tone: "bg-danger" },
];

/** Các cột của bảng Kanban. Bỏ "Đã huỷ" — việc huỷ không cần chỗ trên bảng. */
export const BOARD_COLUMNS: TaskStatusValue[] = [
  "todo",
  "in_progress",
  "review",
  "on_hold",
  "done",
];

export interface PriorityMeta {
  value: TaskPriorityValue;
  label: string;
  tone: string;
}

export const TASK_PRIORITIES: PriorityMeta[] = [
  { value: "low", label: "Thấp", tone: "text-ink-faint" },
  { value: "normal", label: "Bình thường", tone: "text-ink-soft" },
  { value: "high", label: "Cao", tone: "text-notice" },
  { value: "urgent", label: "Khẩn cấp", tone: "text-danger" },
];

/**
 * Luồng chuyển trạng thái hợp lệ.
 *
 * Bản sao của App\Domain\Task\Enums\TaskStatus::allowedTransitions(). Ở đây
 * chỉ để KHÔNG hiện những lựa chọn chắc chắn bị từ chối — luật thật nằm ở
 * backend và vẫn chặn dù frontend có gửi bừa.
 */
export const ALLOWED_TRANSITIONS: Record<TaskStatusValue, TaskStatusValue[]> = {
  todo: ["in_progress", "on_hold", "cancelled"],
  in_progress: ["review", "on_hold", "cancelled"],
  review: ["done", "in_progress"],
  on_hold: ["todo", "in_progress", "cancelled"],
  done: [],
  cancelled: [],
};

/**
 * Bản mô tả hiển thị của một trạng thái.
 *
 * Gặp giá trị lạ — backend thêm trạng thái mới mà frontend chưa cập nhật — thì
 * trả về chính giá trị thô làm nhãn, không ném lỗi và không im lặng đổi sang
 * một trạng thái khác. Hiện "wontfix" khó đọc còn hơn hiện nhầm "Chưa bắt đầu".
 */
export function statusMeta(value: TaskStatusValue): StatusMeta {
  return (
    TASK_STATUSES.find((s) => s.value === value) ?? {
      value,
      label: value,
      tone: "bg-ink-faint",
    }
  );
}

export function priorityMeta(value: TaskPriorityValue): PriorityMeta {
  return (
    TASK_PRIORITIES.find((p) => p.value === value) ?? {
      value,
      label: value,
      tone: "text-ink-soft",
    }
  );
}
