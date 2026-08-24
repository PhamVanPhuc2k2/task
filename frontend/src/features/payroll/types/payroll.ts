/** Khớp với App\Http\Controllers\Api\V1\Payroll\PayrollController. */

/**
 * Số tiền luôn là **chuỗi**, không phải number.
 *
 * Backend trả nguyên dạng DECIMAL. Ép sang `number` ở đây là ném vào float 64
 * bit của JavaScript, nơi `12500000.10 + 2000000.20` ra `14500000.299999999`.
 * Cộng dồn nhiều dòng thì sai số tích lại — mà đây là tiền lương, kế toán sẽ
 * cộng tay và hỏi vì sao lệch.
 */
export type Money = string;

export interface SalaryRecord {
  id: string;
  effective_from: string;
  effective_to: string | null;
  is_current: boolean;
  base_salary: Money;
  allowance: Money;
  total: Money;
  currency: string;
  reason: string;
  author?: { id: string; name: string } | null;
  created_at: string | null;
}

export interface PayrollRow {
  user: {
    id: string;
    name: string;
    employee_code: string | null;
    department: string | null;
  };
  /** `null` = chưa được đặt mức lương nào. Trạng thái hợp lệ, không phải lỗi. */
  salary: {
    base_salary: Money;
    allowance: Money;
    total: Money;
    currency: string;
    effective_from: string;
  } | null;
}

/**
 * Có chữ ký chỉ mục để truyền thẳng vào `api.get({ query })` — cùng khuôn với
 * `EmployeeFilters`. Thiếu nó thì TypeScript từ chối gán một interface có
 * trường cố định vào `Record<string, …>`.
 */
export interface PayrollFilters {
  [key: string]: string | number | undefined;
  search?: string;
  department_id?: string;
  page?: number;
}

/**
 * `"15000000.00"` → `"15.000.000 ₫"`.
 *
 * Cắt phần thập phân khi bằng 0: lương Việt Nam gần như luôn tròn đồng, và
 * `15.000.000,00 ₫` chỉ làm bảng khó đọc hơn.
 *
 * Dùng `Intl` trên phần nguyên tách bằng chuỗi, KHÔNG `Number(value)` — số
 * lương vẫn nằm dưới ngưỡng an toàn của float, nhưng quy tắc "không đưa tiền
 * qua number" giữ cho mọi chỗ nhất quán và không phải xét từng chỗ một.
 */
export function formatMoney(value: Money | null | undefined): string {
  if (value === null || value === undefined || value === "") return "—";

  const [nguyen = "0", thapPhan = ""] = value.split(".");
  const nhom = nguyen.replace(/\B(?=(\d{3})+(?!\d))/g, ".");
  const le = /^0*$/.test(thapPhan) ? "" : `,${thapPhan}`;

  return `${nhom}${le} ₫`;
}

/** `"15000000.00"` → `"15.000.000"`, không có ký hiệu tiền — dùng cho ô nhập. */
export function toInputValue(value: Money | null | undefined): string {
  if (value === null || value === undefined) return "";

  const [nguyen = "0", thapPhan = ""] = value.split(".");

  return /^0*$/.test(thapPhan) ? nguyen : `${nguyen}.${thapPhan}`;
}
