"use client";

import { useState } from "react";

import { Button } from "@/components/ui/button";
import { TextArea, TextInput } from "@/components/ui/field";
import { Avatar, REQUEST_STATUS_TONE } from "@/components/ui/pill";
import { EmptyState, ErrorState, Skeleton } from "@/components/ui/states";
import { cn } from "@/lib/cn";
import { formatDate } from "@/lib/format";

import {
  useCancelAdjustment,
  useMyAdjustments,
  useReviewAdjustment,
  useTeamAdjustments,
} from "../api/adjustment-api";
import { formatMinutes, type AdjustmentItem } from "../types/adjustment";
import { AdjustmentComposer } from "./adjustment-composer";

/**
 * Toàn bộ phần "giải trình công": nộp đơn, đơn của tôi, hộp duyệt.
 *
 * Trước phần này, bảng công chỉ có **một cửa vào**: người quản lý bấm nút. Nhân
 * viên đi gặp khách cả ngày thì nhắn Zalo, quản lý nhớ thì bấm, quên thì thôi —
 * và lý do thật của một ngày công bất thường nằm trong lịch sử chat của hai
 * người.
 *
 * Từ khi có chốt sổ kỳ công, chuyện đó có **hạn chót cứng**: kỳ chốt rồi thì
 * không ai duyệt được nữa, kể cả giám đốc.
 *
 * Dùng chung `REQUEST_STATUS_TONE` với đơn nghỉ và đơn đi muộn: ba loại đơn đi
 * qua đúng một vòng đời, nên "đang chờ" phải có cùng một màu ở cả ba chỗ.
 */
export function AdjustmentPanel({
  canReview,
  initialDate = "",
}: {
  canReview: boolean;
  /** Ngày điền sẵn khi người dùng bấm "Giải trình ngày này" từ bảng công. */
  initialDate?: string;
}) {
  const cuaToi = useMyAdjustments();
  const cuaDoi = useTeamAdjustments(canReview);

  return (
    <div className="space-y-8">
      {/* ── Nộp đơn ─────────────────────────────────── */}
      <section className="tone-card rounded-2xl p-5">
        <h2 className="mb-1 text-[0.95rem] font-semibold tracking-tight">
          Giải trình một ngày công
        </h2>
        <p className="text-ink-faint mb-4 text-[0.84rem]">
          Dùng khi hệ thống đo thiếu giờ vì bạn làm ngoài văn phòng, mất mạng,
          hay quên mở máy. Quản lý duyệt thì ngày đó thôi bị đánh dấu bất
          thường.
        </p>

        {cuaToi.isPending && <Skeleton className="h-40" />}

        {cuaToi.isError && (
          <ErrorState
            error={cuaToi.error}
            onRetry={() => void cuaToi.refetch()}
          />
        )}

        {cuaToi.data && (
          <AdjustmentComposer
            // Đổi ngày điền sẵn thì dựng lại form: người dùng vừa bấm "Giải
            // trình ngày này" cho một ngày KHÁC, và giữ nguyên state cũ nghĩa là
            // họ gửi nhầm ngày mà không nhận ra.
            key={initialDate}
            latestDate={cuaToi.data.latest_date}
            initialDate={initialDate}
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
            description="Đơn giải trình của bạn sẽ hiện ở đây, kèm con số quản lý đã chốt."
          />
        )}

        {cuaToi.data && cuaToi.data.requests.length > 0 && (
          <ul className="divide-line divide-y">
            {cuaToi.data.requests.map((d) => (
              <DongGiaiTrinh key={d.id} don={d} laCuaToi />
            ))}
          </ul>
        )}
      </section>

      {/* ── Hộp duyệt ───────────────────────────────── */}
      {canReview && (
        <section className="tone-card rounded-2xl p-5">
          <h2 className="mb-1 text-[0.95rem] font-semibold tracking-tight">
            Đơn cần duyệt
            {cuaDoi.data && cuaDoi.data.pending > 0 && (
              <span className="border-notice-line bg-notice-surface text-notice ml-2 inline-flex min-w-5 justify-center rounded-full border px-1.5 py-0.5 text-[0.72rem] font-semibold">
                {cuaDoi.data.pending}
              </span>
            )}
          </h2>
          <p className="text-ink-faint mb-4 text-[0.84rem]">
            Xử lý hết trước khi chốt sổ kỳ công — chốt rồi thì đơn còn treo sẽ
            không ai duyệt được nữa.
          </p>

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
              description="Đơn giải trình của nhân sự trong phạm vi bạn quản lý sẽ hiện ở đây."
            />
          )}

          {cuaDoi.data && cuaDoi.data.requests.length > 0 && (
            <ul className="divide-line divide-y">
              {cuaDoi.data.requests.map((d) => (
                <DongGiaiTrinh key={d.id} don={d} canReview={d.is_editable} />
              ))}
            </ul>
          )}
        </section>
      )}
    </div>
  );
}

