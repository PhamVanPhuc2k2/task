"use client";

import { useState } from "react";

import { Button } from "@/components/ui/button";
import { TextArea } from "@/components/ui/field";
import { EmptyState, ErrorState, Skeleton } from "@/components/ui/states";
import { formatDateTime } from "@/lib/format";

import { useClosePeriod, usePeriods, useReopenPeriod } from "../api/period-api";
import {
  formatPeriod,
  type ClosablePeriod,
  type PeriodItem,
} from "../types/period";

/**
 * Chốt sổ kỳ công.
 *
 * Nền móng của mọi phép tính tiền: **trả lương từ những con số còn sửa được
 * nghĩa là không bao giờ trả lời được câu *"phiếu lương này tính từ đâu ra"***.
 * Sau khi chốt, không ai sửa được số liệu của kỳ đó — kể cả admin.
 *
 * Hai quyền tách nhau, và màn này hỏi server thay vì tự suy từ danh sách quyền:
 * giám đốc chốt **và** mở khoá, admin chỉ chốt. Thêm một quyền ở đợt sau thì
 * giao diện tự đúng.
 */
export function PeriodPanel({ enabled }: { enabled: boolean }) {
  const ds = usePeriods(enabled);

  return (
    <div className="space-y-8">
      <section className="tone-card rounded-2xl p-5">
        <h2 className="mb-1 text-[0.95rem] font-semibold tracking-tight">
          Chốt sổ kỳ công
        </h2>
        <p className="text-ink-faint mb-4 text-[0.84rem]">
          Chốt một kỳ là khoá cả giờ công, đơn từ và báo cáo ngày của kỳ đó. Đọc
          thì vẫn đọc bình thường — khoá chỉ chặn ghi.
        </p>

        {ds.isPending && <Skeleton className="h-32" />}

        {ds.isError && (
          <ErrorState error={ds.error} onRetry={() => void ds.refetch()} />
        )}

        {ds.data &&
          (ds.data.closable === null ? (
            <p className="border-line bg-paper-sunken text-ink-soft rounded-xl border px-3.5 py-2.5 text-[0.84rem]">
              Không còn kỳ nào để chốt — mọi kỳ đã kết thúc đều đã chốt sổ.
            </p>
          ) : (
            <KySapChot ky={ds.data.closable} canClose={ds.data.can_close} />
          ))}
      </section>

      {/* ── Lịch sử ─────────────────────────────────── */}
      <section className="tone-card rounded-2xl p-5">
        <h2 className="mb-1 text-[0.95rem] font-semibold tracking-tight">
          Các kỳ đã chốt
        </h2>
        <p className="text-ink-faint mb-4 text-[0.84rem]">
          Mười hai kỳ gần nhất. Mọi lần chốt và mở khoá đều vào nhật ký kiểm
          toán — bảng này chỉ hiện trạng thái hiện tại.
        </p>

        {ds.data && ds.data.periods.length === 0 && (
          <EmptyState
            title="Chưa chốt kỳ nào"
            description="Kỳ công đã chốt sẽ hiện ở đây, kèm người chốt và thời điểm."
          />
        )}

        {ds.data && ds.data.periods.length > 0 && (
          <ul className="divide-line divide-y">
            {ds.data.periods.map((k) => (
              <DongKy key={k.period} ky={k} canReopen={ds.data.can_reopen} />
            ))}
          </ul>
        )}
      </section>
    </div>
  );
}

/**
 * Kỳ mà nút "Chốt sổ" đang nhắm tới, kèm lý do nếu chưa bấm được.
 *
 * Nút mờ **luôn** đi kèm lời giải thích và con số. Một nút mờ không giải thích
 * là thứ người ta bấm ba lần rồi đi hỏi người khác.
 */
