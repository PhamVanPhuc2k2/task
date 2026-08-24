import Link from "next/link";

import { Avatar } from "@/components/ui/pill";
import { formatDate, formatDueDistance } from "@/lib/format";

import type { Task } from "../types/task";
import { OverdueBadge, PriorityBadge, StatusBadge } from "./task-badges";
import { TaskCard } from "./task-card";

/**
 * Danh sách công việc.
 *
 * Hai cách trình bày cùng một dữ liệu: bảng từ `md` trở lên, thẻ xếp dọc trên
 * điện thoại. Bảng ép ngang trên màn hình 375px thì chữ bé tới mức không đọc
 * được — mà đó lại là màn hình phần lớn nhân viên dùng.
 */
export function TaskTable({ tasks }: { tasks: Task[] }) {
  return (
    <>
      <ul className="space-y-2.5 md:hidden">
        {tasks.map((task) => (
          <li key={task.id}>
            <TaskCard task={task} showStatus />
          </li>
        ))}
      </ul>

      <div className="tone-card hidden overflow-hidden rounded-2xl md:block">
        <div className="overflow-x-auto">
          <table className="w-full border-collapse text-[0.88rem]">
            <caption className="sr-only">Danh sách công việc</caption>
            {/* Đầu bảng nền lõm và dính khi cuộn: danh sách dài thì cuộn được
                vài chục dòng là quên mất cột nào là cột nào. */}
            <thead className="bg-paper-sunken border-line sticky top-0 border-b">
              <tr className="text-left">
                <Th className="w-[40%]">Công việc</Th>
                <Th>Trạng thái</Th>
                <Th>Người làm</Th>
                <Th>Hạn</Th>
              </tr>
            </thead>

            <tbody>
              {tasks.map((task) => (
                <tr
                  key={task.id}
                  className="border-line hover:bg-paper-sunken border-b transition-colors last:border-0"
                >
                  <td className="px-4 py-3">
                    <Link
                      href={`/tasks/${task.id}`}
                      className="focus-frame block rounded font-medium hover:underline"
                    >
                      {task.title}
                    </Link>

                    <div className="mt-1 flex flex-wrap items-center gap-x-2.5 gap-y-1">
                      <PriorityBadge
                        priority={task.priority.value}
                        label={task.priority.label}
                      />
                      {task.project && (
                        <span className="text-ink-faint text-[0.78rem]">
                          {task.project.name}
                        </span>
                      )}
                    </div>
                  </td>

                  <td className="px-4 py-3">
                    <StatusBadge
                      status={task.status.value}
                      label={task.status.label}
                    />
                  </td>

                  <td className="px-4 py-3">
                    {task.assignee ? (
                      <span className="flex items-center gap-2">
                        <Avatar name={task.assignee.name} size="sm" />
                        <span className="truncate">{task.assignee.name}</span>
                      </span>
                    ) : (
                      <span className="text-ink-faint">Chưa giao</span>
                    )}
                  </td>

                  <td className="px-4 py-3 whitespace-nowrap">
                    {task.is_overdue ? (
                      <OverdueBadge />
                    ) : task.due_date ? (
                      <span title={formatDate(task.due_date)}>
                        {formatDueDistance(task.due_date)}
                      </span>
                    ) : (
                      <span className="text-ink-faint">—</span>
                    )}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>
    </>
  );
}

function Th({
  children,
  className,
}: {
  children: React.ReactNode;
  className?: string;
}) {
  return (
    <th
      scope="col"
      className={`text-ink-faint px-4 py-3 text-[0.76rem] font-medium ${className ?? ""}`}
    >
      {children}
    </th>
  );
}
