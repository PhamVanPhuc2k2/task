"use client";

import Link from "next/link";

import { cn } from "@/lib/cn";
import { formatTimeAgo } from "@/lib/format";
import { usePopover } from "@/lib/use-popover";

import {
  useMarkAllRead,
  useMarkNotificationRead,
  useNotifications,
  useUnreadCount,
} from "../api/notifications-api";
import { notificationTone } from "../types/notification";

/**
 * Chuông thông báo trên đầu trang.
 *
 * Chỉ nạp danh sách khi người dùng thật sự mở chuông; lúc đóng chỉ có một truy
 * vấn đếm chạy mỗi phút. Nạp sẵn cả danh sách cho mọi lần tải trang là trả giá
 * cho thứ phần lớn thời gian không ai mở.
 */
export function NotificationBell() {
  // Bấm ra ngoài, phím Esc và đóng/mở dùng chung với menu tài khoản — xem
  // `lib/use-popover.ts`.
  const { boc, dangMo, moDong, dong } = usePopover();

  const { data: chuaDoc = 0 } = useUnreadCount();
  const { data, isPending } = useNotifications(false);
  const danhDau = useMarkNotificationRead();
  const danhDauHet = useMarkAllRead();

  const danhSach = data?.data.slice(0, 8) ?? [];

  return (
    <div ref={boc} className="relative">
      <button
        type="button"
        onClick={moDong}
        aria-expanded={dangMo}
        aria-haspopup="menu"
        aria-label={
          chuaDoc > 0 ? `Thông báo, ${chuaDoc} chưa đọc` : "Thông báo"
        }
        className="focus-frame hover:bg-paper-raised relative rounded-lg p-2"
      >
        <BellIcon />

        {chuaDoc > 0 && (
          <span className="bg-accent text-on-accent absolute -top-0.5 -right-0.5 flex min-w-4 items-center justify-center rounded-full px-1 text-[0.62rem] font-bold">
            {chuaDoc > 99 ? "99+" : chuaDoc}
          </span>
        )}
      </button>

      {dangMo && (
        <div className="border-line bg-paper-raised shadow-pop absolute right-0 z-40 mt-2 w-[min(22rem,calc(100vw-2rem))] overflow-hidden rounded-2xl border">
          <div className="border-line flex items-center justify-between border-b px-4 py-2.5">
            <span className="text-[0.85rem] font-semibold">Thông báo</span>

            {chuaDoc > 0 && (
              <button
                type="button"
                onClick={() => danhDauHet.mutate()}
                className="text-ink-faint hover:text-ink focus-frame rounded text-[0.76rem]"
              >
                Đánh dấu đã đọc hết
              </button>
            )}
          </div>

          {isPending && (
            <p className="text-ink-faint px-4 py-6 text-center text-[0.84rem]">
              Đang tải…
            </p>
          )}

          {!isPending && danhSach.length === 0 && (
            <p className="text-ink-faint px-4 py-8 text-center text-[0.84rem]">
              Chưa có thông báo nào.
            </p>
          )}

          <ul className="max-h-[24rem] overflow-y-auto">
            {danhSach.map((tb) => (
              <li key={tb.id} className="border-line border-b last:border-0">
                <Link
                  href={tb.url ?? "/notifications"}
                  onClick={() => {
                    if (tb.read_at === null) danhDau.mutate(tb.id);
                    dong();
                  }}
                  className={cn(
                    "focus-frame hover:bg-paper-raised block px-4 py-3 transition-colors",
                    tb.read_at === null && "bg-paper-raised/60",
                  )}
                >
                  <span className="flex items-start gap-2.5">
                    <span
                      aria-hidden="true"
                      className={cn(
                        "mt-1.5 size-1.5 shrink-0 rounded-full",
                        tb.read_at === null
                          ? notificationTone(tb.kind)
                          : "bg-transparent",
                      )}
                    />
                    <span className="min-w-0">
                      <span className="block text-[0.85rem] font-medium">
                        {tb.title}
                      </span>
                      <span className="text-ink-soft mt-0.5 line-clamp-2 block text-[0.8rem] leading-snug">
                        {tb.message}
                      </span>
                      <span className="text-ink-faint mt-1 block text-[0.72rem]">
                        {formatTimeAgo(tb.created_at)}
                      </span>
                    </span>
                  </span>
                </Link>
              </li>
            ))}
          </ul>

          <div className="border-line border-t">
            <Link
              href="/notifications"
              onClick={dong}
              className="text-ink-soft hover:bg-paper-raised focus-frame block px-4 py-2.5 text-center text-[0.82rem]"
            >
              Xem tất cả
            </Link>
          </div>
        </div>
      )}
    </div>
  );
}

function BellIcon() {
  return (
    <svg
      viewBox="0 0 24 24"
      aria-hidden="true"
      className="size-5"
      fill="none"
      stroke="currentColor"
      strokeWidth="1.6"
      strokeLinecap="round"
      strokeLinejoin="round"
    >
      <path d="M18 8a6 6 0 1 0-12 0c0 7-3 9-3 9h18s-3-2-3-9M13.7 21a2 2 0 0 1-3.4 0" />
    </svg>
  );
}
