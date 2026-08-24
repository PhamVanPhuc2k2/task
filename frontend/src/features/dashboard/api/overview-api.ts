import { useQuery, type UseQueryResult } from "@tanstack/react-query";

import { api, type ApiError } from "@/lib/api-client";
import type { Wrapped } from "@/lib/pagination";

import type { Overview } from "../types/overview";

export const overviewKeys = {
  all: ["dashboard", "overview"] as const,
};

/**
 * Số liệu tổng quan toàn công ty.
 *
 * `staleTime` một phút: đây là màn hình để nhìn lướt tình hình chứ không phải
 * để theo dõi thời gian thực. Gọi lại mỗi lần chuyển tab chỉ tạo tải cho
 * database mà không ai nhận ra khác biệt.
 */
export function useOverview(
  enabled = true,
): UseQueryResult<Overview, ApiError> {
  return useQuery({
    queryKey: overviewKeys.all,
    queryFn: async () =>
      (await api.get<Wrapped<Overview>>("/dashboard/overview")).data,
    staleTime: 60_000,
    enabled,
  });
}
