"use client";

import { useState } from "react";

import { Button } from "@/components/ui/button";
import { SelectInput, TextArea } from "@/components/ui/field";
import { Avatar } from "@/components/ui/pill";
import { EmptyState, ErrorState, Skeleton } from "@/components/ui/states";
import { useCurrentUser } from "@/features/auth/api/auth-api";
import {
  useCancelLeave,
  useMyLeave,
  useReviewLeave,
  useTeamLeave,
} from "@/features/leave/api/leave-api";
import { LateArrivalPanel } from "@/features/leave/components/late-arrival-panel";
import { LeaveComposer } from "@/features/leave/components/leave-composer";
import {
  LEAVE_STATUS_TONE,
  type LeaveRequestItem,
  type LeaveStatusValue,
} from "@/features/leave/types/leave";
import { cn } from "@/lib/cn";
import { formatDate } from "@/lib/format";

/**
 * Nghỉ phép.
 *
 * Hai tầng trong một trang, cùng khuôn với Chấm công / Báo cáo / Lương: đơn của
 * chính mình (ai cũng thấy) và hộp duyệt của quản lý (chỉ người có quyền). Quản
 * lý cũng là người phải xin nghỉ.
 *
 * **Phạm vi cố ý hẹp**: không có quỹ phép, không có số ngày còn lại, không nghỉ
 * nửa ngày. Ba thứ đó cần công ty chốt chính sách trước. Thứ làm ở đây là mảnh
 * gỡ được việc bấm tay hằng ngày — nhân viên khai, quản lý duyệt một lần, và
 * bảng công thôi hiện ô trống không rõ nguyên nhân.
 */
export default function LeavePage() {
  const { data: user } = useCurrentUser();

  const [tab, setTab] = useState<LeaveStatusValue>("pending");

  /*
  | Hai loại đơn, một trang.
  |
  | Nghỉ phép và đi muộn là cùng một việc trong đầu người dùng — "tôi cần xin
  | phép vắng mặt" — do cùng một người duyệt, với cùng một quyền. Tách thành
  | hai mục điều hướng riêng thì thanh bên dài thêm cho một thứ mà người ta tìm
  | ở cùng chỗ.
  */
  const [che, setChe] = useState<"nghi" | "muon">("nghi");

  const duyetDuoc =
    user?.permissions.some(
      (p) => p === "leave.view.team" || p === "leave.view.all",
    ) === true;

  const cuaToi = useMyLeave();
  // Không nạp hộp duyệt nghỉ phép khi đang xem tab đi muộn — đó là một
  // lượt gọi cho dữ liệu không hiện ra.
  const cuaDoi = useTeamLeave(tab, duyetDuoc && che === "nghi");

  return (
    <div data-tone="time" className="enter space-y-8">
      <header>
        <span
          aria-hidden="true"
          className="bg-tone mb-3 block h-[3px] w-9 rounded-full"
        />
        <h1 className="text-[1.65rem] leading-tight font-semibold tracking-[-0.035em]">
          Nghỉ phép
        </h1>
        <p className="text-ink-soft mt-1.5 max-w-2xl text-[0.9rem]">
          Xin phép ở đây thay vì nhắn tin. Ngày đã duyệt sẽ được miễn chấm công
          — bảng công hiện lý do thay vì một ô trống không rõ nguyên nhân.
        </p>
      </header>

      <ChonChe che={che} onChange={setChe} />

      {che === "muon" && <LateArrivalPanel canApprove={duyetDuoc} />}

      {che === "nghi" && (
        <div className="space-y-8">
          {/* ── Nộp đơn ─────────────────────────────────── */}
          <section className="tone-card rounded-2xl p-5">
            <h2 className="mb-4 text-[0.95rem] font-semibold tracking-tight">
              Xin nghỉ
            </h2>

            {cuaToi.isPending && <Skeleton className="h-40" />}

            {cuaToi.isError && (
              <ErrorState
                error={cuaToi.error}
                onRetry={() => void cuaToi.refetch()}
              />
            )}

            {cuaToi.data && (
              <LeaveComposer
                types={cuaToi.data.types}
                window={cuaToi.data.window}
              />
            )}
          </section>

          {/* ── Đơn của tôi ─────────────────────────────── */}
          <section className="tone-card rounded-2xl p-5">
            <div className="mb-4 flex flex-wrap items-baseline justify-between gap-3">
              <h2 className="text-[0.95rem] font-semibold tracking-tight">
                Đơn của tôi
              </h2>

              {cuaToi.data &&
                cuaToi.data.total > cuaToi.data.requests.length && (
                  <p className="text-ink-faint text-[0.82rem]">
                    Hiện {cuaToi.data.requests.length} đơn gần nhất trên tổng số{" "}
                    {cuaToi.data.total}
                  </p>
                )}
            </div>

            {cuaToi.data && cuaToi.data.requests.length === 0 && (
              <EmptyState
                title="Chưa có đơn nào"
                description="Đơn xin nghỉ của bạn sẽ hiện ở đây, kèm trạng thái duyệt."
              />
            )}

            {cuaToi.data && cuaToi.data.requests.length > 0 && (
              <ul className="divide-line divide-y">
                {cuaToi.data.requests.map((d) => (
                  <DongDon key={d.id} don={d} laCuaToi />
                ))}
              </ul>
            )}
          </section>

          {/* ── Hộp duyệt ───────────────────────────────── */}
          {duyetDuoc && (
            <section className="tone-card rounded-2xl p-5">
              <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                <h2 className="text-[0.95rem] font-semibold tracking-tight">
                  Đơn cần duyệt
                  {cuaDoi.data && cuaDoi.data.pending_count > 0 && (
                    <span className="border-notice-line bg-notice-surface text-notice ml-2 inline-flex min-w-5 justify-center rounded-full border px-1.5 py-0.5 text-[0.72rem] font-semibold">
                      {cuaDoi.data.pending_count}
                    </span>
                  )}
                </h2>

                <div className="w-44">
                  <label htmlFor="loc-trang-thai-nghi" className="sr-only">
                    Lọc theo trạng thái
                  </label>
                  <SelectInput
                    id="loc-trang-thai-nghi"
                    value={tab}
                    onChange={(e) => setTab(e.target.value as LeaveStatusValue)}
                  >
                    <option value="pending">Đang chờ</option>
                    <option value="approved">Đã duyệt</option>
                    <option value="rejected">Đã từ chối</option>
                    <option value="cancelled">Đã rút</option>
                  </SelectInput>
                </div>
              </div>

              {cuaDoi.isPending && <Skeleton className="h-32" />}

              {cuaDoi.isError && (
                <ErrorState
                  error={cuaDoi.error}
                  onRetry={() => void cuaDoi.refetch()}
                />
              )}

              {cuaDoi.data && cuaDoi.data.requests.length === 0 && (
                <EmptyState
                  title={
                    tab === "pending"
                      ? "Không có đơn nào đang chờ"
                      : "Không có đơn nào ở trạng thái này"
                  }
                  description="Đơn của nhân sự trong phạm vi bạn quản lý sẽ hiện ở đây."
                />
              )}

              {cuaDoi.data && cuaDoi.data.requests.length > 0 && (
                <ul className="divide-line divide-y">
                  {cuaDoi.data.requests.map((d) => (
                    <DongDon
                      key={d.id}
                      don={d}
                      canReview={cuaDoi.data.can_approve && d.is_editable}
                      laCuaToi={d.user?.id === user?.id}
                    />
                  ))}
                </ul>
              )}
            </section>
          )}
        </div>
      )}
    </div>
  );
}

