"use client";

import { Avatar } from "@/components/ui/pill";
import { ErrorState, ListSkeleton } from "@/components/ui/states";
import { formatDateTime, formatTimeAgo } from "@/lib/format";

import { useTaskActivities } from "../api/tasks-api";
import {
  priorityMeta,
  statusMeta,
  type TaskActivity,
  type TaskPriorityValue,
  type TaskStatusValue,
} from "../types/task";

/**
 * Dòng thời gian hoạt động của task.
 *
 * Nhật ký lưu tên cột và giá trị thô (`status`, `in_progress`). Dịch sang tiếng
 * Việt ở đây chứ không ở backend: đây là chuyện trình bày, và bảng nhật ký phải
 * giữ nguyên giá trị gốc để còn tra cứu về sau.
 */

const TEN_TRUONG: Record<string, string> = {
  title: "Tiêu đề",
  description: "Mô tả",
  status: "Trạng thái",
  priority: "Mức ưu tiên",
  due_date: "Hạn hoàn thành",
  assignee_id: "Người thực hiện",
  assigner_id: "Người giao việc",
  reviewer_id: "Người duyệt",
  project_id: "Dự án",
  progress_percent: "Tiến độ",
  estimate_hours: "Số giờ ước lượng",
  started_at: "Bắt đầu lúc",
  completed_at: "Hoàn thành lúc",
  due_date_change_count: "Số lần dời hạn",
  deleted_at: "Xoá lúc",
};

const TEN_SU_KIEN: Record<string, string> = {
  created: "đã tạo công việc",
  updated: "đã cập nhật",
  deleted: "đã xoá công việc",
};

export function ActivityTimeline({ taskId }: { taskId: string }) {
  const { data, isPending, isError, error, refetch } =
    useTaskActivities(taskId);

  if (isPending) return <ListSkeleton rows={3} />;
  if (isError)
    return <ErrorState error={error} onRetry={() => void refetch()} />;
  if (data.length === 0) {
    return (
      <p className="text-ink-faint py-4 text-[0.86rem]">
        Chưa có thay đổi nào.
      </p>
    );
  }

  return (
    <ol className="space-y-4">
      {data.map((moc) => (
        <li key={moc.id} className="flex gap-3">
          <Avatar name={moc.causer?.name ?? "Hệ thống"} size="sm" />

          <div className="min-w-0 flex-1">
            <p className="text-[0.88rem] leading-snug">
              <span className="font-medium">
                {moc.causer?.name ?? "Hệ thống"}
              </span>{" "}
              <span className="text-ink-soft">
                {TEN_SU_KIEN[moc.event] ?? moc.event}
              </span>{" "}
              <span
                className="text-ink-faint text-[0.78rem]"
                title={formatDateTime(moc.created_at)}
              >
                · {formatTimeAgo(moc.created_at)}
              </span>
            </p>

            <ThayDoi activity={moc} />
          </div>
        </li>
      ))}
    </ol>
  );
}

function ThayDoi({ activity }: { activity: TaskActivity }) {
  if (activity.event === "created" || activity.new_values === null) return null;

  const truong = Object.keys(activity.new_values);
  if (truong.length === 0) return null;

  return (
    <ul className="mt-1.5 space-y-1">
      {truong.map((ten) => (
        <li key={ten} className="text-ink-soft text-[0.82rem]">
          <span className="text-ink-faint">{TEN_TRUONG[ten] ?? ten}:</span>{" "}
          <span className="line-through opacity-60">
            {hienThi(ten, activity.old_values?.[ten])}
          </span>{" "}
          →{" "}
          <span className="text-ink">
            {hienThi(ten, activity.new_values?.[ten])}
          </span>
        </li>
      ))}
    </ul>
  );
}

/** Đưa giá trị thô trong nhật ký về dạng đọc được. */
function hienThi(truong: string, giaTri: unknown): string {
  if (giaTri === null || giaTri === undefined || giaTri === "") return "trống";

  if (truong === "status") {
    return statusMeta(String(giaTri) as TaskStatusValue).label;
  }

  if (truong === "priority") {
    return priorityMeta(String(giaTri) as TaskPriorityValue).label;
  }

  if (
    truong.endsWith("_at") ||
    truong === "due_date" ||
    truong === "deleted_at"
  ) {
    return formatDateTime(String(giaTri));
  }

  // Khoá ngoại lưu id nội bộ — nhật ký không kèm tên người, và đi tra từng id
  // là thêm một loạt request cho một dòng lịch sử.
  if (truong.endsWith("_id")) return `#${String(giaTri)}`;

  const chuoi = String(giaTri);

  return chuoi.length > 80 ? `${chuoi.slice(0, 80)}…` : chuoi;
}
