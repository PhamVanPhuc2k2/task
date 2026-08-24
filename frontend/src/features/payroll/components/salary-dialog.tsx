"use client";

import { useState } from "react";

import { Button } from "@/components/ui/button";
import { Dialog } from "@/components/ui/dialog";
import { Field, TextArea, TextInput } from "@/components/ui/field";
import { formatDate } from "@/lib/format";

import { useSalaryHistory, useSetSalary } from "../api/payroll-api";
import { formatMoney, type SalaryRecord } from "../types/payroll";

/**
 * Lịch sử mức lương của một người, và ô đặt mức mới.
 *
 * Hiện lịch sử **trước** ô nhập, có chủ ý: người đặt lương cần thấy mức hiện
 * tại và lần điều chỉnh gần nhất trước khi gõ số mới. Đặt ô nhập lên đầu là mời
 * người ta gõ mà chưa biết đang gõ đè lên cái gì.
 */
export function SalaryDialog({
  open,
  onClose,
  userId,
  userName,
  canManage,
}: {
  open: boolean;
  onClose: () => void;
  userId: string;
  userName: string;
  canManage: boolean;
}) {
  const lichSu = useSalaryHistory(userId, open);
  const dat = useSetSalary();

  const [luongCoBan, setLuongCoBan] = useState("");
  const [phuCap, setPhuCap] = useState("");
  const [hieuLucTu, setHieuLucTu] = useState(() =>
    new Date().toISOString().slice(0, 10),
  );
  const [lyDo, setLyDo] = useState("");

  const hienHanh = lichSu.data?.find((r) => r.is_current);

  function gui() {
    dat.mutate(
      {
        userId,
        base_salary: luongCoBan.replaceAll(".", "").trim(),
        allowance: phuCap.replaceAll(".", "").trim() || "0",
        effective_from: hieuLucTu,
        reason: lyDo.trim(),
      },
      {
        onSuccess: () => {
          setLuongCoBan("");
          setPhuCap("");
          setLyDo("");
          dat.reset();
        },
      },
    );
  }

  return (
    <Dialog open={open} onClose={onClose} title={`Lương — ${userName}`}>
      <div className="space-y-5">
        {lichSu.isPending && (
          <p className="text-ink-faint text-[0.86rem]">Đang tải…</p>
        )}

        {lichSu.data && lichSu.data.length === 0 && (
          <p className="border-line text-ink-faint rounded-xl border border-dashed px-4 py-5 text-[0.86rem]">
            Chưa đặt mức lương nào cho người này.
          </p>
        )}

        {hienHanh && (
          <div className="border-accent-line bg-accent-surface rounded-xl border px-4 py-3">
            <p className="text-ink-soft text-[0.76rem]">Đang hưởng</p>
            <p className="text-ink mt-0.5 text-[1.25rem] leading-none font-semibold tabular-nums">
              {formatMoney(hienHanh.total)}
            </p>
            <p className="text-ink-soft mt-1.5 text-[0.78rem]">
              Cơ bản {formatMoney(hienHanh.base_salary)} · Phụ cấp{" "}
              {formatMoney(hienHanh.allowance)} · từ{" "}
              {formatDate(hienHanh.effective_from)}
            </p>
          </div>
        )}

        {lichSu.data && lichSu.data.length > 0 && (
          <section>
            <h3 className="text-ink-soft mb-2 text-[0.82rem] font-medium">
              Lịch sử điều chỉnh
            </h3>
            <ul className="divide-line border-line divide-y rounded-xl border">
              {lichSu.data.map((r) => (
                <Dong key={r.id} record={r} />
              ))}
            </ul>
          </section>
        )}

        {canManage && (
          <div className="border-line space-y-4 border-t pt-4">
            <h3 className="text-[0.9rem] font-semibold tracking-tight">
              Đặt mức mới
            </h3>

            <div className="grid gap-4 sm:grid-cols-2">
              <Field
                label="Lương cơ bản"
                required
                hint="Đơn vị: đồng."
                error={dat.error?.fieldError("base_salary")}
              >
                {(id, describedBy) => (
                  <TextInput
                    id={id}
                    inputMode="numeric"
                    aria-describedby={describedBy}
                    value={luongCoBan}
                    placeholder="15000000"
                    onChange={(e) => setLuongCoBan(e.target.value)}
                  />
                )}
              </Field>

              <Field
                label="Phụ cấp"
                hint="Để trống nếu không có."
                error={dat.error?.fieldError("allowance")}
              >
                {(id, describedBy) => (
                  <TextInput
                    id={id}
                    inputMode="numeric"
                    aria-describedby={describedBy}
                    value={phuCap}
                    placeholder="0"
                    onChange={(e) => setPhuCap(e.target.value)}
                  />
                )}
              </Field>
            </div>

            <Field
              label="Hiệu lực từ"
              required
              hint={
                hienHanh
                  ? `Phải sau ${formatDate(hienHanh.effective_from)} — ngày bắt đầu của mức đang áp dụng.`
                  : "Ngày mức lương này bắt đầu có hiệu lực."
              }
              error={dat.error?.fieldError("effective_from")}
            >
              {(id, describedBy) => (
                <TextInput
                  id={id}
                  type="date"
                  aria-describedby={describedBy}
                  value={hieuLucTu}
                  onChange={(e) => setHieuLucTu(e.target.value)}
                />
              )}
            </Field>

            <Field
              label="Lý do"
              required
              hint="Sáu tháng sau sẽ có người hỏi vì sao mức lương này thay đổi."
              error={dat.error?.fieldError("reason")}
            >
              {(id, describedBy) => (
                <TextArea
                  id={id}
                  rows={2}
                  aria-describedby={describedBy}
                  value={lyDo}
                  placeholder="Tăng lương theo kỳ đánh giá giữa năm."
                  onChange={(e) => setLyDo(e.target.value)}
                />
              )}
            </Field>

            {dat.error && !dat.error.errors && (
              <p role="alert" className="text-danger text-[0.84rem]">
                {dat.error.message}
              </p>
            )}

            <div className="flex gap-3">
              <Button
                variant="primary"
                loading={dat.isPending}
                disabled={luongCoBan.trim() === "" || lyDo.trim().length < 5}
                onClick={gui}
              >
                Lưu mức lương
              </Button>
              <Button variant="ghost" onClick={onClose}>
                Đóng
              </Button>
            </div>
          </div>
        )}

        {!canManage && (
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

function Dong({ record }: { record: SalaryRecord }) {
  return (
    <li className="px-3.5 py-2.5">
      <div className="flex flex-wrap items-baseline justify-between gap-2">
        <span className="text-[0.86rem] font-medium tabular-nums">
          {formatMoney(record.total)}
        </span>
        <span className="text-ink-faint text-[0.78rem] tabular-nums">
          {formatDate(record.effective_from)}
          {record.effective_to === null
            ? " → nay"
            : ` → ${formatDate(record.effective_to)}`}
        </span>
      </div>

      <p className="text-ink-soft mt-1 text-[0.8rem] leading-relaxed">
        {record.reason}
        {record.author && (
          <span className="text-ink-faint"> — {record.author.name}</span>
        )}
      </p>
    </li>
  );
}
