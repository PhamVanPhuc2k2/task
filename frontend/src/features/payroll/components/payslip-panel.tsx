"use client";

import { useState } from "react";

import { Button } from "@/components/ui/button";
import { EmptyState, ErrorState, Skeleton } from "@/components/ui/states";

import { useMyPayslip, usePayslipSheet } from "../api/payslip-api";
import { formatMoney } from "../types/payroll";
import {
  formatMinutes,
  formatPeriod,
  type PayslipItem,
} from "../types/payslip";
import { PayslipCard } from "./payslip-card";

/**
 * Màn phiếu lương: phiếu của tôi, và bảng kê cả công ty cho người có quyền.
 *
 * ## Kỳ mặc định do SERVER chọn
 *
 * Giao diện không tự tính "tháng trước" từ `new Date()`: đồng hồ máy người dùng
 * có thể lệch, và trong bảy tiếng đầu ngày mùng một giờ Việt Nam thì một máy
 * đặt múi giờ khác vẫn đang ở tháng cũ. Lần tải đầu gửi `period` rỗng; phản hồi
 * nói ra kỳ nào, và nút lùi/tiến dựa trên con số đó.
 */
export function PayslipPanel({ xemTatCa }: { xemTatCa: boolean }) {
  const [ky, setKy] = useState<string | null>(null);

  const cuaToi = useMyPayslip(ky);
  const bangKe = usePayslipSheet(ky, xemTatCa);

  // Kỳ đang xem, lấy từ phản hồi chứ không tự tính. `null` khi chưa tải xong.
  const kyHienTai = cuaToi.data?.period ?? null;

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <h2 className="text-[0.95rem] font-semibold tracking-tight">
          {kyHienTai ? `Kỳ ${formatPeriod(kyHienTai)}` : "Đang tải kỳ…"}
        </h2>

        {kyHienTai && (
          <div className="flex items-center gap-2">
            <Button
              size="sm"
              variant="ghost"
              aria-label="Kỳ trước"
              onClick={() => setKy(lui(kyHienTai, -1))}
            >
              ‹
            </Button>
            <span className="text-[0.9rem] font-semibold tabular-nums">
              {kyHienTai}
            </span>
            <Button
              size="sm"
              variant="ghost"
              aria-label="Kỳ sau"
              onClick={() => setKy(lui(kyHienTai, 1))}
            >
              ›
            </Button>
          </div>
        )}
      </div>

      {cuaToi.isPending && <Skeleton className="h-72" />}

      {cuaToi.isError && (
        <ErrorState
          error={cuaToi.error}
          onRetry={() => void cuaToi.refetch()}
        />
      )}

      {cuaToi.data && <PayslipCard phieu={cuaToi.data} />}

      {/* ── Bảng kê cả công ty ──────────────────────── */}
      {xemTatCa && (
        <section className="tone-card rounded-2xl p-5">
          <div className="mb-4 flex flex-wrap items-baseline justify-between gap-3">
            <div>
              <h2 className="text-[0.95rem] font-semibold tracking-tight">
                Bảng kê cả công ty
              </h2>
              <p className="text-ink-faint mt-1 text-[0.82rem]">
                Mọi lượt mở màn này đều được ghi nhật ký.
              </p>
            </div>

            {bangKe.data && (
              <p className="text-ink-soft text-[0.85rem]">
                Tổng chi{" "}
                <strong className="text-ink text-[1.05rem] font-semibold tabular-nums">
                  {formatMoney(bangKe.data.net_total)}
                </strong>
              </p>
            )}
          </div>

          {bangKe.isPending && <Skeleton className="h-48" />}

          {bangKe.isError && (
            <ErrorState
              error={bangKe.error}
              onRetry={() => void bangKe.refetch()}
            />
          )}

          {bangKe.data && bangKe.data.payslips.length === 0 && (
            <EmptyState
              title="Chưa có ai"
              description="Bảng kê chỉ tính người đang làm việc."
            />
          )}

          {bangKe.data && bangKe.data.payslips.length > 0 && (
            <>
              {/* Bảng rộng cuộn trong khung của nó — trang không cuộn ngang. */}
              <div className="-mx-2 overflow-x-auto px-2">
                <table className="w-full min-w-180 border-collapse text-[0.84rem]">
                  <thead>
                    <tr className="text-ink-faint border-line border-b text-left">
                      <th className="py-2 pr-3 font-medium">Nhân sự</th>
                      <Th>Đã làm</Th>
                      <Th>Thiếu</Th>
                      <Th>Làm thêm</Th>
                      <Th>Lương tháng</Th>
                      <Th>Trừ</Th>
                      <Th>Làm thêm</Th>
                      <Th>Thực nhận</Th>
                    </tr>
                  </thead>

                  <tbody className="divide-line divide-y">
                    {bangKe.data.payslips.map((p) => (
                      <Dong key={p.user?.id ?? p.period} phieu={p} />
                    ))}
                  </tbody>
                </table>
              </div>

              {/* Không cắt im lặng: với bảng lương thì 50 người bị cắt là 50
                  người không được trả mà không ai nhận ra. */}
              {bangKe.data.total > bangKe.data.payslips.length && (
                <p className="text-ink-faint mt-3 text-[0.82rem]">
                  Hiện {bangKe.data.payslips.length} người trên tổng số{" "}
                  {bangKe.data.total}
                </p>
              )}
            </>
          )}
        </section>
      )}
    </div>
  );
}

