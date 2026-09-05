"use client";

import { useState } from "react";

import { Button } from "@/components/ui/button";
import { TextArea, TextInput } from "@/components/ui/field";
import { Avatar } from "@/components/ui/pill";
import { EmptyState, ErrorState, Skeleton } from "@/components/ui/states";
import { formatDate } from "@/lib/format";

import { useSaveLeaveBalance, useTeamLeaveBalances } from "../api/balance-api";
import {
  formatDays,
  type LeaveBalanceItem,
  type LeavePolicy,
} from "../types/balance";

/**
 * Bảng quỹ phép năm của nhân sự trong phạm vi quản lý.
 *
 * Xem được không có nghĩa là sửa được: `can_manage` do server nói ra, vì
 * `leave.balance.manage` là quyền RIÊNG — duyệt một đơn nghỉ là quyết định về
 * một lần vắng mặt, cộng thêm ngày phép là quyết định ra tiền cho cả năm.
 */
export function BalancePanel({ year: namHienTai }: { year: number }) {
  const [nam, setNam] = useState(namHienTai);

  const ds = useTeamLeaveBalances(nam, true);

  return (
    <div className="space-y-8">
      <section className="tone-card rounded-2xl p-5">
        <div className="mb-4 flex flex-wrap items-baseline justify-between gap-3">
          <div>
            <h2 className="text-[0.95rem] font-semibold tracking-tight">
              Quỹ phép năm
            </h2>
            {ds.data && <ChinhSach policy={ds.data.policy} />}
          </div>

          <ChonNam nam={nam} onChange={setNam} moc={namHienTai} />
        </div>

        {ds.isPending && <Skeleton className="h-48" />}

        {ds.isError && (
          <ErrorState error={ds.error} onRetry={() => void ds.refetch()} />
        )}

        {ds.data && ds.data.balances.length === 0 && (
          <EmptyState
            title="Chưa có ai"
            description="Quỹ phép của nhân sự trong phạm vi bạn quản lý sẽ hiện ở đây."
          />
        )}

        {ds.data && ds.data.balances.length > 0 && (
          <>
            {/* Bảng rộng cuộn trong khung của nó — trang không bao giờ cuộn
                ngang. */}
            <div className="-mx-2 overflow-x-auto px-2">
              <table className="w-full min-w-160 border-collapse text-[0.84rem]">
                <thead>
                  <tr className="text-ink-faint border-line border-b text-left">
                    <Th>Nhân sự</Th>
                    <Th right>Được hưởng</Th>
                    <Th right>Tồn</Th>
                    <Th right>Điều chỉnh</Th>
                    <Th right>Đã dùng</Th>
                    <Th right>Còn lại</Th>
                    {ds.data.can_manage && <Th />}
                  </tr>
                </thead>

                <tbody className="divide-line divide-y">
                  {ds.data.balances.map((q) => (
                    <DongQuy
                      key={q.user?.id ?? q.year}
                      quy={q}
                      nam={nam}
                      canManage={ds.data.can_manage}
                      carryOverMax={ds.data.policy.carry_over_max_days}
                    />
                  ))}
                </tbody>
              </table>
            </div>

            {/* Không cắt im lặng: công ty 250 người phải biết mình đang xem
                200. Quy ước chung của cả dự án. */}
            {ds.data.total > ds.data.balances.length && (
              <p className="text-ink-faint mt-3 text-[0.82rem]">
                Hiện {ds.data.balances.length} người trên tổng số{" "}
                {ds.data.total}
              </p>
            )}
          </>
        )}
      </section>
    </div>
  );
}

/** Chính sách hiện hành — để nhân sự khỏi phải đi tra khi có người thắc mắc. */
function ChinhSach({ policy }: { policy: LeavePolicy }) {
  return (
    <p className="text-ink-faint mt-1 text-[0.82rem]">
      {policy.base_days} ngày cơ bản, thêm {policy.seniority_extra_days} ngày
      mỗi {policy.seniority_step_years} năm thâm niên.{" "}
      {policy.carry_over_max_days > 0
        ? `Chuyển tồn tối đa ${policy.carry_over_max_days} ngày.`
        : "Không cho chuyển phép tồn."}{" "}
      Đổi ở Cài đặt.
    </p>
  );
}

