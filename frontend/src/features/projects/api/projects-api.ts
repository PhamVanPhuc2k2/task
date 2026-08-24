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
  Project,
  ProjectMember,
  ProjectRoleValue,
  ProjectStatusValue,
} from "../types/project";

// `type` chứ không `interface` — xem chú thích ở TaskFilters: chỉ kiểu dạng
// `type` mới truyền được vào tham số `query` kiểu Record của apiFetch.
export type ProjectFilters = {
  status?: ProjectStatusValue | "";
  /** Chỉ lấy dự án còn nhận việc mới. */
  open?: boolean;
  search?: string;
  page?: number;
  per_page?: number;
};

export const projectKeys = {
  all: ["projects"] as const,
  list: (filters: ProjectFilters) => ["projects", "list", filters] as const,
  detail: (id: string) => ["projects", "detail", id] as const,
  members: (id: string) => ["projects", "members", id] as const,
};

export interface ProjectInput {
  name: string;
  code?: string | null;
  description?: string | null;
  status?: ProjectStatusValue;
  start_date?: string | null;
  end_date?: string | null;
  owner_id?: string | null;
}

export function useProjects(
  filters: ProjectFilters = {},
): UseQueryResult<Paginated<Project>, ApiError> {
  return useQuery({
    queryKey: projectKeys.list(filters),
    queryFn: () =>
      api.get<Paginated<Project>>("/projects", {
        query: { ...filters, open: filters.open ? 1 : undefined },
      }),
    placeholderData: (previous) => previous,
  });
}

export function useProject(id: string): UseQueryResult<Project, ApiError> {
  return useQuery({
    queryKey: projectKeys.detail(id),
    queryFn: async () =>
      (await api.get<Wrapped<Project>>(`/projects/${id}`)).data,
  });
}

export function useProjectMembers(
  id: string,
): UseQueryResult<ProjectMember[], ApiError> {
  return useQuery({
    queryKey: projectKeys.members(id),
    queryFn: async () =>
      (await api.get<Wrapped<ProjectMember[]>>(`/projects/${id}/members`)).data,
  });
}

export function useCreateProject(): UseMutationResult<
  Project,
  ApiError,
  ProjectInput
> {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: async (input) =>
      (await api.post<Wrapped<Project>>("/projects", input)).data,
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: projectKeys.all });
    },
  });
}

export function useUpdateProject(
  id: string,
): UseMutationResult<Project, ApiError, Partial<ProjectInput>> {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: async (input) =>
      (await api.patch<Wrapped<Project>>(`/projects/${id}`, input)).data,
    onSuccess: (project) => {
      queryClient.setQueryData(projectKeys.detail(id), project);
      void queryClient.invalidateQueries({ queryKey: projectKeys.all });
    },
  });
}

export function useSetProjectMember(
  projectId: string,
): UseMutationResult<
  unknown,
  ApiError,
  { user_id: string; role: ProjectRoleValue }
> {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (input) =>
      api.post<unknown>(`/projects/${projectId}/members`, input),
    onSuccess: () => {
      void queryClient.invalidateQueries({
        queryKey: projectKeys.members(projectId),
      });
    },
  });
}

export function useRemoveProjectMember(
  projectId: string,
): UseMutationResult<void, ApiError, string> {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (userId) =>
      api.delete<void>(`/projects/${projectId}/members/${userId}`),
    onSuccess: () => {
      void queryClient.invalidateQueries({
        queryKey: projectKeys.members(projectId),
      });
    },
  });
}
