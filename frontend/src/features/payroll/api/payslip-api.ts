import { useQuery, type UseQueryResult } from "@tanstack/react-query";

import { api, type ApiError } from "@/lib/api-client";
import type { Wrapped } from "@/lib/pagination";

import type { PayslipItem, PayslipSheet } from "../types/payslip";

export const payslipKeys = {
  me: (period: string | null) => ["payslips", "me", period] as const,
  sheet: (period: string | null) => ["payslips", "sheet", period] as const,
};

/**
 * Phiếu lương của chính mình.
 *
 * `period` để `null` thì server chọn **kỳ vừa kết thúc** — không phải tháng
 * đang chạy. Giao diện không tự tính tháng trước từ `new Date()`: đồng hồ máy
 * người dùng có thể lệch, và trong bảy tiếng đầu ngày mùng một giờ Việt Nam thì
 * một máy đặt múi giờ khác vẫn đang ở tháng cũ.
 */
export function useMyPayslip(
  period: string | null = null,
): UseQueryResult<PayslipItem, ApiError> {
  return useQuery({
    queryKey: payslipKeys.me(period),
    queryFn: async () =>
      (
        await api.get<Wrapped<PayslipItem>>("/payroll/payslips/me", {
          query: { period },
        })
      ).data,
  });
}

export function usePayslipSheet(
  period: string | null,
  enabled: boolean,
): UseQueryResult<PayslipSheet, ApiError> {
  return useQuery({
    queryKey: payslipKeys.sheet(period),
    queryFn: async () =>
      (
        await api.get<Wrapped<PayslipSheet>>("/payroll/payslips", {
          query: { period },
        })
      ).data,
    enabled,
    placeholderData: (previous) => previous,
  });
}
