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
  AttendanceExceptionValue,
  LateArrivalItem,
  MyLateArrivals,
  TeamLateArrivals,
} from "../types/late-arrival";

export const lateArrivalKeys = {
  all: ["late-arrivals"] as const,
  me: ["late-arrivals", "me"] as const,
  team: ["late-arrivals", "team"] as const,
};

export function useMyLateArrivals(): UseQueryResult<MyLateArrivals, ApiError> {
  return useQuery({
    queryKey: lateArrivalKeys.me,
    queryFn: async () =>
      (await api.get<Wrapped<MyLateArrivals>>("/late-arrivals/me")).data,
  });
}

export function useTeamLateArrivals(
  enabled: boolean,
): UseQueryResult<TeamLateArrivals, ApiError> {
  return useQuery({
    queryKey: lateArrivalKeys.team,
    queryFn: async () =>
      (await api.get<Wrapped<TeamLateArrivals>>("/late-arrivals/team")).data,
    enabled,
    placeholderData: (previous) => previous,
  });
}

export interface SubmitLateArrivalInput {
  type: AttendanceExceptionValue;
  date: string;
  /** Chỉ gửi với đơn đi muộn. */
  expected_arrival?: string;
  /** Chỉ gửi với đơn về sớm. */
  expected_departure?: string;
  reason: string;
}

export function useSubmitLateArrival(): UseMutationResult<
  Wrapped<LateArrivalItem>,
  ApiError,
  SubmitLateArrivalInput
> {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (input) =>
      api.post<Wrapped<LateArrivalItem>>("/late-arrivals", input),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: lateArrivalKeys.all });
    },
  });
}

export function useCancelLateArrival(): UseMutationResult<
  unknown,
  ApiError,
  string
> {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (id) => api.post<unknown>(`/late-arrivals/${id}/cancel`),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: lateArrivalKeys.all });
    },
  });
}

export function useReviewLateArrival(): UseMutationResult<
  unknown,
  ApiError,
  { id: string; approve: boolean; note: string }
> {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: ({ id, approve, note }) =>
      api.post<unknown>(`/late-arrivals/${id}/review`, {
        approve,
        note: note === "" ? null : note,
      }),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: lateArrivalKeys.all });
      // Bảng công đổi theo: ngày vừa duyệt giờ không còn bị đánh dấu đi muộn.
      void queryClient.invalidateQueries({ queryKey: ["attendance"] });
    },
  });
}
