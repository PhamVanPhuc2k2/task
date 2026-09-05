import {
  useMutation,
  useQuery,
  useQueryClient,
  type UseMutationResult,
  type UseQueryResult,
} from "@tanstack/react-query";

import { api, type ApiError } from "@/lib/api-client";
import type { Wrapped } from "@/lib/pagination";

import type { PeriodItem, PeriodList } from "../types/period";
import { attendanceKeys } from "./attendance-api";

export const periodKeys = {
  list: ["attendance", "periods"] as const,
};

export function usePeriods(
  enabled: boolean,
): UseQueryResult<PeriodList, ApiError> {
  return useQuery({
    queryKey: periodKeys.list,
    queryFn: async () =>
      (await api.get<Wrapped<PeriodList>>("/attendance/periods")).data,
    enabled,
  });
}

export function useClosePeriod(): UseMutationResult<
  Wrapped<PeriodItem>,
  ApiError,
  string
> {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (period) =>
      api.post<Wrapped<PeriodItem>>("/attendance/periods/close", { period }),
    onSuccess: () => {
      /*
      | Làm mới CẢ bảng công, không chỉ danh sách kỳ.
      |
      | Chốt sổ khoá mọi đường ghi vào kỳ đó — nút "Duyệt" trên bảng công và nút
      | "Gửi đơn" ở màn giải trình đều vừa thành vô hiệu. Chỉ làm mới danh sách
      | kỳ thì hai màn kia vẫn mời người ta bấm vào thứ chắc chắn sẽ trả lỗi.
      */
      void queryClient.invalidateQueries({ queryKey: attendanceKeys.all });
    },
  });
}

export function useReopenPeriod(): UseMutationResult<
  Wrapped<PeriodItem>,
  ApiError,
  { period: string; reason: string }
> {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: ({ period, reason }) =>
      api.post<Wrapped<PeriodItem>>("/attendance/periods/reopen", {
        period,
        reason,
      }),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: attendanceKeys.all });
    },
  });
}
