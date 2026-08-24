import { QueryClient } from "@tanstack/react-query";

import { ApiError } from "./api-client";

/**
 * Cấu hình mặc định cho toàn bộ dữ liệu máy chủ.
 *
 * Theo quy ước trong README: dữ liệu máy chủ do TanStack Query quản lý,
 * không nhét vào global store.
 */
export function makeQueryClient(): QueryClient {
  return new QueryClient({
    defaultOptions: {
      queries: {
        // Comment và task thay đổi thường xuyên nhưng không cần realtime.
        staleTime: 30_000,
        gcTime: 5 * 60_000,
        refetchOnWindowFocus: true,
        retry: (failureCount, error) => {
          // Lỗi do người dùng (401/403/404/422) thì thử lại cũng vô ích.
          if (error instanceof ApiError && error.status < 500) {
            return false;
          }

          return failureCount < 2;
        },
      },
      mutations: {
        // Thao tác ghi không tự thử lại — tránh tạo trùng bản ghi.
        retry: false,
      },
    },
  });
}