function KySapChot({
  ky,
  canClose,
}: {
  ky: ClosablePeriod;
  canClose: boolean;
}) {
  const [xacNhan, setXacNhan] = useState(false);
  const chot = useClosePeriod();

  const conTreo = Object.entries(ky.pending).filter(([, so]) => so > 0);

  return (
    <div className="border-line bg-paper-sunken rounded-xl border p-4">
      <div className="flex flex-wrap items-baseline justify-between gap-3">
        <p className="text-[0.95rem] font-semibold">
          Kỳ sắp chốt: {formatPeriod(ky.period)}
        </p>

        {ky.ready ? (
          <span className="border-tone-line bg-tone-surface text-tone-ink rounded-full border px-2 py-0.5 text-[0.74rem] font-medium">
            Sẵn sàng
          </span>
        ) : (
          <span className="border-notice-line bg-notice-surface text-notice rounded-full border px-2 py-0.5 text-[0.74rem] font-medium">
            Còn đơn chờ duyệt
          </span>
        )}
      </div>

      {conTreo.length > 0 && (
        <div className="mt-3">
          <p className="text-ink-soft text-[0.84rem]">
            Xử lý hết những đơn này rồi mới chốt được — chốt sổ khoá luôn đơn
            từ, nên đơn còn treo sẽ không ai duyệt được nữa:
          </p>
          <ul className="text-ink-soft mt-2 space-y-1 text-[0.84rem]">
            {conTreo.map(([nhan, so]) => (
              <li key={nhan}>
                <strong className="text-ink font-semibold tabular-nums">
                  {so}
                </strong>{" "}
                {nhan}
              </li>
            ))}
          </ul>
        </div>
      )}

      {!canClose && (
        <p className="text-ink-faint mt-3 text-[0.84rem]">
          Bạn không có quyền chốt sổ. Việc này thuộc về giám đốc hoặc quản trị
          viên.
        </p>
      )}

      {canClose && (
        <div className="mt-4">
          {!xacNhan ? (
            <Button
              variant="primary"
              disabled={!ky.ready}
              onClick={() => setXacNhan(true)}
            >
              Chốt sổ {formatPeriod(ky.period)}
            </Button>
          ) : (
            <div className="space-y-3">
              {/*
                Hỏi lại một nhịp. Chốt sổ khoá số liệu đã dùng để trả lương, và
                chỉ GIÁM ĐỐC mở khoá lại được — admin bấm nhầm thì phải đi tìm
                người khác để gỡ.
              */}
              <p className="border-notice-line bg-notice-surface text-notice rounded-xl border px-3.5 py-2.5 text-[0.84rem]">
                Sau khi chốt, <strong>không ai sửa được</strong> giờ công, đơn
                từ hay báo cáo ngày của {formatPeriod(ky.period)} — kể cả quản
                trị viên. Chỉ giám đốc mở khoá lại được.
              </p>

              {chot.error && (
                <p role="alert" className="text-danger text-[0.84rem]">
                  {chot.error.fieldError("period") ?? chot.error.message}
                </p>
              )}

              <div className="flex flex-wrap gap-2">
                <Button
                  variant="primary"
                  loading={chot.isPending}
                  onClick={() => chot.mutate(ky.period)}
                >
                  Xác nhận chốt sổ
                </Button>
                <Button variant="ghost" onClick={() => setXacNhan(false)}>
                  Huỷ
                </Button>
              </div>
            </div>
          )}
        </div>
      )}
    </div>
  );
}

function DongKy({ ky, canReopen }: { ky: PeriodItem; canReopen: boolean }) {
  const [mo, setMo] = useState(false);
  const [lyDo, setLyDo] = useState("");

  const moKhoa = useReopenPeriod();

  return (
    <li className="py-3.5 first:pt-0 last:pb-0">
      <div className="flex flex-wrap items-baseline gap-x-3 gap-y-1">
        <span className="text-[0.9rem] font-semibold">
          {formatPeriod(ky.period)}
        </span>

        <span className="text-ink-faint text-[0.82rem]">
          {ky.is_locked ? "Chốt" : "Chốt lần gần nhất"}{" "}
          {formatDateTime(ky.closed_at)}
          {ky.closed_by && ` bởi ${ky.closed_by}`}
        </span>

        <span
          className={
            ky.is_locked
              ? "border-tone-line bg-tone-surface text-tone-ink ml-auto shrink-0 rounded-full border px-2 py-0.5 text-[0.74rem] font-medium"
              : "border-notice-line bg-notice-surface text-notice ml-auto shrink-0 rounded-full border px-2 py-0.5 text-[0.74rem] font-medium"
          }
        >
          {ky.status_label}
        </span>
      </div>

      {/*
        Vết mở khoá giữ nguyên cả sau khi chốt lại. Xoá đi thì bảng chỉ còn nói
        "đã chốt", và lịch sử đóng mở biến mất khỏi chỗ người ta nhìn đầu tiên.
      */}
      {ky.reopened_at && (
        <p className="border-line bg-paper-sunken text-ink-soft mt-2 rounded-lg border px-3 py-2 text-[0.84rem]">
          <strong className="text-ink font-semibold">
            Mở khoá {formatDateTime(ky.reopened_at)}
            {ky.reopened_by && ` bởi ${ky.reopened_by}`}:
          </strong>{" "}
          {ky.reopen_reason}
        </p>
      )}

      {canReopen && ky.is_locked && (
        <div className="mt-2.5">
          {!mo ? (
            <Button size="sm" variant="ghost" onClick={() => setMo(true)}>
              Mở khoá
            </Button>
          ) : (
            <div className="space-y-2">
              <label
                htmlFor={`ly-do-mo-khoa-${ky.period}`}
                className="text-ink-soft block text-[0.82rem] font-medium"
              >
                Lý do mở khoá — ba tháng sau sẽ có người hỏi vì sao giờ công
                tháng này khác con số trên phiếu lương
              </label>
              <TextArea
                id={`ly-do-mo-khoa-${ky.period}`}
                rows={2}
                value={lyDo}
                placeholder="Kế toán báo sai giờ công của hai người, cần sửa lại trước khi trả lương."
                onChange={(e) => setLyDo(e.target.value)}
              />

              {moKhoa.error && (
                <p role="alert" className="text-danger text-[0.84rem]">
                  {moKhoa.error.fieldError("reason") ??
                    moKhoa.error.fieldError("period") ??
                    moKhoa.error.message}
                </p>
              )}

              <div className="flex flex-wrap gap-2">
                <Button
                  size="sm"
                  variant="primary"
                  loading={moKhoa.isPending}
                  disabled={lyDo.trim().length < 10}
                  onClick={() =>
                    moKhoa.mutate(
                      { period: ky.period, reason: lyDo.trim() },
                      { onSuccess: () => setMo(false) },
                    )
                  }
                >
                  Mở khoá kỳ này
                </Button>
                <Button size="sm" variant="ghost" onClick={() => setMo(false)}>
                  Huỷ
                </Button>
              </div>
            </div>
          )}
        </div>
      )}
    </li>
  );
}
