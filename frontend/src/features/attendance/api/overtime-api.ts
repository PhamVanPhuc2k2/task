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
  MyOvertime,
  OvertimeItem,
  OvertimePreview,
  TeamOvertime,
} from "../types/overtime";
import { attendanceKeys } from "./attendance-api";

/**
 * Khoá nằm DƯỚI `["attendance"]`, cùng lý do với đơn giải trình.
 *
 * Duyệt một đơn làm thêm làm đổi số đơn còn treo của kỳ — tức là đổi cả
 * `closable.ready` ở màn chốt sổ. Một lần `invalidateQueries(attendanceKeys.all)`
 * là đủ và không có chỗ nào để quên.
 */
export const overtimeKeys = {
  me: ["attendance", "overtime", "me"] as const,
  team: ["attendance", "overtime", "team"] as const,
  preview: (date: string) =>
    ["attendance", "overtime", "preview", date] as const,
};

export function useMyOvertime(): UseQueryResult<MyOvertime, ApiError> {
  return useQuery({
    queryKey: overtimeKeys.me,
    queryFn: async () =>
      (await api.get<Wrapped<MyOvertime>>("/attendance/overtime/me")).data,
  });
}

export function useTeamOvertime(
  enabled: boolean,
): UseQueryResult<TeamOvertime, ApiError> {
  return useQuery({
    queryKey: overtimeKeys.team,
    queryFn: async () =>
      (await api.get<Wrapped<TeamOvertime>>("/attendance/overtime/team")).data,
    enabled,
    placeholderData: (previous) => previous,
  });
}

/**
 * Hệ số của một ngày cụ thể.
 *
 * Khoá theo ngày nên đổi qua đổi lại giữa vài ngày không gọi lại mạng. Không
 * hỏi khi ô ngày còn trống.
 */
export function useOvertimePreview(
  date: string,
): UseQueryResult<OvertimePreview, ApiError> {
  return useQuery({
    queryKey: overtimeKeys.preview(date),
    queryFn: async () =>
      (
        await api.get<Wrapped<OvertimePreview>>(
          "/attendance/overtime/preview",
          {
            query: { date },
          },
        )
      ).data,
    enabled: date !== "",
    // Lịch tuần và ngày lễ gần như không đổi trong một phiên làm việc.
    staleTime: 5 * 60_000,
  });
}

export interface SubmitOvertimeInput {
  work_date: string;
  start_time: string;
  end_time: string;
  reason: string;
}

export function useSubmitOvertime(): UseMutationResult<
  Wrapped<OvertimeItem>,
  ApiError,
  SubmitOvertimeInput
> {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (input) =>
      api.post<Wrapped<OvertimeItem>>("/attendance/overtime", input),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: attendanceKeys.all });
    },
  });
}

export function useCancelOvertime(): UseMutationResult<
  unknown,
  ApiError,
  string
> {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (id) => api.post<unknown>(`/attendance/overtime/${id}/cancel`),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: attendanceKeys.all });
    },
  });
}

export interface ReviewOvertimeInput {
  id: string;
  approve: boolean;
  /**
   * Số phút người duyệt chốt. `null` = giữ đúng số đã đăng ký.
   *
   * Không gửi được nhiều hơn số đã đăng ký: cho phép thế là mở một đường vòng
   * qua ba cái trần của Điều 107 đã kiểm lúc nộp.
   */
  minutes: number | null;
  note: string;
}

export function useReviewOvertime(): UseMutationResult<
  unknown,
  ApiError,
  ReviewOvertimeInput
> {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: ({ id, approve, minutes, note }) =>
      api.post<unknown>(`/attendance/overtime/${id}/review`, {
        approve,
        minutes,
        note: note === "" ? null : note,
      }),
    onSuccess: () => {
      // Kỳ vừa bớt một đơn treo, và số phút đã dùng của người nộp vừa đổi.
      void queryClient.invalidateQueries({ queryKey: attendanceKeys.all });
    },
  });
}
