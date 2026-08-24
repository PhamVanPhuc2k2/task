import {
  useMutation,
  useQuery,
  useQueryClient,
  type UseMutationResult,
  type UseQueryResult,
} from "@tanstack/react-query";

import { api, type ApiError } from "@/lib/api-client";
import type { Wrapped } from "@/lib/pagination";

import type {
  BonusPoolStatusValue,
  MyBonus,
  ProjectBonusResponse,
} from "../types/bonus";

export const bonusKeys = {
  all: ["bonus"] as const,
  me: ["bonus", "me"] as const,
  project: (projectId: string) => ["bonus", "project", projectId] as const,
};

/** Thưởng của chính mình. Chỉ trả quỹ đã chốt — backend lọc sẵn. */
export function useMyBonus(): UseQueryResult<MyBonus, ApiError> {
  return useQuery({
    queryKey: bonusKeys.me,
    queryFn: async () => (await api.get<Wrapped<MyBonus>>("/bonus/me")).data,
  });
}

export function useProjectBonus(
  projectId: string,
  enabled: boolean,
): UseQueryResult<ProjectBonusResponse, ApiError> {
  return useQuery({
    queryKey: bonusKeys.project(projectId),
    queryFn: () =>
      api.get<ProjectBonusResponse>(`/projects/${projectId}/bonus`),
    enabled,
  });
}

export function useSaveBonusPool(): UseMutationResult<
  unknown,
  ApiError,
  { projectId: string; total_amount: string; condition_note: string }
> {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: ({ projectId, ...body }) =>
      api.post<unknown>(`/projects/${projectId}/bonus`, body),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: bonusKeys.all });
    },
  });
}

export interface AllocationInput {
  user_id: string;
  amount: string;
  reason: string;
}

/**
 * Thay thế **toàn bộ** danh sách phần chia.
 *
 * Không có endpoint sửa lẻ từng người: chia thưởng là một quyết định trên cả
 * nhóm, và trạng thái trung gian khi sửa lẻ có thể vượt quỹ.
 */
export function useAllocateBonus(): UseMutationResult<
  unknown,
  ApiError,
  { projectId: string; allocations: AllocationInput[] }
> {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: ({ projectId, allocations }) =>
      api.put<unknown>(`/projects/${projectId}/bonus/allocations`, {
        allocations,
      }),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: bonusKeys.all });
    },
  });
}

export function useChangePoolStatus(): UseMutationResult<
  unknown,
  ApiError,
  { projectId: string; status: BonusPoolStatusValue }
> {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: ({ projectId, status }) =>
      api.post<unknown>(`/projects/${projectId}/bonus/status`, { status }),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: bonusKeys.all });
    },
  });
}
