"use client";

import Link from "next/link";
import { useState } from "react";

import { Button } from "@/components/ui/button";
import { buttonClass } from "@/components/ui/button";
import { Pagination } from "@/components/ui/pagination";
import { EmptyState, ErrorState, ListSkeleton } from "@/components/ui/states";
import {
  useMarkAllRead,
  useMarkNotificationRead,
  useNotifications,
} from "@/features/notifications/api/notifications-api";
import { notificationTone } from "@/features/notifications/types/notification";
import { cn } from "@/lib/cn";
import { formatDateTime, formatTimeAgo } from "@/lib/format";

/**
 * Trung tâm thông báo.
 *
 * Bộ lọc chỉ có hai lựa chọn — tất cả hoặc chưa đọc. Thêm lọc theo loại nghe
 * có vẻ hữu ích nhưng thực tế người ta vào đây để dọn hàng đợi, không để tra
 * cứu; ai cần tra cứu thì mở thẳng task.
 */
export default function NotificationsPage() {
  const [chiChuaDoc, setChiChuaDoc] = useState(false);
  const [trang, setTrang] = useState(1);

  const { data, isPending, isError, error, refetch } = useNotifications(
    chiChuaDoc,
    trang,
  );
  const danhDau = useMarkNotificationRead();
  const danhDauHet = useMarkAllRead();

  const danhSach = data?.data ?? [];

  return (
    <div data-tone="all" className="enter mx-auto max-w-3xl space-y-6">
      <header className="flex flex-wrap items-end justify-between gap-4">
        <div>
          <span
            aria-hidden="true"
            className="bg-tone mb-3 block h-[3px] w-9 rounded-full"
          />
          <h1 className="text-[1.65rem] leading-tight font-semibold tracking-[-0.035em]">
            Thông báo
          </h1>
          <p className="text-ink-soft mt-1.5 text-[0.9rem]">
            Việc được giao, deadline sắp tới và trao đổi liên quan tới bạn.
          </p>
        </div>

        <Link
          href="/settings/notifications"
          className={buttonClass("secondary", "sm")}
        >
          Cài đặt thông báo
        </Link>
      </header>

      <div className="flex flex-wrap items-center gap-2.5">
        <Button
          size="sm"
          variant={chiChuaDoc ? "ghost" : "secondary"}
          onClick={() => {
            setChiChuaDoc(false);
            setTrang(1);
          }}
        >
          Tất cả
        </Button>
        <Button
          size="sm"
          variant={chiChuaDoc ? "secondary" : "ghost"}
          onClick={() => {
            setChiChuaDoc(true);
            setTrang(1);
          }}
        >
          Chưa đọc
        </Button>

        <Button
          size="sm"
          variant="ghost"
          className="ml-auto"
          loading={danhDauHet.isPending}
          onClick={() => danhDauHet.mutate()}
        >
          Đánh dấu đã đọc hết
        </Button>
      </div>

      {isPending && <ListSkeleton rows={6} />}

      {isError && <ErrorState error={error} onRetry={() => void refetch()} />}

      {data && danhSach.length === 0 && (
        <EmptyState
          title={chiChuaDoc ? "Không còn gì chưa đọc" : "Chưa có thông báo nào"}
          description={
            chiChuaDoc
              ? "Bạn đã đọc hết thông báo của mình."
              : "Khi có người giao việc hoặc nhắc tên bạn, thông báo sẽ hiện ở đây."
          }
        />
      )}

      {data && danhSach.length > 0 && (
        <>
          <ul className="border-line divide-line bg-paper-raised shadow-card divide-y overflow-hidden rounded-2xl border">
            {danhSach.map((tb) => (
              <li key={tb.id}>
                <Link
                  href={tb.url ?? "#"}
                  onClick={() => {
                    if (tb.read_at === null) danhDau.mutate(tb.id);
                  }}
                  className={cn(
                    "focus-frame hover:bg-paper-raised flex items-start gap-3 px-4 py-3.5 transition-colors",
                    tb.read_at === null && "bg-paper-raised/50",
                  )}
                >
                  <span
                    aria-hidden="true"
                    className={cn(
                      "mt-2 size-2 shrink-0 rounded-full",
                      tb.read_at === null
                        ? notificationTone(tb.kind)
                        : "bg-transparent",
                    )}
                  />

                  <span className="min-w-0 flex-1">
                    <span className="block font-medium">{tb.title}</span>
                    <span className="text-ink-soft mt-0.5 block text-[0.88rem] leading-relaxed">
                      {tb.message}
                    </span>
                    <span
                      className="text-ink-faint mt-1 block text-[0.75rem]"
                      title={formatDateTime(tb.created_at)}
                    >
                      {formatTimeAgo(tb.created_at)}
                      {tb.read_at === null && " · chưa đọc"}
                    </span>
                  </span>
                </Link>
              </li>
            ))}
          </ul>

          <Pagination
            page={data.meta.current_page}
            lastPage={data.meta.last_page}
            total={data.meta.total}
            from={data.meta.from}
            to={data.meta.to}
            onChange={setTrang}
          />
        </>
      )}
    </div>
  );
}