function DongGiaiTrinh({
  don,
  canReview = false,
  laCuaToi = false,
}: {
  don: AdjustmentItem;
  canReview?: boolean;
  laCuaToi?: boolean;
}) {
  const [mo, setMo] = useState<null | "duyet" | "tuChoi">(null);

  // Điền sẵn con số người nộp xin, nhưng ĐÂY LÀ Ô SỬA ĐƯỢC: cái đi vào bảng
  // công là con số người duyệt gửi lên. Nếu không thì "duyệt" chỉ còn nghĩa là
  // đồng ý với mọi con số nhân viên tự khai.
  const [gio, setGio] = useState(
    don.requested_minutes === null ? "" : String(don.requested_minutes / 60),
  );
  const [ghiChu, setGhiChu] = useState("");

  const duyet = useReviewAdjustment();
  const rut = useCancelAdjustment();

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
              {formatDate(don.work_date)}
              <span className="text-ink-faint">
                {" · "}
                {don.requested_minutes === null ? (
                  "xin bỏ qua"
                ) : (
                  <>
                    đề nghị{" "}
                    <strong className="text-ink font-semibold tabular-nums">
                      {formatMinutes(don.requested_minutes)}
                    </strong>
                  </>
                )}
              </span>
            </span>

            <span
              className={cn(
                "ml-auto shrink-0 rounded-full border px-2 py-0.5 text-[0.74rem] font-medium",
                REQUEST_STATUS_TONE[don.status],
              )}
            >
              {don.status_label}
            </span>
          </div>

          <p className="text-ink-soft mt-1.5 text-[0.86rem] leading-relaxed">
            {don.reason}
          </p>

          {don.review && (
            <p className="border-line bg-paper-sunken text-ink-soft mt-2 rounded-lg border px-3 py-2 text-[0.84rem]">
              <strong className="text-ink font-semibold">
                {don.review.by ?? "Quản lý"}:
              </strong>{" "}
              {/*
                Nói ra CON SỐ ĐÃ CHỐT khi nó khác con số đã xin. Không nói thì
                người nộp phải tự mở bảng công đi so — và phần lớn sẽ không so.
              */}
              {don.status === "approved" &&
                (don.review.approved_minutes === null ? (
                  <>Bỏ qua ngày này, giữ nguyên số giờ hệ thống đo được. </>
                ) : (
                  <>
                    Ghi nhận{" "}
                    <strong className="text-ink font-semibold tabular-nums">
                      {formatMinutes(don.review.approved_minutes)}
                    </strong>
                    .{" "}
                  </>
                ))}
              {don.review.note}
            </p>
          )}

          {/* Người nộp tự rút được khi còn đang chờ. */}
          {laCuaToi && don.is_editable && !canReview && (
            <div className="mt-2.5">
              {rut.error && (
                <p role="alert" className="text-danger mb-2 text-[0.84rem]">
                  {rut.error.message}
                </p>
              )}
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
              {mo === null && (
                <div className="flex flex-wrap gap-2">
                  <Button
                    size="sm"
                    variant="primary"
                    onClick={() => setMo("duyet")}
                  >
                    Duyệt
                  </Button>
                  <Button
                    size="sm"
                    variant="ghost"
                    onClick={() => setMo("tuChoi")}
                  >
                    Từ chối
                  </Button>
                </div>
              )}

              {mo === "duyet" && (
                <div className="space-y-2">
                  <label
                    htmlFor={`gio-duyet-${don.id}`}
                    className="text-ink-soft block text-[0.82rem] font-medium"
                  >
                    Ấn định số giờ — để trống thì giữ nguyên số hệ thống đo được
                  </label>
                  <div className="max-w-40">
                    <TextInput
                      id={`gio-duyet-${don.id}`}
                      type="number"
                      min={0}
                      max={24}
                      step={0.5}
                      value={gio}
                      placeholder="8"
                      onChange={(e) => setGio(e.target.value)}
                    />
                  </div>

                  <TextArea
                    rows={2}
                    value={ghiChu}
                    aria-label="Ghi chú cho người nộp"
                    placeholder="Ghi chú cho người nộp (không bắt buộc)."
                    onChange={(e) => setGhiChu(e.target.value)}
                  />

                  {duyet.error && (
                    <p role="alert" className="text-danger text-[0.84rem]">
                      {duyet.error.fieldError("minutes") ??
                        duyet.error.fieldError("work_date") ??
                        duyet.error.message}
                    </p>
                  )}

                  <div className="flex flex-wrap gap-2">
                    <Button
                      size="sm"
                      variant="primary"
                      loading={duyet.isPending}
                      onClick={() =>
                        duyet.mutate({
                          id: don.id,
                          approve: true,
                          minutes:
                            gio === "" ? null : Math.round(Number(gio) * 60),
                          note: ghiChu.trim(),
                        })
                      }
                    >
                      Xác nhận duyệt
                    </Button>
                    <Button
                      size="sm"
                      variant="ghost"
                      onClick={() => setMo(null)}
                    >
                      Huỷ
                    </Button>
                  </div>
                </div>
              )}

              {mo === "tuChoi" && (
                <div className="space-y-2">
                  <label
                    htmlFor={`ly-do-tu-choi-giai-trinh-${don.id}`}
                    className="text-ink-soft block text-[0.82rem] font-medium"
                  >
                    Lý do từ chối — người nộp cần biết vì sao
                  </label>
                  <TextArea
                    id={`ly-do-tu-choi-giai-trinh-${don.id}`}
                    rows={2}
                    value={ghiChu}
                    placeholder="Hôm đó không có lịch gặp khách nào trên hệ thống."
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
                          minutes: null,
                          note: ghiChu.trim(),
                        })
                      }
                    >
                      Gửi từ chối
                    </Button>
                    <Button
                      size="sm"
                      variant="ghost"
                      onClick={() => setMo(null)}
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
