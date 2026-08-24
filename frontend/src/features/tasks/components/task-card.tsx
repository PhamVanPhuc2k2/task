import Link from "next/link";

import { Avatar } from "@/components/ui/pill";
import { cn } from "@/lib/cn";
import { formatDueDistance } from "@/lib/format";

import type { Task } from "../types/task";
import { OverdueBadge, PriorityBadge, StatusBadge } from "./task-badges";

/**
 * Thẻ tóm tắt một task, dùng chung cho "Hôm nay của tôi" và bảng Kanban.
 *
 * Cả thẻ là một liên kết: trên điện thoại, vùng bấm nhỏ hơn ngón tay là lỗi
 * dùng được, không phải chuyện thẩm mỹ.
 */
export function TaskCard({
  task,
  showStatus = false,
  className,
}: {
  task: Task;
  showStatus?: boolean;
  className?: string;
}) {
  return (
    <Link
      href={`/tasks/${task.id}`}
      className={cn(
        "border-line bg-paper-raised focus-frame lift shadow-card block rounded-2xl border p-4",
        className,
      )}
    >
      <div className="flex items-start justify-between gap-3">
        <p className="line-clamp-2 text-[0.93rem] leading-snug font-medium">
          {task.title}
        </p>

        {task.assignee && <Avatar name={task.assignee.name} size="sm" />}
      </div>

      <div className="mt-3 flex flex-wrap items-center gap-x-2 gap-y-1.5">
        {showStatus && (
          <StatusBadge status={task.status.value} label={task.status.label} />
        )}

        <PriorityBadge
          priority={task.priority.value}
          label={task.priority.label}
        />

        {task.is_overdue ? (
          <OverdueBadge />
        ) : (
          task.due_date && (
            <span className="text-ink-faint text-[0.76rem]">
              {formatDueDistance(task.due_date)}
            </span>
          )
        )}

        {task.project && (
          <span className="text-ink-faint truncate text-[0.76rem]">
            · {task.project.name}
          </span>
        )}
      </div>
    </Link>
  );
}