/**
 * Công tắc hai chế độ.
 *
 * Dùng `radiogroup` chứ không phải hai nút thường: người dùng bàn phím và trình
 * đọc màn hình phải hiểu đây là **một lựa chọn giữa hai thứ loại trừ nhau**,
 * không phải hai hành động rời rạc.
 */
function ChonChe({
  che,
  onChange,
}: {
  che: "nghi" | "muon";
  onChange: (v: "nghi" | "muon") => void;
}) {
  const muc: { v: "nghi" | "muon"; nhan: string }[] = [
    { v: "nghi", nhan: "Nghỉ phép" },
    { v: "muon", nhan: "Đi muộn" },
  ];

  return (
    <div
      role="radiogroup"
      aria-label="Loại đơn"
      className="border-line bg-paper-sunken inline-flex gap-0.5 rounded-xl border p-0.5"
    >
      {muc.map((m) => (
        <button
          key={m.v}
          type="button"
          role="radio"
          aria-checked={che === m.v}
          onClick={() => onChange(m.v)}
          className={cn(
            "focus-frame rounded-lg px-4 py-1.5 text-[0.86rem] font-medium transition-colors",
            che === m.v
              ? "bg-paper-raised text-ink shadow-card"
              : "text-ink-faint hover:text-ink-soft",
          )}
        >
          {m.nhan}
        </button>
      ))}
    </div>
  );
}

/*
|--------------------------------------------------------------------------
| Một dòng đơn
|--------------------------------------------------------------------------
*/

function DongDon({
  don,
  canReview = false,
  laCuaToi = false,
}: {
  don: LeaveRequestItem;
  canReview?: boolean;
  laCuaToi?: boolean;
}) {
  const [moDuyet, setMoDuyet] = useState(false);
  const [ghiChu, setGhiChu] = useState("");

  const duyet = useReviewLeave();
  const rut = useCancelLeave();

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
              {don.type_label} · {formatDate(don.start_date)}
              {don.end_date !== don.start_date &&
                ` – ${formatDate(don.end_date)}`}
              <span className="text-ink-faint"> · {don.days} ngày</span>
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
              {!moDuyet ? (
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
                    onClick={() => setMoDuyet(true)}
                  >
                    Từ chối
                  </Button>
                </div>
              ) : (
                <div className="space-y-2">
                  <label
                    htmlFor={`ly-do-tu-choi-${don.id}`}
                    className="text-ink-soft block text-[0.82rem] font-medium"
                  >
                    Lý do từ chối — người nộp cần biết vì sao
                  </label>
                  <TextArea
                    id={`ly-do-tu-choi-${don.id}`}
                    rows={2}
                    value={ghiChu}
                    placeholder="Tuần đó cả nhóm phải chạy kịp bàn giao cho khách."
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
                      onClick={() => setMoDuyet(false)}
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
