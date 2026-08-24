"use client";

import Link from "next/link";
import { Suspense } from "react";

import { buttonClass } from "@/components/ui/button";
import { EmptyState, ErrorState, ListSkeleton } from "@/components/ui/states";
import { useTasks } from "@/features/tasks/api/tasks-api";
import { TaskBoard } from "@/features/tasks/components/board";
import { TaskFiltersBar } from "@/features/tasks/components/task-filters-bar";
import { useTaskFilters } from "@/features/tasks/hooks/use-task-filters";

/** Số task tối đa bảng nạp. Bảng Kanban không phân trang được — kéo thả giữa */
/** hai trang là vô nghĩa — nên chặn cứng và nói rõ khi chạm trần. */
const TRAN = 100;

export default function BoardPage() {
  return (
    <Suspense fallback={<ListSkeleton rows={4} />}>
      <BoardView />
    </Suspense>
  );
}

function BoardView() {
  const { filters, setFilters } = useTaskFilters();

  // Bỏ lọc trạng thái: bảng vốn đã chia theo trạng thái, lọc thêm thì có cột
  // luôn trống mà không rõ vì sao.
  const { data, isPending, isError, error, refetch } = useTasks({
    ...filters,
    status: "",
    per_page: TRAN,
    page: 1,
  });

  const chamTran = data !== undefined && data.meta.total > TRAN;

  return (
    <div data-tone="task" className="enter space-y-6">
      <header className="flex flex-wrap items-end justify-between gap-4">
        <div>
          <span
            aria-hidden="true"
            className="bg-tone mb-3 block h-[3px] w-9 rounded-full"
          />
          <h1 className="text-[1.65rem] leading-tight font-semibold tracking-[-0.035em]">
            Bảng Kanban
          </h1>
          <p className="text-ink-soft mt-1.5 text-[0.9rem]">
            Kéo thẻ sang cột khác để đổi trạng thái, hoặc dùng ô “Chuyển sang…”
            trên từng thẻ.
          </p>
        </div>

        <Link href="/tasks/new" className={buttonClass("primary")}>
          Tạo việc mới
        </Link>
      </header>

      <TaskFiltersBar
        filters={{ ...filters, status: "" }}
        onChange={setFilters}
      />

      {chamTran && (
        <p className="border-notice-line bg-notice-surface text-notice rounded-xl border px-4 py-2.5 text-[0.85rem]">
          Bảng đang hiện {TRAN} việc gần hạn nhất trên tổng số {data.meta.total}
          . Lọc theo dự án hoặc mức ưu tiên để thu hẹp lại.
        </p>
      )}

      {isPending && <ListSkeleton rows={4} />}

      {isError && <ErrorState error={error} onRetry={() => void refetch()} />}

      {data &&
        (data.data.length === 0 ? (
          <EmptyState
            title="Không có việc nào trên bảng"
            description="Thử nới bộ lọc, hoặc tạo việc mới."
          />
        ) : (
          <TaskBoard tasks={data.data} />
        ))}
    </div>
  );
}
