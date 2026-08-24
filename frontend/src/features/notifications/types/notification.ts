/** Khớp với App\Http\Resources\NotificationResource phía backend. */

/** Khớp App\Domain\Identity\Enums\NotificationType. */
export type NotificationKind =
  | "task.assigned"
  | "task.due_soon"
  | "task.overdue"
  | "task.comment_added"
  | "task.mentioned";

export interface AppNotification {
  id: string;
  kind: NotificationKind | null;
  title: string;
  message: string;
  /** Đường dẫn tương đối trong ứng dụng, ví dụ `/tasks/{uuid}`. */
  url: string | null;
  task_id: string | null;
  actor_name: string | null;
  read_at: string | null;
  created_at: string | null;
}

export interface NotificationSetting {
  type: NotificationKind;
  label: string;
  description: string;
  in_app: boolean;
  email: boolean;
}

/**
 * Chấm màu theo mức độ gấp.
 *
 * "Quá hạn" là màu cảnh báo, còn lại trung tính. Tô đỏ mọi thứ thì không còn
 * gì nổi bật và người đọc thôi phân biệt.
 */
export function notificationTone(kind: NotificationKind | null): string {
  switch (kind) {
    case "task.overdue":
      return "bg-danger";
    case "task.due_soon":
      return "bg-notice";
    case "task.mentioned":
      return "bg-accent";
    default:
      return "bg-ink-faint";
  }
}
