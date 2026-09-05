"use client";

import { useState } from "react";

import { Button } from "@/components/ui/button";
import { TextArea, TextInput } from "@/components/ui/field";
import { Avatar, REQUEST_STATUS_TONE } from "@/components/ui/pill";
import { EmptyState, ErrorState, Skeleton } from "@/components/ui/states";
import { cn } from "@/lib/cn";
import { formatDate } from "@/lib/format";

import {
  useCancelOvertime,
  useMyOvertime,
  useReviewOvertime,
  useTeamOvertime,
} from "../api/overtime-api";
import {
  DAY_KIND_TONE,
  formatMinutes,
  type MyOvertime,
  type OvertimeItem,
} from "../types/overtime";
import { OvertimeComposer } from "./overtime-composer";

/**
 * Toàn bộ phần "làm thêm giờ": đăng ký, đơn của tôi, hộp duyệt.
 *
 * Làm thêm giờ ra tiền ở mức 150–300% (Điều 98 Bộ luật Lao động 2019), nên nó
 * là một ĐƠN chứ không phải một con số suy ra từ giờ ngồi trước máy: **duyệt
 * trước mới được tính**.
 */
export function OvertimePanel({ canReview }: { canReview: boolean }) {
  const cuaToi = useMyOvertime();
  const cuaDoi = useTeamOvertime(canReview);

  return (
    <div className="space-y-8">
      {/* ── Đăng ký ─────────────────────────────────── */}
      <section className="tone-card rounded-2xl p-5">
        <h2 className="mb-1 text-[0.95rem] font-semibold tracking-tight">
          Đăng ký làm thêm giờ
        </h2>
        <p className="text-ink-faint mb-4 text-[0.84rem]">
          Đăng ký trước, quản lý duyệt rồi mới được tính. Hệ số phụ thuộc ngày
          hôm đó là ngày làm việc, ngày nghỉ hay ngày lễ.
        </p>

        {cuaToi.isPending && <Skeleton className="h-40" />}

        {cuaToi.isError && (
          <ErrorState
            error={cuaToi.error}
            onRetry={() => void cuaToi.refetch()}
          />
        )}

        {cuaToi.data && (
          <>
            <HanMuc data={cuaToi.data} />
            <OvertimeComposer data={cuaToi.data} />
          </>
        )}
      </section>

      {/* ── Đơn của tôi ─────────────────────────────── */}
      <section className="tone-card rounded-2xl p-5">
        <div className="mb-4 flex flex-wrap items-baseline justify-between gap-3">
          <h2 className="text-[0.95rem] font-semibold tracking-tight">
            Đăng ký của tôi
          </h2>

          {cuaToi.data && cuaToi.data.total > cuaToi.data.requests.length && (
            <p className="text-ink-faint text-[0.82rem]">
              Hiện {cuaToi.data.requests.length} đơn gần nhất trên tổng số{" "}
              {cuaToi.data.total}
            </p>
          )}
        </div>

        {cuaToi.data && cuaToi.data.requests.length === 0 && (
          <EmptyState
            title="Chưa có đăng ký nào"
            description="Đăng ký làm thêm giờ của bạn sẽ hiện ở đây, kèm hệ số đã được duyệt."
          />
        )}

        {cuaToi.data && cuaToi.data.requests.length > 0 && (
          <ul className="divide-line divide-y">
            {cuaToi.data.requests.map((d) => (
              <DongOT key={d.id} don={d} laCuaToi />
            ))}
          </ul>
        )}
      </section>

      {/* ── Hộp duyệt ───────────────────────────────── */}
      {canReview && (
        <section className="tone-card rounded-2xl p-5">
          <h2 className="mb-1 text-[0.95rem] font-semibold tracking-tight">
            Đăng ký cần duyệt
            {cuaDoi.data && cuaDoi.data.pending > 0 && (
              <span className="border-notice-line bg-notice-surface text-notice ml-2 inline-flex min-w-5 justify-center rounded-full border px-1.5 py-0.5 text-[0.72rem] font-semibold">
                {cuaDoi.data.pending}
              </span>
            )}
          </h2>
          <p className="text-ink-faint mb-4 text-[0.84rem]">
            Duyệt sớm: người ta đăng ký cho tối nay, và một câu trả lời tới muộn
            nghĩa là họ ở lại làm mà chưa ai đồng ý.
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
              title="Không có đăng ký nào"
              description="Đăng ký làm thêm của nhân sự trong phạm vi bạn quản lý sẽ hiện ở đây."
            />
          )}

          {cuaDoi.data && cuaDoi.data.requests.length > 0 && (
            <ul className="divide-line divide-y">
              {cuaDoi.data.requests.map((d) => (
                <DongOT key={d.id} don={d} canReview={d.is_editable} />
              ))}
            </ul>
          )}
        </section>
      )}
    </div>
  );
}

/**
 * Ba trần của Điều 107 và số đã dùng.
 *
 * Người nộp không có cách nào tự biết mình đã dùng bao nhiêu. Không nói ra thì
 * họ gõ xong cả cái đơn rồi mới nhận một câu từ chối, và lần sau vẫn không
 * đoán được.
 */
