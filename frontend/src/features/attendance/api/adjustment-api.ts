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
  AdjustmentItem,
  MyAdjustments,
  TeamAdjustments,
} from "../types/adjustment";
import { attendanceKeys } from "./attendance-api";

/**
 * Khoá nằm DƯỚI `["attendance"]`, có chủ ý.
 *
 * Duyệt một đơn giải trình ghi một dòng vào bảng công, và làm đổi số đơn còn
 * treo của kỳ — tức là đổi cả `closable.ready` ở màn chốt sổ. Ba thứ đó luôn
 * đổi cùng nhau, nên một lần `invalidateQueries(attendanceKeys.all)` là đủ và
 * không có chỗ nào để quên.
 */
export const adjustmentKeys = {
  me: ["attendance", "adjustments", "me"] as const,
  team: ["attendance", "adjustments", "team"] as const,
};

export function useMyAdjustments(): UseQueryResult<MyAdjustments, ApiError> {
  return useQuery({
    queryKey: adjustmentKeys.me,
    queryFn: async () =>
      (await api.get<Wrapped<MyAdjustments>>("/attendance/adjustments/me"))
        .data,
  });
}

export function useTeamAdjustments(
  enabled: boolean,
): UseQueryResult<TeamAdjustments, ApiError> {
  return useQuery({
    queryKey: adjustmentKeys.team,
    queryFn: async () =>
      (await api.get<Wrapped<TeamAdjustments>>("/attendance/adjustments/team"))
        .data,
    enabled,
    placeholderData: (previous) => previous,
  });
}

export interface SubmitAdjustmentInput {
  work_date: string;
  reason: string;
  /** Bỏ trống nghĩa là xin bỏ qua ngày này, không đề nghị con số nào. */
  requested_minutes?: number | null;
}

export function useSubmitAdjustment(): UseMutationResult<
  Wrapped<AdjustmentItem>,
  ApiError,
  SubmitAdjustmentInput
> {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (input) =>
      api.post<Wrapped<AdjustmentItem>>("/attendance/adjustments", input),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: attendanceKeys.all });
    },
  });
}

export function useCancelAdjustment(): UseMutationResult<
  unknown,
  ApiError,
  string
> {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (id) =>
      api.post<unknown>(`/attendance/adjustments/${id}/cancel`),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: attendanceKeys.all });
    },
  });
}

export interface ReviewAdjustmentInput {
  id: string;
  approve: boolean;
  /**
   * Số phút người duyệt chốt.
   *
   * `null` = bỏ qua ngày này nhưng giữ nguyên số hệ thống đo được. Khác hẳn
   * việc để giao diện tự điền lại `requested_minutes` — cái đi vào bảng công
   * phải là con số người duyệt gửi lên.
   */
  minutes: number | null;
  note: string;
}

export function useReviewAdjustment(): UseMutationResult<
  unknown,
  ApiError,
  ReviewAdjustmentInput
> {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: ({ id, approve, minutes, note }) =>
      api.post<unknown>(`/attendance/adjustments/${id}/review`, {
        approve,
        minutes,
        note: note === "" ? null : note,
      }),
    onSuccess: () => {
      // Bảng công vừa có thêm một dòng quyết định, và kỳ vừa bớt một đơn treo.
      void queryClient.invalidateQueries({ queryKey: attendanceKeys.all });
    },
  });
}
