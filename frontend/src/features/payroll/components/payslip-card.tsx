"use client";

import { formatMoney } from "../types/payroll";
import {
  formatMinutes,
  formatPeriod,
  type PayslipItem,
} from "../types/payslip";

/**
 * Một phiếu lương, hiện đủ đường đi từ lương tháng tới số thực nhận.
 *
 * ## Cộng tay các dòng phải ra đúng tổng
 *
 * Đó là phép kiểm mà bất kỳ ai cũng làm được, và là lý do phiếu hiện từng dòng
 * chứ không chỉ con số cuối. Câu hỏi thật không phải *"tôi được bao nhiêu"* —
 * con số đó nằm trên tài khoản ngân hàng — mà là *"vì sao tháng này ít hơn
 * tháng trước"*.
 *
 * ## Số tiền là CHUỖI, không bao giờ ép sang number
 *
 * `12500000.10 + 2000000.20` trong JavaScript ra `14500000.299999999`. Backend
 * đã cộng xong bằng `bcmath`; ở đây chỉ định dạng để đọc.
 */
export function PayslipCard({ phieu }: { phieu: PayslipItem }) {
  const { minutes: p, money: t } = phieu;

  return (
    <section className="tone-card rounded-2xl p-5">
      <div className="mb-4 flex flex-wrap items-baseline justify-between gap-x-4 gap-y-2">
        <div>
          <h2 className="text-[0.95rem] font-semibold tracking-tight">
            Phiếu lương {formatPeriod(phieu.period)}
            {phieu.user && (
              <span className="text-ink-faint ml-2 font-normal">
                {phieu.user.name}
              </span>
            )}
          </h2>

          {/*
            Kỳ chưa chốt sổ thì mọi con số còn đổi được: một đơn giải trình
            được duyệt chiều nay sẽ đổi số giờ thiếu của cả tháng. Không nói ra
            thì người ta chụp màn hình một phiếu tạm rồi tháng sau đối chiếu với
            phiếu thật và không hiểu.
          */}
          <p className="text-ink-faint mt-1 text-[0.82rem]">
            {phieu.is_final
              ? "Kỳ đã chốt sổ — các con số dưới đây không đổi nữa."
              : "Bản tạm: kỳ chưa chốt sổ, số liệu còn thay đổi."}
          </p>
        </div>

        <p className="text-ink-soft text-[0.85rem]">
          Thực nhận{" "}
          <strong className="text-ink text-[1.25rem] font-semibold tabular-nums">
            {formatMoney(t.net_total)}
          </strong>
        </p>
      </div>

      {/* ── Giờ công ────────────────────────────────── */}
      <div className="border-line bg-paper-sunken mb-4 rounded-xl border px-3.5 py-3">
        <div className="grid gap-x-6 gap-y-1.5 text-[0.84rem] sm:grid-cols-2">
          <Gio
            nhan="Giờ chuẩn của kỳ"
            gia={p.standard}
            chu="theo lịch thực tế, không phải 26 ngày cố định"
          />
          <Gio nhan="Đã làm" gia={p.worked} />
          {p.paid_leave > 0 && (
            <Gio
              nhan="Nghỉ có hưởng lương"
              gia={p.paid_leave}
              chu="không phải có mặt, không bị trừ"
            />
          )}
          {p.unpaid_leave > 0 && (
            <Gio nhan="Nghỉ không lương" gia={p.unpaid_leave} />
          )}
          <Gio nhan="Phải có mặt" gia={p.required} />
          <Gio
            nhan="Thiếu giờ"
            gia={p.shortfall}
            chu={p.shortfall > 0 ? "đã trừ ân hạn từng ngày" : undefined}
            canhBao={p.shortfall > 0}
          />
          {p.overtime > 0 && <Gio nhan="Làm thêm đã duyệt" gia={p.overtime} />}
        </div>
      </div>

      {/* ── Tiền ────────────────────────────────────── */}
      <ul className="divide-line divide-y text-[0.88rem]">
        <Dong nhan="Lương tháng" tien={t.base_salary} />

        {t.allowance !== "0" && <Dong nhan="Phụ cấp" tien={t.allowance} />}

        {p.shortfall > 0 && (
          <Dong
            nhan="Trừ thiếu giờ"
            phu={`${formatMinutes(p.shortfall)} × ${formatMoney(t.hourly_rate)}/giờ`}
            tien={t.shortfall_deduction}
            am
          />
        )}

        {p.unpaid_leave > 0 && (
          <Dong
            nhan="Trừ nghỉ không lương"
            phu={`${formatMinutes(p.unpaid_leave)} × ${formatMoney(t.hourly_rate)}/giờ`}
            tien={t.unpaid_leave_deduction}
            am
          />
        )}

        {/* Gom theo hệ số, sắp từ thấp lên cao — phiếu của hai tháng đọc giống
            nhau, chứ không chạy theo thứ tự đơn được duyệt. */}
        {phieu.overtime_lines.map((d) => (
          <Dong
            key={d.percent}
            nhan={`Làm thêm ${d.percent}%`}
            phu={`${formatMinutes(d.minutes)} × ${formatMoney(t.hourly_rate)}/giờ × ${d.percent}%`}
            tien={d.amount}
          />
        ))}

        <li className="flex flex-wrap items-baseline justify-between gap-2 py-2.5">
          <span className="font-semibold">Thực nhận</span>
          <span className="text-[1.05rem] font-semibold tabular-nums">
            {formatMoney(t.net_total)}
          </span>
        </li>
      </ul>

      <p className="text-ink-faint mt-3 text-[0.78rem]">
        Lương giờ {formatMoney(t.hourly_rate)} = lương tháng ÷{" "}
        {formatMinutes(p.standard)} chuẩn của kỳ. Chưa gồm thuế thu nhập cá
        nhân, bảo hiểm xã hội và công đoàn phí.
      </p>
    </section>
  );
}

function Gio({
  nhan,
  gia,
  chu,
  canhBao = false,
}: {
  nhan: string;
  gia: number;
  chu?: string;
  canhBao?: boolean;
}) {
  return (
    <p className="text-ink-soft">
      {nhan}:{" "}
      <strong
        className={
          canhBao
            ? "text-danger font-semibold tabular-nums"
            : "text-ink font-semibold tabular-nums"
        }
      >
        {gia === 0 ? "0" : formatMinutes(gia)}
      </strong>
      {chu && <span className="text-ink-faint"> — {chu}</span>}
    </p>
  );
}

function Dong({
  nhan,
  phu,
  tien,
  am = false,
}: {
  nhan: string;
  phu?: string;
  tien: string;
  am?: boolean;
}) {
  return (
    <li className="flex flex-wrap items-baseline justify-between gap-2 py-2.5">
      <span>
        {nhan}
        {phu && (
          <span className="text-ink-faint ml-1.5 text-[0.78rem]">{phu}</span>
        )}
      </span>
      <span
        className={
          am
            ? "text-danger font-medium tabular-nums"
            : "font-medium tabular-nums"
        }
      >
        {am && "− "}
        {formatMoney(tien)}
      </span>
    </li>
  );
}
