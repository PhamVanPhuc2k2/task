"use client";

import Link from "next/link";

import { buttonClass } from "@/components/ui/button";
import { IconPlus } from "@/components/ui/icon";
import { EmptyState, ErrorState, ListSkeleton } from "@/components/ui/states";
import { useCurrentUser } from "@/features/auth/api/auth-api";
import { useMyTasks } from "@/features/tasks/api/tasks-api";
import { TaskCard } from "@/features/tasks/components/task-card";
import type { MyTaskBuckets, Task } from "@/features/tasks/types/task";
import { cn } from "@/lib/cn";
import { formatLongDate, shortName } from "@/lib/format";

/**
 * "Hôm nay của tôi" — màn hình mặc định mỗi sáng.
 *
 * Gom theo hạn chứ không theo trạng thái: điều nhân viên cần biết ngay khi mở
 * máy là *việc nào đã trễ* và *việc nào phải xong hôm nay*, không phải có bao
 * nhiêu việc đang ở trạng thái nào. Backend đã gom sẵn ở `GET /tasks/my`.
 */

const NHOM: {
  key: keyof MyTaskBuckets;
  tieuDe: string;
  moTa: string;
  nhanManh?: boolean;
}[] = [
  {
    key: "overdue",
    tieuDe: "Quá hạn",
    moTa: "Đã qua hạn mà chưa đóng",
    nhanManh: true,
  },
  { key: "today", tieuDe: "Hôm nay", moTa: "Phải xong trong hôm nay" },
  { key: "this_week", tieuDe: "Tuần này", moTa: "Hạn trước hết chủ nhật" },
  {
    key: "later",
    tieuDe: "Xa hơn",
    moTa: "Hạn sau tuần này, hoặc chưa có hạn",
  },
];

export default function MyDayPage() {
  const { data: user } = useCurrentUser();
  const { data, isPending, isError, error, refetch } = useMyTasks();

  const tong = data ? Object.values(data).flat().length : 0;
  const xemToanCongTy = user?.permissions.includes("task.view.all") === true;

  return (
    <div data-tone="home" className="enter space-y-8">
      <header className="flex flex-wrap items-end justify-between gap-4">
        <div>
          <p className="text-ink-faint mb-1 text-[0.82rem]">
            {formatLongDate()}
          </p>
          <span
            aria-hidden="true"
            className="bg-tone mb-3 block h-[3px] w-9 rounded-full"
          />
          <h1 className="text-[1.9rem] leading-tight font-semibold tracking-[-0.035em] sm:text-[2.2rem]">
            {user ? `Chào ${shortName(user.name)}` : "Hôm nay của tôi"}
          </h1>
          {data && (
            <p className="text-ink-soft mt-2 text-[0.92rem]">
              {tong === 0
                ? "Bạn không còn việc nào đang mở."
                : `Bạn đang có ${tong} việc chưa đóng.`}
            </p>
          )}
        </div>

        <Link
          href="/tasks/new"
          className={buttonClass("primary", "md", "shrink-0")}
        >
          <IconPlus className="size-4" />
          Tạo việc mới
        </Link>
      </header>

      {isPending && <ListSkeleton rows={4} />}

      {isError && <ErrorState error={error} onRetry={() => void refetch()} />}

      {/* Quản trị viên và giám đốc gần như không bao giờ được giao việc, nên
          với họ màn này luôn rỗng. Chỉ ra lối sang Tổng quan thay vì để họ
          nhìn một câu "không có việc nào" mỗi sáng. */}
      {data && tong === 0 && xemToanCongTy && (
        <EmptyState
          title="Bạn không có việc nào được giao"
          description="Việc thường không giao trực tiếp cho vai trò của bạn. Màn Tổng quan cho thấy tình hình công việc và dự án của cả công ty."
          action={
            <Link href="/overview" className={buttonClass("primary", "sm")}>
              Xem tổng quan công ty
            </Link>
          }
        />
      )}

      {data && tong === 0 && !xemToanCongTy && (
        <EmptyState
          title="Không có việc nào đang mở"
          description="Mọi việc được giao cho bạn đều đã hoàn thành hoặc đã huỷ."
          action={
            <Link href="/tasks/new" className={buttonClass("secondary", "sm")}>
              Tạo việc mới
            </Link>
          }
        />
      )}

      {data && tong > 0 && (
        <div className="grid gap-5 lg:grid-cols-2">
          {NHOM.map((nhom) => (
            <Nhom
              key={nhom.key}
              tieuDe={nhom.tieuDe}
              moTa={nhom.moTa}
              nhanManh={nhom.nhanManh}
              tasks={data[nhom.key]}
            />
          ))}
        </div>
      )}
    </div>
  );
}

function Nhom({
  tieuDe,
  moTa,
  tasks,
  nhanManh,
}: {
  tieuDe: string;
  moTa: string;
  tasks: Task[];
  nhanManh?: boolean;
}) {
  // Nhóm quá hạn được đánh dấu để nhìn lướt cũng thấy ngay — nhưng chỉ khi
  // thật sự có việc trễ, không thì thành báo động giả mỗi ngày.
  const bao = nhanManh === true && tasks.length > 0;

  return (
    <section aria-labelledby={`nhom-${tieuDe}`}>
      <div className="mb-3 flex items-center gap-2.5 px-0.5">
        <h2
          id={`nhom-${tieuDe}`}
          className="text-[0.95rem] font-semibold tracking-tight"
        >
          {tieuDe}
        </h2>

        {/* Con số nằm trong viên nhãn thay vì trôi cạnh tiêu đề: mắt bắt được
            số lượng trước khi kịp đọc chữ, và đó mới là thứ cần biết trước. */}
        <span
          className={cn(
            "inline-flex h-5 min-w-5 items-center justify-center rounded-full px-1.5 text-[0.72rem] font-semibold",
            bao
              ? "bg-danger-surface text-danger border-danger-line border"
              : "bg-paper-sunken text-ink-faint",
          )}
        >
          {tasks.length}
        </span>
      </div>

      {tasks.length === 0 ? (
        <p className="border-line text-ink-faint rounded-2xl border border-dashed px-4 py-6 text-[0.84rem]">
          {moTa} — không có.
        </p>
      ) : (
        <ul className="space-y-2.5">
          {tasks.map((task) => (
            <li key={task.id}>
              <TaskCard task={task} showStatus />
            </li>
          ))}
        </ul>
      )}
    </section>
  );
}