function HanMuc({ data }: { data: MyOvertime }) {
  const { policy, used } = data;

  return (
    <div className="border-line bg-paper-sunken mb-4 rounded-xl border px-3.5 py-2.5 text-[0.84rem]">
      <p className="text-ink-soft">
        {policy.max_minutes_per_month > 0 && (
          <>
            Tháng này{" "}
            <Muc dung={used.month} tran={policy.max_minutes_per_month} />
          </>
        )}
        {policy.max_minutes_per_month > 0 &&
          policy.max_minutes_per_year > 0 && (
            <span className="text-ink-faint"> · </span>
          )}
        {policy.max_minutes_per_year > 0 && (
          <>
            Năm nay <Muc dung={used.year} tran={policy.max_minutes_per_year} />
          </>
        )}
      </p>

      <p className="text-ink-faint mt-1 text-[0.78rem]">
        Trần theo Bộ luật Lao động, gồm cả đơn đang chờ duyệt. Hệ số:{" "}
        {policy.rate_working_percent}% ngày thường ·{" "}
        {policy.rate_weekly_rest_percent}% ngày nghỉ ·{" "}
        {policy.rate_holiday_percent}% ngày lễ.
      </p>
    </div>
  );
}

function Muc({ dung, tran }: { dung: number; tran: number }) {
  return (
    <strong
      className={
        dung >= tran
          ? "text-danger font-semibold tabular-nums"
          : "text-ink font-semibold tabular-nums"
      }
    >
      {formatMinutes(dung)}/{formatMinutes(tran)}
    </strong>
  );
}

function DongOT({
  don,
  canReview = false,
  laCuaToi = false,
}: {
  don: OvertimeItem;
  canReview?: boolean;
  laCuaToi?: boolean;
}) {
  const [mo, setMo] = useState<null | "duyet" | "tuChoi">(null);

  // Điền sẵn số đã đăng ký, nhưng ĐÂY LÀ Ô SỬA ĐƯỢC: "đăng ký 3 tiếng, thực tế
  // làm 2" là chuyện thường. Không gửi được nhiều hơn — server chặn, vì cho
  // phép thế là mở đường vòng qua ba cái trần đã kiểm lúc nộp.
  const [phut, setPhut] = useState(String(don.minutes));
  const [ghiChu, setGhiChu] = useState("");

  const duyet = useReviewOvertime();
  const rut = useCancelOvertime();

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
                <strong className="text-ink font-semibold tabular-nums">
                  {don.start_time}–{don.end_time}
                </strong>{" "}
                ({formatMinutes(don.minutes)})
              </span>
            </span>

            <span
              className={cn(
                "rounded-full border px-2 py-0.5 text-[0.74rem] font-medium",
                DAY_KIND_TONE[don.day_kind],
              )}
            >
              {don.rate_percent}%
              {/* Chưa duyệt thì hệ số còn đổi được — nói ra chứ đừng để người
                  ta đinh ninh con số đã chắc. */}
              {!don.rate_is_final && " dự kiến"}
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
              {/* Nói ra con số ĐÃ DUYỆT khi nó khác số đã đăng ký. Không nói
                  thì người nộp phải tự đi so, và phần lớn sẽ không so. */}
              {don.status === "approved" &&
                don.review.approved_minutes !== null && (
                  <>
                    Duyệt{" "}
                    <strong className="text-ink font-semibold tabular-nums">
                      {formatMinutes(don.review.approved_minutes)}
                    </strong>{" "}
                    ở hệ số {don.rate_percent}%.{" "}
                  </>
                )}
              {don.review.note}
            </p>
          )}

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
                Rút đăng ký
              </Button>
            </div>
          )}

          {/* Không tự duyệt đơn của chính mình, kể cả khi có quyền. Backend
              cũng chặn — đây chỉ để nút không hiện ra rồi bấm vào ăn 403. */}
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
                    htmlFor={`phut-duyet-${don.id}`}
                    className="text-ink-soft block text-[0.82rem] font-medium"
                  >
                    Số phút duyệt — tối đa {don.minutes} phút đã đăng ký
                  </label>
                  <div className="max-w-40">
                    <TextInput
                      id={`phut-duyet-${don.id}`}
                      type="number"
                      min={1}
                      max={don.minutes}
                      step={15}
                      value={phut}
                      onChange={(e) => setPhut(e.target.value)}
                    />
                  </div>

                  <TextArea
                    rows={2}
                    value={ghiChu}
                    aria-label="Ghi chú cho người đăng ký"
                    placeholder="Ghi chú cho người đăng ký (không bắt buộc)."
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
                          minutes: phut === "" ? null : Number(phut),
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
                    htmlFor={`ly-do-tu-choi-ot-${don.id}`}
                    className="text-ink-soft block text-[0.82rem] font-medium"
                  >
                    Lý do từ chối — người đăng ký cần biết trước khi ở lại làm
                  </label>
                  <TextArea
                    id={`ly-do-tu-choi-ot-${don.id}`}
                    rows={2}
                    value={ghiChu}
                    placeholder="Việc này không gấp, để sáng mai làm trong giờ."
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