function ChonNam({
  nam,
  onChange,
  moc,
}: {
  nam: number;
  onChange: (v: number) => void;
  moc: number;
}) {
  return (
    <div className="flex items-center gap-2">
      <Button
        size="sm"
        variant="ghost"
        aria-label="Năm trước"
        onClick={() => onChange(nam - 1)}
        disabled={nam <= moc - 5}
      >
        ‹
      </Button>
      <span className="text-[0.9rem] font-semibold tabular-nums">{nam}</span>
      <Button
        size="sm"
        variant="ghost"
        aria-label="Năm sau"
        onClick={() => onChange(nam + 1)}
        disabled={nam >= moc + 1}
      >
        ›
      </Button>
    </div>
  );
}

function DongQuy({
  quy,
  nam,
  canManage,
  carryOverMax,
}: {
  quy: LeaveBalanceItem;
  nam: number;
  canManage: boolean;
  carryOverMax: number;
}) {
  const [mo, setMo] = useState(false);

  return (
    <>
      <tr>
        <td className="py-2.5 pr-3">
          <div className="flex items-center gap-2">
            <Avatar name={quy.user?.name ?? "?"} size="sm" />
            <div className="min-w-0">
              <p className="truncate font-medium">{quy.user?.name}</p>
              <p className="text-ink-faint text-[0.76rem]">
                {quy.user?.department ?? "Chưa xếp phòng"}
                {/* Ngày vào làm quyết định số phép của năm đầu — hiện ra để
                    nhân sự đối chiếu ngay khi con số trông lạ, và để thấy ai
                    đang thiếu ngày vào làm. */}
                {quy.user?.joined_at
                  ? ` · vào làm ${formatDate(quy.user.joined_at)}`
                  : " · chưa có ngày vào làm"}
              </p>
            </div>
          </div>
        </td>

        <Td right>
          {formatDays(quy.entitled_days)}
          {quy.is_overridden && (
            <span
              className="text-notice ml-1"
              title={`Nhân sự đặt riêng. Hệ thống tự tính ${formatDays(quy.computed_entitled_days)}.`}
            >
              *
            </span>
          )}
        </Td>
        <Td right>{formatDays(quy.carried_over_days)}</Td>
        <Td right>{formatDays(quy.adjustment_days)}</Td>
        <Td right>{formatDays(quy.used_days)}</Td>
        <td
          className={
            quy.remaining_days < 0
              ? "text-danger py-2.5 pl-3 text-right font-semibold tabular-nums"
              : "py-2.5 pl-3 text-right font-semibold tabular-nums"
          }
        >
          {formatDays(quy.remaining_days)}
        </td>

        {canManage && (
          <td className="py-2.5 pl-3 text-right">
            <Button size="sm" variant="ghost" onClick={() => setMo(!mo)}>
              {mo ? "Đóng" : "Sửa"}
            </Button>
          </td>
        )}
      </tr>

      {mo && canManage && quy.user && (
        <tr>
          <td colSpan={7} className="pb-4">
            <FormSua
              quy={quy}
              nam={nam}
              carryOverMax={carryOverMax}
              onDone={() => setMo(false)}
            />
          </td>
        </tr>
      )}
    </>
  );
}

