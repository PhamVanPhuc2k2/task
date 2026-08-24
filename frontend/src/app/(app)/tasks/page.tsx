"use client";

import Link from "next/link";
import { Suspense } from "react";

import { Button, buttonClass } from "@/components/ui/button";
import { IconPlus } from "@/components/ui/icon";
import { Pagination } from "@/components/ui/pagination";
import { EmptyState, ErrorState, ListSkeleton } from "@/components/ui/states";
import { useTasks } from "@/features/tasks/api/tasks-api";
import { TaskFiltersBar } from "@/features/tasks/components/task-filters-bar";
import { TaskTable } from "@/features/tasks/components/task-table";
import { useTaskFilters } from "@/features/tasks/hooks/use-task-filters";

/**
 * Danh sách công việc — lọc, tìm, phân trang.
 *
 * `useSearchParams` bắt buộc nằm trong Suspense, nếu không `next build` sẽ
 * dừng: Next cần biết phần nào của trang phụ thuộc địa chỉ để dựng sẵn phần
 * còn lại.
 */
export default function TasksPage() {
  return (
    <Suspense fallback={<ListSkeleton rows={6} />}>
      <TasksView />
    </Suspense>
  );
}

function TasksView() {
  const { filters, setFilters, reset, hasFilters } = useTaskFilters();
  const { data, isPending, isError, error, refetch, isFetching } =
    useTasks(filters);

  return (
    <div data-tone="task" className="enter space-y-6">
      <header className="flex flex-wrap items-end justify-between gap-4">
        <div>
          <span
            aria-hidden="true"
            className="bg-tone mb-3 block h-[3px] w-9 rounded-full"
          />
          <h1 className="text-[1.65rem] leading-tight font-semibold tracking-[-0.035em]">
            Công việc
          </h1>
          <p className="text-ink-soft mt-1.5 text-[0.9rem]">
            Mọi việc bạn được phép xem, theo phạm vi phòng ban và vai trò.
          </p>
        </div>

        <Link href="/tasks/new" className={buttonClass("primary")}>
          <IconPlus className="size-4" />
          Tạo việc mới
        </Link>
      </header>

      <TaskFiltersBar filters={filters} onChange={setFilters} />

      {hasFilters && (
        <div className="flex items-center gap-3">
          <Button size="sm" variant="ghost" onClick={reset}>
            Xoá bộ lọc
          </Button>
          {isFetching && (
            <span className="text-ink-faint text-[0.8rem]">Đang lọc…</span>
          )}
        </div>
      )}

      {isPending && <ListSkeleton rows={6} />}

      {isError && <ErrorState error={error} onRetry={() => void refetch()} />}

      {data && data.data.length === 0 && (
        <EmptyState
          title={hasFilters ? "Không có việc nào khớp" : "Chưa có việc nào"}
          description={
            hasFilters
              ? "Thử nới bộ lọc hoặc xoá từ khoá tìm kiếm."
              : "Việc được giao cho bạn sẽ hiện ở đây."
          }
          action={
            hasFilters ? (
              <Button size="sm" onClick={reset}>
                Xoá bộ lọc
              </Button>
            ) : undefined
          }
        />
      )}

      {data && data.data.length > 0 && (
        <>
          <TaskTable tasks={data.data} />

          <Pagination
            page={data.meta.current_page}
            lastPage={data.meta.last_page}
            total={data.meta.total}
            from={data.meta.from}
            to={data.meta.to}
            onChange={(page) => setFilters({ page })}
          />
        </>
      )}
    </div>
  );
}
