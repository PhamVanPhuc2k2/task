"use client";

import { QueryClientProvider } from "@tanstack/react-query";
import { ReactQueryDevtools } from "@tanstack/react-query-devtools";
import { useState, type ReactNode } from "react";

import { makeQueryClient } from "@/lib/query-client";

/**
 * Bọc toàn bộ ứng dụng trong TanStack Query.
 *
 * QueryClient tạo qua useState để mỗi lần render trên server sinh một instance
 * riêng — nếu để biến module toàn cục thì các request của những người dùng khác
 * nhau sẽ dùng chung cache, làm lộ dữ liệu giữa các tài khoản.
 */
export function Providers({ children }: { children: ReactNode }) {
  const [queryClient] = useState(makeQueryClient);

  return (
    <QueryClientProvider client={queryClient}>
      {children}
      {process.env.NODE_ENV === "development" && (
        <ReactQueryDevtools initialIsOpen={false} />
      )}
    </QueryClientProvider>
  );
}
