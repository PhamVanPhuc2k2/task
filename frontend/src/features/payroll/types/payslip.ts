/** Khớp với App\Http\Controllers\Api\V1\Payroll\*Payslip*. */

import type { Money } from "./payroll";

/**
 * Số phút của một phiếu lương.
 *
 * Giữ CẢ ĐƯỜNG ĐI chứ không chỉ con số cuối: câu hỏi thật của người nhận lương
 * là *"vì sao tháng này ít hơn tháng trước"*, và trả mỗi tổng thì mọi thắc mắc
 * đều dồn về kế toán.
 */
export interface PayslipMinutes {
  /**
   * Số phút chuẩn của kỳ, theo **lịch thực tế** — không phải 26 ngày cố định.
   *
   * Đây là mẫu số của lương giờ, nên nó đổi theo tháng. Màn hình phải hiện nó
   * ngay cạnh lương giờ, nếu không thì con số lương giờ đến từ hư không.
   */
  standard: number;
  /** Số phút thật sự phải có mặt = chuẩn − nghỉ có lương − nghỉ không lương. */
  required: number;
  worked: number;
  paid_leave: number;
  unpaid_leave: number;
  /**
   * Số phút thiếu, đã cộng dồn theo TỪNG NGÀY và đã áp ân hạn từng ngày.
   *
   * Không tính lại được từ `required - worked`: ân hạn áp cho mỗi ngày, nên
   * hiệu của hai tổng ra một con số khác.
   */
  shortfall: number;
  overtime: number;
}

export interface PayslipMoney {
  base_salary: Money;
  allowance: Money;
  /** Lương một giờ, đã làm tròn để hiển thị. */
  hourly_rate: Money;
  shortfall_deduction: Money;
  unpaid_leave_deduction: Money;
  overtime_pay: Money;
  net_total: Money;
}

export interface PayslipOvertimeLine {
  /** Hệ số phần trăm — 150, 200 hoặc 300 (Điều 98 BLLĐ 2019). */
  percent: number;
  minutes: number;
  amount: Money;
}

export interface PayslipItem {
  period: string;
  /**
   * Kỳ đã chốt sổ chưa.
   *
   * `false` = bản TẠM: một đơn giải trình được duyệt chiều nay sẽ đổi số giờ
   * thiếu của cả tháng. Màn hình phải nói thẳng điều đó.
   */
  is_final: boolean;
  minutes: PayslipMinutes;
  money: PayslipMoney;
  overtime_lines: PayslipOvertimeLine[];
  /** Chỉ có ở bảng kê của kế toán. */
  user?: {
    id: string;
    name: string;
    employee_code: string | null;
    department: string | null;
  };
}

export interface PayslipSheet {
  period: string;
  is_final: boolean;
  payslips: PayslipItem[];
  total: number;
  limit: number;
  /** Tổng chi của kỳ, cộng từ đúng những dòng đang hiện. */
  net_total: Money;
}

/** `2026-08` thành `tháng 08/2026` — cách người ta đọc một kỳ lương. */
export function formatPeriod(period: string): string {
  const [nam, thang] = period.split("-");

  return thang === undefined ? period : `tháng ${thang}/${nam}`;
}

/** `465` thành `7h45`. Số phút của phiếu lương đọc như giờ công. */
export { formatMinutes } from "@/features/attendance/types/attendance";
