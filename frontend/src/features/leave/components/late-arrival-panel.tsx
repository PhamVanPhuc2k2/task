"use client";

import { useState } from "react";

import { Button } from "@/components/ui/button";
import { TextArea } from "@/components/ui/field";
import { Avatar } from "@/components/ui/pill";
import { EmptyState, ErrorState, Skeleton } from "@/components/ui/states";
import { cn } from "@/lib/cn";
import { formatDate } from "@/lib/format";

import {
  useCancelLateArrival,
  useMyLateArrivals,
  useReviewLateArrival,
  useTeamLateArrivals,
} from "../api/late-arrival-api";
import type { LateArrivalItem } from "../types/late-arrival";
import { LEAVE_STATUS_TONE } from "../types/leave";
import { LateArrivalComposer } from "./late-arrival-composer";

/**
 * Toàn bộ phần "đi làm muộn": nộp đơn, đơn của tôi, hộp duyệt.
 *
 * Gói thành một component thay vì nhét thẳng vào trang: trang `/leave` đã dài
 * 350 dòng cho phần nghỉ phép, và hai luồng này tuy cùng chỗ nhưng không dùng
 * chung state nào. Tách ra thì mỗi phần đọc được độc lập.
 *
 * Dùng chung `LEAVE_STATUS_TONE` với đơn nghỉ, có chủ ý: hai loại đơn đi qua
 * đúng một vòng đời, nên "đang chờ" phải có cùng màu ở cả hai chỗ. Màu khác
 * nhau cho cùng một trạng thái là thứ khiến người dùng phải đọc chữ mới hiểu.
 */
export function LateArrivalPanel({ canApprove }: { canApprove: boolean }) {
  const cuaToi = useMyLateArrivals();
  const cuaDoi = useTeamLateArrivals(canApprove);

  return (
    <div className="space-y-8">
      {/* ── Nộp đơn ─────────────────────────────────── */}
      <section className="tone-card rounded-2xl p-5">
        <h2 className="mb-1 text-[0.95rem] font-semibold tracking-tight">
          Xin đi muộn
        </h2>
        <p className="text-ink-faint mb-4 text-[0.84rem]">
          Ngày đã duyệt sẽ không bị đánh dấu đi muộn trên bảng công — nhưng chỉ
          tới đúng giờ bạn xin.
        </p>

        {cuaToi.isPending && <Skeleton className="h-40" />}

        {cuaToi.isError && (
          <ErrorState
            error={cuaToi.error}
            onRetry={() => void cuaToi.refetch()}
          />
        )}

        {cuaToi.data && (
          <LateArrivalComposer
            window={cuaToi.data.window}
            shift={cuaToi.data.shift}
          />
        )}
      </section>

      {/* ── Đơn của tôi ─────────────────────────────── */}
      <section className="tone-card rounded-2xl p-5">
        <div className="mb-4 flex flex-wrap items-baseline justify-between gap-3">
          <h2 className="text-[0.95rem] font-semibold tracking-tight">
            Đơn của tôi
          </h2>

          {/* Không cắt im lặng: người có 120 đơn phải biết mình đang xem 100. */}
          {cuaToi.data && cuaToi.data.total > cuaToi.data.requests.length && (
            <p className="text-ink-faint text-[0.82rem]">
              Hiện {cuaToi.data.requests.length} đơn gần nhất trên tổng số{" "}
              {cuaToi.data.total}
            </p>
          )}
        </div>

        {cuaToi.data && cuaToi.data.requests.length === 0 && (
          <EmptyState
            title="Chưa có đơn nào"
            description="Đơn xin đi muộn của bạn sẽ hiện ở đây, kèm trạng thái duyệt."
          />
        )}

        {cuaToi.data && cuaToi.data.requests.length > 0 && (
          <ul className="divide-line divide-y">
            {cuaToi.data.requests.map((d) => (
              <DongDiMuon key={d.id} don={d} laCuaToi />
            ))}
          </ul>
        )}
      </section>

      {/* ── Hộp duyệt ───────────────────────────────── */}
      {canApprove && (
        <section className="tone-card rounded-2xl p-5">
          <h2 className="mb-4 text-[0.95rem] font-semibold tracking-tight">
            Đơn cần duyệt
            {cuaDoi.data && cuaDoi.data.pending > 0 && (
              <span className="border-notice-line bg-notice-surface text-notice ml-2 inline-flex min-w-5 justify-center rounded-full border px-1.5 py-0.5 text-[0.72rem] font-semibold">
                {cuaDoi.data.pending}
              </span>
            )}
          </h2>

          {cuaDoi.isPending && <Skeleton className="h-32" />}

          {cuaDoi.isError && (
            <ErrorState
              error={cuaDoi.error}
              onRetry={() => void cuaDoi.refetch()}
            />
          )}

          {cuaDoi.data && cuaDoi.data.requests.length === 0 && (
            <EmptyState
              title="Không có đơn nào"
              description="Đơn xin đi muộn của nhân sự trong phạm vi bạn quản lý sẽ hiện ở đây."
            />
          )}

          {cuaDoi.data && cuaDoi.data.requests.length > 0 && (
            <ul className="divide-line divide-y">
              {cuaDoi.data.requests.map((d) => (
                <DongDiMuon key={d.id} don={d} canReview={d.is_editable} />
              ))}
            </ul>
          )}
        </section>
      )}
    </div>
  );
}

