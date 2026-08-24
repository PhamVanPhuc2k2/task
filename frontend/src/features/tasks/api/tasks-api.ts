import {
  useMutation,
  useQuery,
  useQueryClient,
  type UseMutationResult,
  type UseQueryResult,
} from "@tanstack/react-query";

import { api, type ApiError } from "@/lib/api-client";
import type { Paginated, Wrapped } from "@/lib/pagination";

import type {
  MyTaskBuckets,
  Task,
  TaskActivity,
  TaskPriorityValue,
  TaskStatusValue,
} from "../types/task";

/**
 * Bộ lọc của `GET /tasks`. Tên trường khớp đúng query string của backend nên
 * truyền thẳng được, không cần lớp ánh xạ ở giữa.
 */
// Khai báo bằng `type` chứ không `interface`: chỉ kiểu dạng `type` mới có chỉ
// mục ngầm, và `apiFetch` nhận `query` dưới dạng Record<string, …>. Dùng
// `interface` thì TypeScript từ chối ngay chỗ truyền vào.
export type TaskFilters = {
  status?: TaskStatusValue | "";
  priority?: TaskPriorityValue | "";
  assignee_id?: string;
  project_id?: string;
  overdue?: boolean;
  /*
   * Bốn cờ khớp 1-1 với bốn ô số ở trang Tổng quan.
   *
   * Backend lọc bằng ĐÚNG scope mà trang Tổng quan dùng để đếm, nên bấm vào ô
   * "12 việc quá hạn" thì danh sách mở ra đúng 12 dòng. Xem
   * App\Domain\Task\Models\Task.
   */
  open?: boolean;
  unassigned?: boolean;
  due_today?: boolean;
  completed_this_week?: boolean;
  due_from?: string;
  due_to?: string;
  search?: string;
  page?: number;
  per_page?: number;
};

/**
 * Khoá cache.
 *
 * `list` nhận nguyên bộ lọc: đổi bộ lọc là đổi khoá, nên mỗi tổ hợp lọc có
 * cache riêng và quay lại bộ lọc cũ thì hiện ngay, không gọi lại mạng.
 */
export const taskKeys = {
  all: ["tasks"] as const,
  list: (filters: TaskFilters) => ["tasks", "list", filters] as const,
  detail: (id: string) => ["tasks", "detail", id] as const,
  activities: (id: string) => ["tasks", "activities", id] as const,
  my: ["tasks", "my"] as const,
  team: ["tasks", "team"] as const,
};

export interface CreateTaskInput {
  title: string;
  description?: string | null;
  project_id?: string | null;
  parent_task_id?: string | null;
  assignee_id?: string | null;
  reviewer_id?: string | null;
  priority?: TaskPriorityValue;
  due_date?: string | null;
  estimate_hours?: string | null;
}

export interface UpdateTaskInput {
  title?: string;
  description?: string | null;
  priority?: TaskPriorityValue;
  progress_percent?: number;
}

export function useTasks(
  filters: TaskFilters,
): UseQueryResult<Paginated<Task>, ApiError> {
  return useQuery({
    queryKey: taskKeys.list(filters),
    queryFn: () =>
      api.get<Paginated<Task>>("/tasks", {
        query: { ...filters, overdue: filters.overdue ? 1 : undefined },
      }),
    // Giữ dữ liệu cũ trên màn hình khi đổi trang hoặc đổi bộ lọc, thay vì
    // chớp sang khung xương rồi mới có nội dung.
    placeholderData: (previous) => previous,
  });
}

export function useTask(id: string): UseQueryResult<Task, ApiError> {
  return useQuery({
    queryKey: taskKeys.detail(id),
    queryFn: async () => (await api.get<Wrapped<Task>>(`/tasks/${id}`)).data,
  });
}

export function useTaskActivities(
  id: string,
): UseQueryResult<TaskActivity[], ApiError> {
  return useQuery({
    queryKey: taskKeys.activities(id),
    queryFn: async () =>
      (await api.get<Paginated<TaskActivity>>(`/tasks/${id}/activities`)).data,
  });
}

export function useMyTasks(): UseQueryResult<MyTaskBuckets, ApiError> {
  return useQuery({
    queryKey: taskKeys.my,
    queryFn: async () =>
      (await api.get<Wrapped<MyTaskBuckets>>("/tasks/my")).data,
  });
}

export function useTeamTasks(
  filters: TaskFilters = {},
): UseQueryResult<Paginated<Task>, ApiError> {
  return useQuery({
    queryKey: [...taskKeys.team, filters] as const,
    queryFn: () => api.get<Paginated<Task>>("/tasks/team", { query: filters }),
    placeholderData: (previous) => previous,
  });
}

export function useCreateTask(): UseMutationResult<
  Task,
  ApiError,
  CreateTaskInput
> {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: async (input) =>
      (await api.post<Wrapped<Task>>("/tasks", input)).data,
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: taskKeys.all });
    },
  });
}

export function useUpdateTask(
  id: string,
): UseMutationResult<Task, ApiError, UpdateTaskInput> {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: async (input) =>
      (await api.patch<Wrapped<Task>>(`/tasks/${id}`, input)).data,
    onSuccess: (task) => {
      queryClient.setQueryData(taskKeys.detail(id), task);
      void queryClient.invalidateQueries({ queryKey: taskKeys.all });
    },
  });
}

export function useDeleteTask(): UseMutationResult<void, ApiError, string> {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (id) => api.delete<void>(`/tasks/${id}`),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: taskKeys.all });
    },
  });
}

export function useChangeTaskStatus(): UseMutationResult<
  Task,
  ApiError,
  { id: string; status: TaskStatusValue }
> {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: async ({ id, status }) =>
      (await api.patch<Wrapped<Task>>(`/tasks/${id}/status`, { status })).data,
    onSuccess: (task, { id }) => {
      queryClient.setQueryData(taskKeys.detail(id), task);
      void queryClient.invalidateQueries({ queryKey: taskKeys.all });
    },
  });
}

export function useAssignTask(): UseMutationResult<
  Task,
  ApiError,
  { id: string; assignee_id: string | null }
> {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: async ({ id, assignee_id }) =>
      (await api.patch<Wrapped<Task>>(`/tasks/${id}/assign`, { assignee_id }))
        .data,
    onSuccess: (task, { id }) => {
      queryClient.setQueryData(taskKeys.detail(id), task);
      void queryClient.invalidateQueries({ queryKey: taskKeys.all });
    },
  });
}

/** Dời hạn. Lý do là bắt buộc — ràng buộc nghiệp vụ, không phải trường cho có. */
export function useChangeDueDate(): UseMutationResult<
  Task,
  ApiError,
  { id: string; due_date: string | null; reason: string }
> {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: async ({ id, due_date, reason }) =>
      (
        await api.patch<Wrapped<Task>>(`/tasks/${id}/due-date`, {
          due_date,
          reason,
        })
      ).data,
    onSuccess: (task, { id }) => {
      queryClient.setQueryData(taskKeys.detail(id), task);
      void queryClient.invalidateQueries({ queryKey: taskKeys.activities(id) });
      void queryClient.invalidateQueries({ queryKey: taskKeys.all });
    },
  });
}
