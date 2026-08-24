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
  LeaveRequestItem,
  LeaveStatusValue,
  MyLeave,
  TeamLeave,
} from "../types/leave";

export const leaveKeys = {
  all: ["leave"] as const,
  me: ["leave", "me"] as const,
  team: (status: string) => ["leave", "team", status] as const,
};

export function useMyLeave(): UseQueryResult<MyLeave, ApiError> {
  return useQuery({
    queryKey: leaveKeys.me,
    queryFn: async () => (await api.get<Wrapped<MyLeave>>("/leave/me")).data,
  });
}

export function useTeamLeave(
  status: LeaveStatusValue,
  enabled: boolean,
): UseQueryResult<TeamLeave, ApiError> {
  return useQuery({
    queryKey: leaveKeys.team(status),
    queryFn: async () =>
      (await api.get<Wrapped<TeamLeave>>("/leave/team", { query: { status } }))
        .data,
    enabled,
    placeholderData: (previous) => previous,
  });
}

export interface SubmitLeaveInput {
  type: string;
  start_date: string;
  end_date: string;
  reason: string;
}

export function useSubmitLeave(): UseMutationResult<
  Wrapped<LeaveRequestItem>,
  ApiError,
  SubmitLeaveInput
> {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (input) => api.post<Wrapped<LeaveRequestItem>>("/leave", input),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: leaveKeys.all });
    },
  });
}

export function useCancelLeave(): UseMutationResult<unknown, ApiError, string> {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (id) => api.post<unknown>(`/leave/${id}/cancel`),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: leaveKeys.all });
    },
  });
}

export function useReviewLeave(): UseMutationResult<
  unknown,
  ApiError,
  { id: string; approve: boolean; note: string }
> {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: ({ id, approve, note }) =>
      api.post<unknown>(`/leave/${id}/review`, {
        approve,
        note: note === "" ? null : note,
      }),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: leaveKeys.all });
      // Bảng công đổi theo: ngày vừa duyệt giờ được miễn chấm công.
      void queryClient.invalidateQueries({ queryKey: ["attendance"] });
    },
  });
}
