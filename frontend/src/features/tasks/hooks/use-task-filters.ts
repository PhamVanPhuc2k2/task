"use client";

import { usePathname, useRouter, useSearchParams } from "next/navigation";
import { useCallback, useMemo } from "react";

import type { TaskFilters } from "../api/tasks-api";

/**
 * Bộ lọc lưu trong địa chỉ trang, không lưu trong state của component.
 *
 * Nhờ vậy tải lại trang không mất bộ lọc, nút Back của trình duyệt quay đúng
 * về bộ lọc trước, và người dùng gửi được đường dẫn "việc quá hạn của phòng
 * tôi" cho đồng nghiệp. Giữ trong state thì cả ba thứ đó đều mất.
 */
export function useTaskFilters(): {
  filters: TaskFilters;
  setFilters: (patch: Partial<TaskFilters>) => void;
  reset: () => void;
  hasFilters: boolean;
} {
  const router = useRouter();
  const pathname = usePathname();
  const searchParams = useSearchParams();

  const filters = useMemo<TaskFilters>(
    () => ({
      search: searchParams.get("search") ?? "",
      status: (searchParams.get("status") ?? "") as TaskFilters["status"],
      priority: (searchParams.get("priority") ?? "") as TaskFilters["priority"],
      project_id: searchParams.get("project_id") ?? "",
      assignee_id: searchParams.get("assignee_id") ?? "",
      overdue: searchParams.get("overdue") === "1",
      open: searchParams.get("open") === "1",
      unassigned: searchParams.get("unassigned") === "1",
      due_today: searchParams.get("due_today") === "1",
      completed_this_week: searchParams.get("completed_this_week") === "1",
      page: Number(searchParams.get("page") ?? 1),
    }),
    [searchParams],
  );

  const setFilters = useCallback(
    (patch: Partial<TaskFilters>) => {
      const params = new URLSearchParams(searchParams.toString());

      for (const [key, value] of Object.entries(patch)) {
        // Giá trị rỗng thì bỏ hẳn khỏi địa chỉ, không để `?status=` lủng lẳng.
        if (value === "" || value === false || value === undefined) {
          params.delete(key);
        } else {
          params.set(key, value === true ? "1" : String(value));
        }
      }

      // Trang 1 là mặc định, không cần ghi ra.
      if (params.get("page") === "1") params.delete("page");

      const query = params.toString();

      // `replace` chứ không `push`: mỗi lần gõ một chữ trong ô tìm kiếm mà đẩy
      // thêm một mục lịch sử thì bấm Back mười lần mới ra khỏi trang.
      router.replace(query ? `${pathname}?${query}` : pathname, {
        scroll: false,
      });
    },
    [pathname, router, searchParams],
  );

  const reset = useCallback(() => {
    router.replace(pathname, { scroll: false });
  }, [pathname, router]);

  const hasFilters =
    Boolean(filters.search) ||
    Boolean(filters.status) ||
    Boolean(filters.priority) ||
    Boolean(filters.project_id) ||
    Boolean(filters.assignee_id) ||
    filters.overdue === true ||
    filters.open === true ||
    filters.unassigned === true ||
    filters.due_today === true ||
    filters.completed_this_week === true;

  return { filters, setFilters, reset, hasFilters };
}
