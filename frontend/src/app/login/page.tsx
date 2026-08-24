import type { Metadata } from "next";
import { Suspense } from "react";

import { AuthFlow } from "@/features/auth/components/auth-flow";
import { AuthShell } from "@/features/auth/components/auth-shell";
import { ExplusMark } from "@/features/auth/components/explus-mark";

export const metadata: Metadata = {
  title: "Đăng nhập",
};

const DIEM_MANH = [
  "Giao việc và theo dõi tiến độ",
  "Báo cáo hằng ngày kèm hình ảnh",
  "Chấm công cho đội làm từ xa",
];

export default function LoginPage() {
  return (
    <AuthShell
      footerNote="Bảo mật hai lớp"
      aside={
        // Ẩn trên điện thoại để form chiếm trọn chỗ.
        <section className="stagger hidden lg:block">
          <p className="text-accent mb-5 font-mono text-[0.7rem] tracking-[0.2em] uppercase">
            Quản lý công việc &amp; nhân sự
          </p>

          <h1 className="text-[3.4rem] leading-[1.02] font-bold tracking-[-0.035em] text-balance">
            Việc của hôm nay,
            <br />
            <span className="text-ink-soft">rõ ràng từ sáng.</span>
          </h1>

          <p className="text-ink-soft mt-6 max-w-md text-[1.02rem] leading-relaxed">
            Một chỗ duy nhất cho task, deadline và bảng công — thay cho chuỗi
            tin nhắn và những file Excel rời rạc.
          </p>

          <ul className="mt-9 space-y-3.5">
            {DIEM_MANH.map((muc) => (
              <li
                key={muc}
                className="flex items-center gap-3.5 text-[0.95rem]"
              >
                <span className="border-accent/30 bg-accent/10 grid h-6 w-6 shrink-0 place-items-center rounded-md border">
                  <ExplusMark className="text-accent h-2.5 w-2.5" />
                </span>
                <span className="text-ink-soft">{muc}</span>
              </li>
            ))}
          </ul>
        </section>
      }
      belowCard={
        <p className="text-ink-faint mt-6 text-center text-[0.8rem] leading-relaxed">
          Chưa có tài khoản? Tài khoản do bộ phận nhân sự cấp.
        </p>
      }
    >
      <Suspense
        fallback={<div className="bg-line h-80 animate-pulse rounded-lg" />}
      >
        <AuthFlow />
      </Suspense>
    </AuthShell>
  );
}
