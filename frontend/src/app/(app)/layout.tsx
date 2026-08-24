import type { ReactNode } from "react";

import { AppShell } from "@/components/app-shell/app-shell";

/**
 * Layout của phần đã đăng nhập.
 *
 * Nhóm route `(app)` không xuất hiện trong URL — nó chỉ để mọi trang bên trong
 * dùng chung khung sidebar, còn `/login` nằm ngoài nhóm nên vẫn toàn màn hình.
 */
export default function AppLayout({ children }: { children: ReactNode }) {
  return <AppShell>{children}</AppShell>;
}
