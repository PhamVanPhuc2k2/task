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
  SettingValue,
  SiteBranding,
  SiteSettingsData,
} from "../types/site";

export const siteKeys = {
  all: ["site"] as const,
  branding: ["site", "branding"] as const,
  settings: ["site", "settings"] as const,
};

/**
 * Nhận diện công ty.
 *
 * `staleTime` một giờ: tên và logo gần như không bao giờ đổi, mà hook này chạy
 * ở **mọi** trang (đầu trang và trang đăng nhập). Không đặt thì mỗi lần chuyển
 * trang là một lượt gọi cho dữ liệu y nguyên.
 */
export function useSiteBranding(): UseQueryResult<SiteBranding, ApiError> {
  return useQuery({
    queryKey: siteKeys.branding,
    queryFn: async () => (await api.get<Wrapped<SiteBranding>>("/site")).data,
    staleTime: 3_600_000,
    // Không cần đăng nhập, nên đừng thử lại nhiều: lỗi ở đây nghĩa là mạng
    // hỏng, và giao diện đã có sẵn đường lùi về tên mặc định.
    retry: 1,
  });
}

export function useSiteSettings(
  enabled: boolean,
): UseQueryResult<SiteSettingsData, ApiError> {
  return useQuery({
    queryKey: siteKeys.settings,
    queryFn: async () =>
      (await api.get<Wrapped<SiteSettingsData>>("/settings")).data,
    enabled,
  });
}

export function useUpdateSiteSettings(): UseMutationResult<
  Wrapped<SiteSettingsData>,
  ApiError,
  Record<string, SettingValue>
> {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (values) =>
      api.put<Wrapped<SiteSettingsData>>("/settings", { values }),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: siteKeys.all });
      /*
      | Cài đặt ghi đè config mà chấm công, báo cáo và nghỉ phép đều đọc. Đổi ca
      | làm mà không xoá cache những màn đó thì số phút đi muộn trên màn hình vẫn
      | tính theo mốc CŨ — người dùng thấy báo lưu thành công rồi tưởng hệ thống
      | không có tác dụng.
      */
      void queryClient.invalidateQueries({ queryKey: ["attendance"] });
      void queryClient.invalidateQueries({ queryKey: ["leave"] });
      void queryClient.invalidateQueries({ queryKey: ["late-arrivals"] });
      void queryClient.invalidateQueries({ queryKey: ["reports"] });
    },
  });
}

export function useUploadLogo(): UseMutationResult<
  Wrapped<{ logo_url: string }>,
  ApiError,
  File
> {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (file) => {
      const form = new FormData();
      form.append("logo", file);

      return api.post<Wrapped<{ logo_url: string }>>("/settings/logo", form);
    },
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: siteKeys.all });
    },
  });
}

export function useRemoveLogo(): UseMutationResult<unknown, ApiError, void> {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: () => api.delete<unknown>("/settings/logo"),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: siteKeys.all });
    },
  });
}
