"use client";

import { ErrorState, Skeleton } from "@/components/ui/states";

import { useMyLeaveBalance } from "../api/balance-api";
import { formatDays, type LeaveBalanceItem } from "../types/balance";

/**
 * Quỹ phép năm của chính người đang xem, đặt ngay trên ô xin nghỉ.
 *
 * ## Vì sao hiện cả phép cộng chứ không chỉ số dư
 *
 * Câu hỏi thật của người dùng không phải *"tôi còn mấy ngày"* mà là *"vì sao
 * tôi còn ngần này"*. Hiện mỗi số dư thì mọi thắc mắc đều dồn về nhân sự, và
 * nhân sự cũng phải mở database ra xem.
 *
 * ## Không tự tính chi phí của đơn đang gõ
 *
 * Nghỉ từ thứ sáu tới thứ hai tiêu 2,5 ngày chứ không phải 4 — vì thứ bảy nửa
 * buổi, chủ nhật và ngày lễ không tính. Chép luật đó xuống trình duyệt là hai
 * nơi cùng định nghĩa "một ngày phép", và chúng sẽ lệch nhau ở lần công ty đổi
 * lịch tuần đầu tiên. Màn này chỉ nói số còn lại; server mới là nơi chặn, và
 * câu lỗi của nó nói rõ đơn cần bao nhiêu ngày.
 */
export function LeaveBalanceCard() {
  const quy = useMyLeaveBalance();

  if (quy.isPending) return <Skeleton className="h-28" />;

  if (quy.isError) {
    return <ErrorState error={quy.error} onRetry={() => void quy.refetch()} />;
  }

  const q = quy.data;

  return (
    <section className="tone-card rounded-2xl p-5">
      <div className="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-2">
        <h2 className="text-[0.95rem] font-semibold tracking-tight">
          Phép năm {q.year}
        </h2>

        <p className="text-ink-soft text-[0.85rem]">
          Còn{" "}
          <strong
            className={
              q.remaining_days <= 0
                ? "text-danger text-[1.15rem] font-semibold tabular-nums"
                : "text-ink text-[1.15rem] font-semibold tabular-nums"
            }
          >
            {formatDays(q.remaining_days)}
          </strong>{" "}
          / {formatDays(q.total_days)} ngày
        </p>
      </div>

      <PhepCong quy={q} />

      {q.note && (
        <p className="border-line bg-paper-sunken text-ink-soft mt-3 rounded-lg border px-3 py-2 text-[0.84rem]">
          {q.note}
        </p>
      )}

      {/*
        Năm ngoái còn dư mà năm nay chưa được chuyển sang.
        Chuyển phép tồn KHÔNG tự động — nó là quyết định có người chịu trách
        nhiệm. Không nói ra thì người ta tưởng số đó đã mất.
      */}
      {(q.previous_remaining_days ?? 0) > 0 && q.carried_over_days === 0 && (
        <p className="border-notice-line bg-notice-surface text-notice mt-3 rounded-xl border px-3.5 py-2.5 text-[0.84rem]">
          Năm {q.year - 1} bạn còn dư{" "}
          <strong>{formatDays(q.previous_remaining_days ?? 0)} ngày</strong>{" "}
          chưa được chuyển sang năm nay. Phép tồn không tự chuyển — hỏi nhân sự
          nếu bạn cần dùng.
        </p>
      )}

      {q.remaining_days <= 0 && (
        <p className="border-danger-line bg-danger-surface text-danger mt-3 rounded-xl border px-3.5 py-2.5 text-[0.84rem]">
          Đã dùng hết phép năm. Cần nghỉ thêm thì chọn loại{" "}
          <strong>Nghỉ không lương</strong> khi nộp đơn.
        </p>
      )}
    </section>
  );
}

/** Phép cộng ra số dư, viết thành một dòng đọc được. */
function PhepCong({ quy }: { quy: LeaveBalanceItem }) {
  return (
    <p className="text-ink-faint mt-2 text-[0.82rem]">
      <O nhan="Được hưởng" gia={quy.entitled_days} />
      {quy.carried_over_days !== 0 && (
        <>
          {" + "}
          <O nhan="tồn năm trước" gia={quy.carried_over_days} />
        </>
      )}
      {quy.adjustment_days !== 0 && (
        <>
          {quy.adjustment_days > 0 ? " + " : " − "}
          <O nhan="điều chỉnh" gia={Math.abs(quy.adjustment_days)} />
        </>
      )}
      {" − "}
      <O nhan="đã dùng" gia={quy.used_days} />

      {/* Ghi đè thì nói ra số hệ thống tự tính, để người đọc biết con số này là
          một quyết định của nhân sự chứ không phải kết quả phép tính. */}
      {quy.is_overridden && (
        <span className="ml-1.5">
          (nhân sự đặt riêng, hệ thống tự tính{" "}
          {formatDays(quy.computed_entitled_days)})
        </span>
      )}
    </p>
  );
}

function O({ nhan, gia }: { nhan: string; gia: number }) {
  return (
    <>
      {nhan}{" "}
      <strong className="text-ink-soft font-semibold tabular-nums">
        {formatDays(gia)}
      </strong>
    </>
  );
}
