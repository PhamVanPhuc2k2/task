"use client";

import { Suspense } from "react";

import { Pagination } from "@/components/ui/pagination";
import { EmptyState, ErrorState, ListSkeleton } from "@/components/ui/states";
import { useTeamTasks } from "@/features/tasks/api/tasks-api";
import { TaskTable } from "@/features/tasks/components/task-table";
import { useTaskFilters } from "@/features/tasks/hooks/use-task-filters";

/**
 * "Việc của đội" — task của mọi người thuộc phòng mình và các phòng trực thuộc.
 *
 * Không có thanh lọc như trang Công việc: đây là màn hình đọc nhanh xem cấp
 * dưới đang vướng gì, ai cần lọc kỹ thì sang trang Công việc lọc theo người.
 */
export default function TeamPage() {
  return (
    <Suspense fallback={<ListSkeleton rows={6} />}>
      <TeamView />
    </Suspense>
  );
}

function TeamView() {
  const { filters, setFilters } = useTaskFilters();
  const { data, isPending, isError, error, refetch } = useTeamTasks({
    page: filters.page,
  });

  return (
    <div data-tone="task" className="enter space-y-6">
      <header>
        <span
          aria-hidden="true"
          className="bg-tone mb-3 block h-[3px] w-9 rounded-full"
        />
        <h1 className="text-[1.65rem] leading-tight font-semibold tracking-[-0.035em]">
          Việc của đội
        </h1>
        <p className="text-ink-soft mt-1.5 text-[0.9rem]">
          Công việc của nhân sự thuộc phòng bạn và các phòng trực thuộc.
        </p>
      </header>

      {isPending && <ListSkeleton rows={6} />}

      {isError && <ErrorState error={error} onRetry={() => void refetch()} />}

      {data && data.data.length === 0 && (
        <EmptyState
          title="Chưa có việc nào"
          description="Khi cấp dưới của bạn được giao việc, chúng sẽ hiện ở đây."
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
