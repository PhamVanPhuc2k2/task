import type { Metadata } from "next";
import { Suspense } from "react";

import { AuthShell } from "@/features/auth/components/auth-shell";
import { ResetPasswordForm } from "@/features/auth/components/reset-password-form";

export const metadata: Metadata = {
  title: "Đặt lại mật khẩu",
};

export default function ResetPasswordPage() {
  return (
    <AuthShell footerNote="Đặt lại mật khẩu">
      {/* `useSearchParams` bắt buộc phải nằm trong Suspense, nếu không cả trang
          bị ép sang render động và `next build` báo lỗi. */}
      <Suspense
        fallback={<div className="bg-line h-80 animate-pulse rounded-lg" />}
      >
        <ResetPasswordForm />
      </Suspense>
    </AuthShell>
  );
}
