import {
  useMutation,
  useQuery,
  useQueryClient,
  type UseMutationResult,
  type UseQueryResult,
} from "@tanstack/react-query";

import { api, type ApiError } from "@/lib/api-client";
import type { Wrapped } from "@/lib/pagination";

import type { LeaveBalanceItem, TeamLeaveBalances } from "../types/balance";
import { leaveKeys } from "./leave-api";

export const balanceKeys = {
  all: ["leave-balances"] as const,
  me: (year: number | null) => ["leave-balances", "me", year] as const,
  team: (year: number) => ["leave-balances", "team", year] as const,
};

export function useMyLeaveBalance(
  year: number | null = null,
): UseQueryResult<LeaveBalanceItem, ApiError> {
  return useQuery({
    queryKey: balanceKeys.me(year),
    queryFn: async () =>
      (
        await api.get<Wrapped<LeaveBalanceItem>>("/leave/balance", {
          query: { year },
        })
      ).data,
  });
}

export function useTeamLeaveBalances(
  year: number,
  enabled: boolean,
): UseQueryResult<TeamLeaveBalances, ApiError> {
  return useQuery({
    queryKey: balanceKeys.team(year),
    queryFn: async () =>
      (
        await api.get<Wrapped<TeamLeaveBalances>>("/leave/balances", {
          query: { year },
        })
      ).data,
    enabled,
    placeholderData: (previous) => previous,
  });
}

export interface SaveLeaveBalanceInput {
  /** uuid của người được sửa. */
  userId: string;
  year: number;
  /** `null` = để hệ thống tự tính. */
  entitled_days_override: number | null;
  carried_over_days: number;
  /** Được phép âm. */
  adjustment_days: number;
  note: string | null;
}

export function useSaveLeaveBalance(): UseMutationResult<
  Wrapped<LeaveBalanceItem>,
  ApiError,
  SaveLeaveBalanceInput
> {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: ({ userId, ...body }) =>
      api.post<Wrapped<LeaveBalanceItem>>(`/leave/balances/${userId}`, body),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: balanceKeys.all });

      /*
      | Làm mới cả màn đơn nghỉ.
      |
      | Quỹ vừa đổi nghĩa là ô "còn bao nhiêu ngày" trên form xin nghỉ đã cũ, và
      | người đang mở form đó sẽ nộp đơn dựa trên một con số không còn đúng —
      | rồi nhận lỗi từ server mà không hiểu vì sao.
      */
      void queryClient.invalidateQueries({ queryKey: leaveKeys.all });
    },
  });
}
