import { Pill } from "@/components/ui/pill";

import {
  priorityMeta,
  statusMeta,
  type TaskPriorityValue,
  type TaskStatusValue,
} from "../types/task";

/**
 * Nhãn mang nghiệp vụ của miền Task.
 *
 * Nằm ở `features/tasks` chứ không ở `components/ui`: bộ UI dùng chung không
 * được biết task có những trạng thái nào — nếu biết thì mỗi lần thêm trạng
 * thái lại phải sửa vào tầng dùng chung của cả ứng dụng.
 *
 * `label` nhận từ backend khi có, vì backend là nơi giữ nhãn chuẩn; bảng tra ở
 * frontend chỉ để dựng danh sách chọn khi chưa có task nào để đọc nhãn ra.
 */
export function StatusBadge({
  status,
  label,
  className,
}: {
  status: TaskStatusValue;
  label?: string;
  className?: string;
}) {
  const meta = statusMeta(status);

  return (
    <Pill dotClass={meta.tone} className={className}>
      {label ?? meta.label}
    </Pill>
  );
}

/**
 * Nền của nhãn ưu tiên.
 *
 * "Khẩn cấp" và "Cao" được nền màu vì đó là thứ phải đập vào mắt khi lướt danh
 * sách. "Thấp" thì ngược lại — nó là thông tin phụ, tô màu lên chỉ tổ tranh chỗ
 * với những việc thật sự gấp.
 */
const PRIORITY_SURFACE: Partial<Record<TaskPriorityValue, string>> = {
  urgent: "border-danger-line bg-danger-surface text-danger",
  high: "border-notice-line bg-notice-surface text-notice",
  low: "border-line bg-paper-raised text-ink-faint",
};

/** Mức ưu tiên. Không hiện khi là "bình thường" — mặc định thì không cần nói. */
export function PriorityBadge({
  priority,
  label,
}: {
  priority: TaskPriorityValue;
  label?: string;
}) {
  if (priority === "normal") return null;

  const meta = priorityMeta(priority);

  return <Pill tone={PRIORITY_SURFACE[priority]}>{label ?? meta.label}</Pill>;
}

/** Chỉ dùng cho task đã quá hạn mà chưa đóng. */
export function OverdueBadge() {
  return (
    <Pill tone="border-danger-line bg-danger-surface text-danger">Trễ hạn</Pill>
  );
}
