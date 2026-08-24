import Link from "next/link";
import type { ReactNode } from "react";

import { ExplusMark } from "./explus-mark";

/**
 * Khung nền dùng chung cho mọi trang không cần đăng nhập.
 *
 * Tách ra khi thêm hai trang mật khẩu. Chép lại bốn lớp nền, đầu trang và chân
 * trang sang từng trang thì lần đổi nhận diện tiếp theo phải sửa ba chỗ — và
 * chỗ bị quên luôn là trang ít người mở nhất, tức là đúng hai trang này.
 *
 * Cột giới thiệu bên trái chỉ trang đăng nhập có: người đang đi đặt lại mật
 * khẩu không cần nghe quảng cáo, họ cần làm xong việc rồi ra.
 */
export function AuthShell({
  children,
  aside,
  footerNote,
  belowCard,
}: {
  children: ReactNode;
  aside?: ReactNode;
  footerNote: string;
  belowCard?: ReactNode;
}) {
  return (
    <div className="stage-dark bg-paper text-ink relative min-h-screen overflow-hidden">
      {/* ── Nền nhiều lớp ────────────────────────────────── */}
      <div
        aria-hidden="true"
        className="pointer-events-none absolute inset-0 -z-30"
      >
        <div className="aurora absolute -inset-[20%]" />
      </div>
      <div
        aria-hidden="true"
        className="plus-field plus-field-fade pointer-events-none absolute inset-0 -z-20 opacity-70"
      />
      <div
        aria-hidden="true"
        className="grain pointer-events-none absolute inset-0 -z-10"
      />

      <main className="relative mx-auto flex min-h-screen w-full max-w-6xl flex-col px-5 py-8 sm:px-8 lg:px-10">
        {/* ── Đầu trang ─────────────────────────────────── */}
        <header className="rise-in flex items-center justify-between">
          <Link href="/login" className="focus-frame flex items-center gap-2.5">
            <span className="border-line-strong bg-paper-raised/60 grid h-9 w-9 place-items-center rounded-lg border backdrop-blur-sm">
              <ExplusMark className="text-accent h-4.5 w-4.5" />
            </span>
            <span className="text-[1.3rem] leading-none font-semibold tracking-tight">
              explus
            </span>
          </Link>

          <span className="text-ink-faint hidden items-center gap-2 font-mono text-[0.68rem] tracking-widest uppercase sm:flex">
            <span className="bg-accent inline-block h-1.5 w-1.5 rounded-full" />
            Hệ thống nội bộ
          </span>
        </header>

        {/* ── Thân trang ────────────────────────────────── */}
        <div
          className={
            aside === undefined
              ? "flex flex-1 items-center justify-center py-12"
              : "grid flex-1 items-center gap-14 py-12 lg:grid-cols-[1.05fr_minmax(0,26rem)] lg:gap-20"
          }
        >
          {aside}

          <section className="rise-in mx-auto w-full max-w-[26rem] lg:mx-0">
            <div className="glass-frame rounded-2xl shadow-[0_30px_80px_-30px_rgba(0,0,0,0.85)]">
              <div className="glass-panel rounded-[15px] px-6 py-8 sm:px-8 sm:py-9">
                {children}
              </div>
            </div>

            {belowCard}
          </section>
        </div>

        {/* ── Chân trang ────────────────────────────────── */}
        <footer className="text-ink-faint flex items-center gap-4 font-mono text-[0.68rem] tracking-widest uppercase">
          <span>© {new Date().getFullYear()} Explus</span>
          <span className="bg-line h-px flex-1" />
          <span>{footerNote}</span>
        </footer>
      </main>
    </div>
  );
}