function DongDiMuon({
  don,
  canReview = false,
  laCuaToi = false,
}: {
  don: LateArrivalItem;
  canReview?: boolean;
  laCuaToi?: boolean;
}) {
  const [moTuChoi, setMoTuChoi] = useState(false);
  const [ghiChu, setGhiChu] = useState("");

  const duyet = useReviewLateArrival();
  const rut = useCancelLateArrival();

  return (
    <li className="py-3.5 first:pt-0 last:pb-0">
      <div className="flex flex-wrap items-start gap-3">
        {don.user && <Avatar name={don.user.name} size="sm" />}

        <div className="min-w-0 flex-1">
          <div className="flex flex-wrap items-baseline gap-x-2 gap-y-1">
            {don.user && (
              <span className="text-[0.88rem] font-medium">
                {don.user.name}
                {don.user.department && (
                  <span className="text-ink-faint ml-1.5 text-[0.76rem] font-normal">
                    {don.user.department}
                  </span>
                )}
              </span>
            )}

            <span className="text-ink-soft text-[0.84rem]">
              {formatDate(don.date)}
              <span className="text-ink-faint">
                {" "}
                · đến lúc{" "}
                <strong className="text-ink font-semibold tabular-nums">
                  {don.expected_arrival}
                </strong>
              </span>
            </span>

            <span
              className={cn(
                "ml-auto shrink-0 rounded-full border px-2 py-0.5 text-[0.74rem] font-medium",
                LEAVE_STATUS_TONE[don.status],
              )}
            >
              {don.status_label}
            </span>
          </div>

          <p className="text-ink-soft mt-1.5 text-[0.86rem] leading-relaxed">
            {don.reason}
          </p>

          {don.review?.note && (
            <p className="border-line bg-paper-sunken text-ink-soft mt-2 rounded-lg border px-3 py-2 text-[0.84rem]">
              <strong className="text-ink font-semibold">
                {don.review.by ?? "Quản lý"}:
              </strong>{" "}
              {don.review.note}
            </p>
          )}

          {/* Người nộp tự rút được khi còn đang chờ. */}
          {laCuaToi && don.is_editable && !canReview && (
            <div className="mt-2.5">
              <Button
                size="sm"
                variant="ghost"
                loading={rut.isPending}
                onClick={() => rut.mutate(don.id)}
              >
                Rút đơn
              </Button>
            </div>
          )}

          {/*
            Không tự duyệt đơn của chính mình, kể cả khi có quyền. Backend cũng
            chặn — đây chỉ là để nút không hiện ra rồi bấm vào ăn 403.
          */}
          {canReview && !laCuaToi && (
            <div className="mt-3">
              {!moTuChoi ? (
                <div className="flex flex-wrap gap-2">
                  <Button
                    size="sm"
                    variant="primary"
                    loading={duyet.isPending}
                    onClick={() =>
                      duyet.mutate({ id: don.id, approve: true, note: "" })
                    }
                  >
                    Duyệt
                  </Button>
                  <Button
                    size="sm"
                    variant="ghost"
                    onClick={() => setMoTuChoi(true)}
                  >
                    Từ chối
                  </Button>
                </div>
              ) : (
                <div className="space-y-2">
                  <label
                    htmlFor={`ly-do-tu-choi-muon-${don.id}`}
                    className="text-ink-soft block text-[0.82rem] font-medium"
                  >
                    Lý do từ chối — người nộp cần biết vì sao
                  </label>
                  <TextArea
                    id={`ly-do-tu-choi-muon-${don.id}`}
                    rows={2}
                    value={ghiChu}
                    placeholder="Sáng đó có họp giao ban với khách, cần bạn có mặt đúng giờ."
                    onChange={(e) => setGhiChu(e.target.value)}
                  />

                  {duyet.error && (
                    <p role="alert" className="text-danger text-[0.84rem]">
                      {duyet.error.fieldError("note") ?? duyet.error.message}
                    </p>
                  )}

                  <div className="flex flex-wrap gap-2">
                    <Button
                      size="sm"
                      variant="primary"
                      loading={duyet.isPending}
                      disabled={ghiChu.trim().length < 5}
                      onClick={() =>
                        duyet.mutate({
                          id: don.id,
                          approve: false,
                          note: ghiChu.trim(),
                        })
                      }
                    >
                      Gửi từ chối
                    </Button>
                    <Button
                      size="sm"
                      variant="ghost"
                      onClick={() => setMoTuChoi(false)}
                    >
                      Huỷ
                    </Button>
                  </div>
                </div>
              )}
            </div>
          )}
        </div>
      </div>
    </li>
  );
}