function Dong({ phieu }: { phieu: PayslipItem }) {
  const { minutes: p, money: t } = phieu;

  const truTong = String(
    Number(t.shortfall_deduction) + Number(t.unpaid_leave_deduction),
  );

  return (
    <tr>
      <td className="py-2.5 pr-3">
        <p className="truncate font-medium">{phieu.user?.name}</p>
        <p className="text-ink-faint text-[0.76rem]">
          {phieu.user?.employee_code ?? "—"}
          {phieu.user?.department && ` · ${phieu.user.department}`}
        </p>
      </td>

      <Td>{formatMinutes(p.worked)}</Td>
      <Td canhBao={p.shortfall > 0}>
        {p.shortfall === 0 ? "—" : formatMinutes(p.shortfall)}
      </Td>
      <Td>{p.overtime === 0 ? "—" : formatMinutes(p.overtime)}</Td>
      <Td>{formatMoney(t.base_salary)}</Td>
      {/*
        Cộng hai khoản trừ để bảng gọn. Đây là con số ĐỂ LIẾC, không phải con số
        để đối chiếu — phiếu chi tiết mới tách hai dòng ra, và chỉ ở đó mới cộng
        bằng chuỗi. Ở một ô liếc qua thì sai số float không tới được một đồng.
      */}
      <Td canhBao={Number(truTong) > 0}>
        {Number(truTong) === 0 ? "—" : `− ${formatMoney(truTong)}`}
      </Td>
      <Td>{t.overtime_pay === "0" ? "—" : formatMoney(t.overtime_pay)}</Td>
      <td className="py-2.5 pl-3 text-right font-semibold tabular-nums">
        {formatMoney(t.net_total)}
      </td>
    </tr>
  );
}

function Th({ children }: { children: React.ReactNode }) {
  return <th className="py-2 pl-3 text-right font-medium">{children}</th>;
}

function Td({
  children,
  canhBao = false,
}: {
  children: React.ReactNode;
  canhBao?: boolean;
}) {
  return (
    <td
      className={
        canhBao
          ? "text-danger py-2.5 pl-3 text-right tabular-nums"
          : "py-2.5 pl-3 text-right tabular-nums"
      }
    >
      {children}
    </td>
  );
}

/** Lùi hoặc tiến `n` tháng từ một kỳ `YYYY-MM`. */
function lui(ky: string, n: number): string {
  const [nam, thang] = ky.split("-").map(Number);

  // Cộng trừ trên hai số nguyên chứ không dựng `Date`: dựng Date từ "2026-08"
  // phải ghép thêm ngày và múi giờ, và đó là chỗ lệch một tháng ở vùng UTC+7.
  const tong = (nam ?? 0) * 12 + ((thang ?? 1) - 1) + n;

  return `${String(Math.floor(tong / 12)).padStart(4, "0")}-${String((tong % 12) + 1).padStart(2, "0")}`;
}
