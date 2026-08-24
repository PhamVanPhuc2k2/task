"use client";

import Link from "next/link";

import { ErrorState, ListSkeleton } from "@/components/ui/states";
import {
  useNotificationSettings,
  useUpdateNotificationSetting,
} from "@/features/notifications/api/notifications-api";
/**
 * Người dùng tự bật/tắt từng loại thông báo, theo từng kênh.
 *
 * Lưu ngay khi bấm, không có nút "Lưu": một bảng công tắc mà phải nhớ bấm lưu
 * là nơi người ta tưởng đã tắt rồi vẫn nhận thông báo, và mất niềm tin vào cả
 * trang này.
 */
export default function NotificationSettingsPage() {
  const { data, isPending, isError, error, refetch } =
    useNotificationSettings();
  const luu = useUpdateNotificationSetting();

  return (
    <div data-tone="all" className="enter mx-auto max-w-2xl space-y-6">
      <header>
        <Link
          href="/notifications"
          className="text-ink-faint hover:text-ink focus-frame inline-block rounded text-[0.82rem]"
        >
          ← Thông báo
        </Link>
        <h1 className="mt-2 text-[1.65rem] leading-tight font-semibold tracking-[-0.035em]">
          Cài đặt thông báo
        </h1>
        <p className="text-ink-soft mt-1.5 text-[0.9rem]">
          Chọn loại nào báo trong ứng dụng, loại nào gửi thêm email. Thay đổi
          được lưu ngay.
        </p>
      </header>

      {isPending && <ListSkeleton rows={5} />}

      {isError && <ErrorState error={error} onRetry={() => void refetch()} />}

      {data && (
        <>
          <ul className="border-line divide-line bg-paper-raised shadow-card divide-y overflow-hidden rounded-2xl border">
            {data.map((muc) => (
              <li key={muc.type} className="p-4 sm:p-5">
                <div className="flex flex-wrap items-start justify-between gap-4">
                  <div className="min-w-0 flex-1">
                    <p className="font-medium">{muc.label}</p>
                    <p className="text-ink-faint mt-0.5 text-[0.84rem] leading-relaxed">
                      {muc.description}
                    </p>
                  </div>

                  <div className="flex shrink-0 gap-4">
                    <CongTac
                      nhan="Ứng dụng"
                      bat={muc.in_app}
                      dangLuu={luu.isPending}
                      onDoi={(bat) =>
                        luu.mutate({
                          type: muc.type,
                          in_app: bat,
                          email: muc.email,
                        })
                      }
                    />
                    <CongTac
                      nhan="Email"
                      bat={muc.email}
                      dangLuu={luu.isPending}
                      onDoi={(bat) =>
                        luu.mutate({
                          type: muc.type,
                          in_app: muc.in_app,
                          email: bat,
                        })
                      }
                    />
                  </div>
                </div>
              </li>
            ))}
          </ul>

          {luu.error && (
            <p role="alert" className="text-danger text-[0.85rem]">
              {luu.error.message}
            </p>
          )}

          <p className="text-ink-faint text-[0.82rem] leading-relaxed">
            Thông báo qua Zalo và Telegram sẽ có ở đợt 2 — đó mới là kênh nhân
            viên thực sự đọc, nhưng cần thời gian xin cấu hình phía nhà cung
            cấp.
          </p>
        </>
      )}
    </div>
  );
}

function CongTac({
  nhan,
  bat,
  dangLuu,
  onDoi,
}: {
  nhan: string;
  bat: boolean;
  dangLuu: boolean;
  onDoi: (bat: boolean) => void;
}) {
  return (
    <label className="flex cursor-pointer flex-col items-center gap-1.5">
      <span className="text-ink-faint text-[0.72rem]">{nhan}</span>
      <input
        type="checkbox"
        checked={bat}
        disabled={dangLuu}
        onChange={(e) => onDoi(e.target.checked)}
        className="accent-accent size-4 cursor-pointer disabled:opacity-50"
      />
    </label>
  );
}
