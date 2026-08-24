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
  AppNotification,
  NotificationSetting,
} from "../types/notification";

export const notificationKeys = {
  all: ["notifications"] as const,
  list: (unread: boolean, page: number) =>
    ["notifications", "list", unread, page] as const,
  unreadCount: ["notifications", "unread-count"] as const,
  settings: ["notifications", "settings"] as const,
};

export function useNotifications(
  unread = false,
  page = 1,
): UseQueryResult<Paginated<AppNotification>, ApiError> {
  return useQuery({
    queryKey: notificationKeys.list(unread, page),
    queryFn: () =>
      api.get<Paginated<AppNotification>>("/notifications", {
        query: { unread: unread ? 1 : undefined, page },
      }),
    placeholderData: (previous) => previous,
  });
}

/**
 * Số chưa đọc cho chuông trên đầu trang.
 *
 * Hỏi lại mỗi phút thay vì mở kết nối realtime: đợt 1 chưa có WebSocket, và
 * một truy vấn đếm mỗi phút cho vài trăm nhân sự là rẻ hơn nhiều so với dựng
 * hạ tầng broadcast chỉ để hiện một con số. Chuyển sang realtime ở đợt sau nếu
 * thật sự cần.
 */
export function useUnreadCount(): UseQueryResult<number, ApiError> {
  return useQuery({
    queryKey: notificationKeys.unreadCount,
    queryFn: async () =>
      (
        await api.get<Wrapped<{ unread: number }>>(
          "/notifications/unread-count",
        )
      ).data.unread,
    refetchInterval: 60_000,
    // Quay lại tab thì hỏi ngay, không đợi hết một phút.
    refetchOnWindowFocus: true,
  });
}

export function useMarkNotificationRead(): UseMutationResult<
  unknown,
  ApiError,
  string
> {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (id) => api.patch<unknown>(`/notifications/${id}/read`),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: notificationKeys.all });
    },
  });
}

export function useMarkAllRead(): UseMutationResult<unknown, ApiError, void> {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: () => api.post<unknown>("/notifications/read-all"),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: notificationKeys.all });
    },
  });
}

export function useNotificationSettings(): UseQueryResult<
  NotificationSetting[],
  ApiError
> {
  return useQuery({
    queryKey: notificationKeys.settings,
    queryFn: async () =>
      (await api.get<Wrapped<NotificationSetting[]>>("/notification-settings"))
        .data,
  });
}

export function useUpdateNotificationSetting(): UseMutationResult<
  NotificationSetting[],
  ApiError,
  { type: string; in_app: boolean; email: boolean }
> {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: async (input) =>
      (
        await api.patch<Wrapped<NotificationSetting[]>>(
          "/notification-settings",
          input,
        )
      ).data,
    onSuccess: (settings) => {
      // Server trả về nguyên danh sách sau khi lưu — đặt thẳng vào cache thay
      // vì gọi lại một vòng nữa.
      queryClient.setQueryData(notificationKeys.settings, settings);
    },
  });
}
