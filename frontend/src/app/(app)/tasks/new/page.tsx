"use client";

import Link from "next/link";
import { Suspense } from "react";
import { useSearchParams } from "next/navigation";

import { TaskForm } from "@/features/tasks/components/task-form";

/**
 * Tạo công việc.
 *
 * Nhận `?project_id=` để nút "Tạo việc" trên trang dự án mở form đã chọn sẵn
 * dự án đó — người dùng không phải tìm lại trong danh sách.
 */
export default function NewTaskPage() {
  return (
    <Suspense fallback={null}>
      <NewTaskView />
    </Suspense>
  );
}

function NewTaskView() {
  const searchParams = useSearchParams();
  const projectId = searchParams.get("project_id") ?? undefined;

  return (
    <div data-tone="task" className="enter mx-auto max-w-2xl space-y-6">
      <header>
        <Link
          href="/tasks"
          className="text-ink-faint hover:text-ink focus-frame rounded text-[0.82rem]"
        >
          ← Công việc
        </Link>
        <span
          aria-hidden="true"
          className="bg-tone mb-3 block h-[3px] w-9 rounded-full"
        />
        <h1 className="mt-2 text-[1.65rem] leading-tight font-semibold tracking-[-0.035em]">
          Tạo việc mới
        </h1>
      </header>

      <TaskForm projectId={projectId} />
    </div>
  );
}