function FormSua({
  quy,
  nam,
  carryOverMax,
  onDone,
}: {
  quy: LeaveBalanceItem;
  nam: number;
  carryOverMax: number;
  onDone: () => void;
}) {
  const luu = useSaveLeaveBalance();

  const [ghiDe, setGhiDe] = useState(
    quy.is_overridden ? String(quy.entitled_days) : "",
  );
  const [ton, setTon] = useState(String(quy.carried_over_days));
  const [dieuChinh, setDieuChinh] = useState(String(quy.adjustment_days));
  const [ghiChu, setGhiChu] = useState(quy.note ?? "");

  const conDuNamTruoc = quy.previous_remaining_days ?? 0;

  return (
    <div className="border-line bg-paper-sunken space-y-3 rounded-xl border p-4">
      {/*
        Gợi ý sẵn số dư năm trước.
        Không trả con số này thì nhân sự phải đổi năm, ghi ra giấy, rồi đổi về —
        và một phần sẽ gõ nhầm. Chỉ là GỢI Ý: chuyển phép tồn là quyết định có
        người chịu trách nhiệm, không phải phép cộng tự động.
      */}
      {conDuNamTruoc > 0 && carryOverMax > 0 && (
        <p className="text-ink-soft text-[0.84rem]">
          Năm {nam - 1} người này còn dư{" "}
          <strong className="text-ink">{formatDays(conDuNamTruoc)} ngày</strong>
          .{" "}
          <button
            type="button"
            className="text-accent focus-frame rounded underline underline-offset-2"
            onClick={() =>
              setTon(String(Math.min(conDuNamTruoc, carryOverMax)))
            }
          >
            Chuyển {formatDays(Math.min(conDuNamTruoc, carryOverMax))} ngày sang{" "}
            {nam}
          </button>
        </p>
      )}

      <div className="grid gap-3 sm:grid-cols-3">
        <OSo
          nhan="Phép tồn năm trước"
          gia={ton}
          onChange={setTon}
          loi={luu.error?.fieldError("carried_over_days")}
          min={0}
          max={carryOverMax}
        />
        <OSo
          nhan="Điều chỉnh (âm được)"
          gia={dieuChinh}
          onChange={setDieuChinh}
          loi={luu.error?.fieldError("adjustment_days")}
          min={-60}
          max={60}
        />
        <OSo
          nhan="Ghi đè số được hưởng"
          goiY={`Để trống thì tự tính (${formatDays(quy.computed_entitled_days)})`}
          gia={ghiDe}
          onChange={setGhiDe}
          loi={luu.error?.fieldError("entitled_days_override")}
          min={0}
          max={60}
        />
      </div>

      <div>
        <label
          htmlFor={`ghi-chu-quy-${quy.user?.id}`}
          className="text-ink-soft mb-1.5 block text-[0.82rem] font-medium"
        >
          Ghi chú — sáu tháng sau sẽ có người hỏi vì sao con số này khác
        </label>
        <TextArea
          id={`ghi-chu-quy-${quy.user?.id}`}
          rows={2}
          value={ghiChu}
          placeholder="Chuyển 3 ngày tồn của năm trước theo thoả thuận với trưởng phòng."
          onChange={(e) => setGhiChu(e.target.value)}
        />
      </div>

      {luu.error && !luu.error.errors && (
        <p role="alert" className="text-danger text-[0.84rem]">
          {luu.error.message}
        </p>
      )}

      <div className="flex flex-wrap gap-2">
        <Button
          size="sm"
          variant="primary"
          loading={luu.isPending}
          onClick={() =>
            luu.mutate(
              {
                userId: quy.user?.id ?? "",
                year: nam,
                entitled_days_override: ghiDe === "" ? null : Number(ghiDe),
                carried_over_days: Number(ton || 0),
                adjustment_days: Number(dieuChinh || 0),
                note: ghiChu.trim() === "" ? null : ghiChu.trim(),
              },
              { onSuccess: onDone },
            )
          }
        >
          Lưu
        </Button>
        <Button size="sm" variant="ghost" onClick={onDone}>
          Huỷ
        </Button>
      </div>
    </div>
  );
}

function OSo({
  nhan,
  goiY,
  gia,
  onChange,
  loi,
  min,
  max,
}: {
  nhan: string;
  goiY?: string;
  gia: string;
  onChange: (v: string) => void;
  loi?: string | null;
  min: number;
  max: number;
}) {
  const id = `o-${nhan.replace(/\s+/g, "-")}`;

  return (
    <div>
      <label
        htmlFor={id}
        className="text-ink-soft mb-1.5 block text-[0.82rem] font-medium"
      >
        {nhan}
      </label>
      <TextInput
        id={id}
        type="number"
        // Bước 0,5: công ty làm sáng thứ bảy nên nửa ngày phép là đơn vị có
        // thật. Server cũng chỉ nhận bội của 0,5.
        step={0.5}
        min={min}
        max={max}
        value={gia}
        onChange={(e) => onChange(e.target.value)}
      />
      {loi ? (
        <p role="alert" className="text-danger mt-1 text-[0.78rem]">
          {loi}
        </p>
      ) : (
        goiY && <p className="text-ink-faint mt-1 text-[0.78rem]">{goiY}</p>
      )}
    </div>
  );
}

function Th({
  children,
  right = false,
}: {
  children?: React.ReactNode;
  right?: boolean;
}) {
  return (
    <th
      className={
        right
          ? "py-2 pl-3 text-right font-medium"
          : "py-2 pr-3 text-left font-medium"
      }
    >
      {children}
    </th>
  );
}

function Td({
  children,
  right = false,
}: {
  children?: React.ReactNode;
  right?: boolean;
}) {
  return (
    <td
      className={right ? "py-2.5 pl-3 text-right tabular-nums" : "py-2.5 pr-3"}
    >
      {children}
    </td>
  );
}
