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
  PayrollFilters,
  PayrollRow,
  SalaryRecord,
} from "../types/payroll";

export const payrollKeys = {
  all: ["payroll"] as const,
  list: (filters: PayrollFilters) => ["payroll", "list", filters] as const,
  history: (userId: string) => ["payroll", "history", userId] as const,
};

export function usePayroll(
  filters: PayrollFilters,
  enabled: boolean,
): UseQueryResult<Paginated<PayrollRow>, ApiError> {
  return useQuery({
    queryKey: payrollKeys.list(filters),
    queryFn: () =>
      api.get<Paginated<PayrollRow>>("/payroll", { query: filters }),
    enabled,
    placeholderData: (previous) => previous,
  });
}

/**
 * Lịch sử mức lương của một người.
 *
 * Mỗi lượt gọi endpoint này ghi một dòng nhật ký ở backend khi xem của người
 * khác — nên `staleTime` để mặc định và KHÔNG bật `refetchOnWindowFocus`: mở
 * lại tab không phải là một lượt xem mới đáng ghi.
 */
export function useSalaryHistory(
  userId: string,
  enabled: boolean,
): UseQueryResult<SalaryRecord[], ApiError> {
  return useQuery({
    queryKey: payrollKeys.history(userId),
    queryFn: async () =>
      (await api.get<Wrapped<SalaryRecord[]>>(`/payroll/${userId}`)).data,
    enabled,
    refetchOnWindowFocus: false,
  });
}

export interface SetSalaryInput {
  userId: string;
  base_salary: string;
  allowance: string;
  effective_from: string;
  reason: string;
}

export function useSetSalary(): UseMutationResult<
  Wrapped<SalaryRecord>,
  ApiError,
  SetSalaryInput
> {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: ({ userId, ...body }) =>
      api.post<Wrapped<SalaryRecord>>(`/payroll/${userId}`, body),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: payrollKeys.all });
    },
  });
}
