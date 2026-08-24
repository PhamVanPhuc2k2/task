"use client";

import { useState } from "react";

import { Button } from "@/components/ui/button";
import { Dialog } from "@/components/ui/dialog";
import { Field, SelectInput, TextArea, TextInput } from "@/components/ui/field";
import { formatDate } from "@/lib/format";

import { useReviewWorkDay, useWorkDay } from "../api/attendance-api";
import {
  DECISIONS,
  formatMinutes,
  type AttendanceCell,
  type AttendanceDecisionValue,
} from "../types/attendance";

/**
 * Xem chi tiết một ngày công và ra quyết định.
 *
 * Hộp thoại này là nơi cả chính sách "nhìn cho biết, duyệt tuỳ hoàn cảnh"
 * thành hình. Nó cố ý hiện **số giờ cạnh số lần đụng vào công việc**: sáu tiếng
 * online mà không động vào việc nào thì nhìn phát ra ngay, còn hai tiếng online
 * mà làm xong bốn việc thì cũng thấy — đó là thứ con số giờ đơn độc không bao
 * giờ nói được.
 */
export function ReviewDialog({
  open,
  onClose,
  userId,
  userName,
  date,
  cell,
  canReview,
}: {
  open: boolean;
  onClose: () => void;
  userId: string;
  userName: string;
  date: string;
  cell: AttendanceCell | undefined;
  canReview: boolean;
}) {
  const { data, isPending } = useWorkDay(userId, date, open);
  const duyet = useReviewWorkDay();

  const [quyetDinh, setQuyetDinh] = useState<AttendanceDecisionValue>(
    cell?.decision ?? "confirmed",
  );
  const [lyDo, setLyDo] = useState(cell?.reason ?? "");
  const [gioAnDinh, setGioAnDinh] = useState("");

  const moTa = DECISIONS.find((d) => d.value === quyetDinh)?.description;

  function gui() {
    duyet.mutate(
      {
        userId,
        work_date: date,
        decision: quyetDinh,
        reason: lyDo.trim(),
        adjusted_minutes:
          quyetDinh === "waived" && gioAnDinh !== ""
            ? Math.round(Number(gioAnDinh) * 60)
            : null,
      },
      { onSuccess: onClose },
    );
  }

  return (
    <Dialog
      open={open}
      onClose={onClose}
      title={`${userName} — ${formatDate(date)}`}
    >
      <div className="space-y-5">
        <div className="grid grid-cols-3 gap-3">
          <ThongSo nhan="Hệ thống đo" gia={formatMinutes(cell?.minutes ?? 0)} />
          <ThongSo nhan="Số phiên" gia={String(cell?.session_count ?? 0)} />
          {/* Đây là con số quan trọng nhất trong ba cái, và là lý do màn này
              tốt hơn một bảng giờ đơn thuần. */}
          <ThongSo
            nhan="Lần đụng việc"
            gia={isPending ? "…" : String(data?.task_activity_count ?? 0)}
          />
        </div>

        <section>
          <h3 className="text-ink-soft mb-2 text-[0.82rem] font-medium">
            Các phiên làm việc
          </h3>

          {isPending ? (
            <p className="text-ink-faint text-[0.84rem]">Đang tải…</p>
          ) : data && data.sessions.length > 0 ? (
            <ul className="divide-line border-line divide-y rounded-xl border">
              {data.sessions.map((p) => (
                <li
                  key={p.started_at}
                  className="flex items-center justify-between px-3 py-2 text-[0.84rem]"
                >
                  <span className="tabular-nums">
                    {gioPhut(p.started_at)} – {gioPhut(p.ended_at)}
                  </span>
                  <span className="text-ink-faint tabular-nums">
                    {formatMinutes(p.minutes)}
                  </span>
                </li>
              ))}
            </ul>
          ) : (
            <p className="text-ink-faint text-[0.84rem]">
              Không có phiên nào — người này không thao tác trên hệ thống trong
              ngày.
            </p>
          )}
        </section>

        {cell?.decision && (
          <p className="border-notice-line bg-notice-surface text-notice rounded-xl border px-3.5 py-2.5 text-[0.84rem]">
            <strong className="font-semibold">{cell.decision_label}</strong>
            {cell.reason && ` — ${cell.reason}`}
          </p>
        )}

        {canReview && (
          <div className="border-line space-y-4 border-t pt-4">
            <Field label="Quyết định" hint={moTa}>
              {(id, describedBy) => (
                <SelectInput
                  id={id}
                  aria-describedby={describedBy}
                  value={quyetDinh}
                  onChange={(e) =>
                    setQuyetDinh(e.target.value as AttendanceDecisionValue)
                  }
                >
                  {DECISIONS.map((d) => (
                    <option key={d.value} value={d.value}>
                      {d.label}
                    </option>
                  ))}
                </SelectInput>
              )}
            </Field>

            {quyetDinh === "waived" && (
              <Field
                label="Ấn định số giờ"
                hint="Để trống thì giữ nguyên số hệ thống đo được."
                error={duyet.error?.fieldError("adjusted_minutes")}
              >
                {(id, describedBy) => (
                  <TextInput
                    id={id}
                    type="number"
                    min={0}
                    max={24}
                    step={0.5}
                    aria-describedby={describedBy}
                    value={gioAnDinh}
                    placeholder="8"
                    onChange={(e) => setGioAnDinh(e.target.value)}
                  />
                )}
              </Field>
            )}

            <Field
              label="Lý do"
              required
              hint="Sáu tháng sau sẽ có người hỏi vì sao ngày này được xử lý như vậy."
              error={duyet.error?.fieldError("reason")}
            >
              {(id, describedBy) => (
                <TextArea
                  id={id}
                  rows={2}
                  aria-describedby={describedBy}
                  value={lyDo}
                  onChange={(e) => setLyDo(e.target.value)}
                  placeholder="Họp với khách hàng cả ngày, không dùng hệ thống."
                />
              )}
            </Field>

            {duyet.error && !duyet.error.errors && (
              <p role="alert" className="text-danger text-[0.84rem]">
                {duyet.error.message}
              </p>
            )}

            <div className="flex gap-3">
              <Button
                variant="primary"
                loading={duyet.isPending}
                disabled={lyDo.trim().length < 5}
                onClick={gui}
              >
                Lưu quyết định
              </Button>
              <Button variant="ghost" onClick={onClose}>
                Đóng
              </Button>
            </div>
          </div>
        )}

        {!canReview && (
          <div className="border-line border-t pt-4">
            <Button variant="ghost" onClick={onClose}>
              Đóng
            </Button>
          </div>
        )}
      </div>
    </Dialog>
  );
}

function ThongSo({ nhan, gia }: { nhan: string; gia: string }) {
  return (
    <div className="border-line bg-paper-sunken rounded-xl border px-3 py-2.5">
      <p className="text-[1.1rem] leading-none font-semibold tabular-nums">
        {gia}
      </p>
      <p className="text-ink-faint mt-1 text-[0.74rem]">{nhan}</p>
    </div>
  );
}

/** `2026-08-12T09:00:00+07:00` → `09:00`, theo giờ Việt Nam. */
function gioPhut(iso: string): string {
  return new Intl.DateTimeFormat("vi-VN", {
    hour: "2-digit",
    minute: "2-digit",
    timeZone: "Asia/Ho_Chi_Minh",
  }).format(new Date(iso));
}
