import {
  useMutation,
  useQuery,
  useQueryClient,
  type UseMutationResult,
  type UseQueryResult,
} from "@tanstack/react-query";

import { api, type ApiError } from "@/lib/api-client";
import type { Wrapped } from "@/lib/pagination";

import type { DailyReport, MyReports, TeamReports } from "../types/report";

export const reportKeys = {
  all: ["reports"] as const,
  me: (month: string) => ["reports", "me", month] as const,
  team: (date: string) => ["reports", "team", date] as const,
};

export function useMyReports(
  month: string,
): UseQueryResult<MyReports, ApiError> {
  return useQuery({
    queryKey: reportKeys.me(month),
    queryFn: async () =>
      (await api.get<Wrapped<MyReports>>("/reports/me", { query: { month } }))
        .data,
  });
}

export function useTeamReports(
  date: string,
  enabled: boolean,
): UseQueryResult<TeamReports, ApiError> {
  return useQuery({
    queryKey: reportKeys.team(date),
    queryFn: async () =>
      (
        await api.get<Wrapped<TeamReports>>("/reports/team", {
          query: { date },
        })
      ).data,
    enabled,
    placeholderData: (previous) => previous,
  });
}

export interface SaveReportInput {
  report_date: string;
  content: string;
  task_ids: string[];
  /** `false` = lưu nháp, `true` = nộp. */
  submit: boolean;
}

/**
 * Lưu nháp hoặc nộp — một endpoint, một cờ.
 *
 * Tách thành hai hàm thì "lưu nháp rồi nộp" trở thành hai request liên tiếp ghi
 * đè nhau, và phía giao diện phải nhớ gọi đúng đường.
 */
export function useSaveReport(): UseMutationResult<
  Wrapped<DailyReport>,
  ApiError,
  SaveReportInput
> {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (input) => api.post<Wrapped<DailyReport>>("/reports", input),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: reportKeys.all });
      // Bảng công hiện cờ "đã báo cáo" nên phải làm mới theo.
      void queryClient.invalidateQueries({ queryKey: ["attendance"] });
    },
  });
}

export function useReviewReport(): UseMutationResult<
  unknown,
  ApiError,
  { reportId: string; note: string }
> {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: ({ reportId, note }) =>
      api.post<unknown>(`/reports/${reportId}/review`, {
        note: note || null,
      }),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: reportKeys.all });
    },
  });
}
