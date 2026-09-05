/** Khớp với App\Http\Controllers\Api\V1\Attendance\{Period,ClosePeriod,ReopenPeriod}Controller. */

export interface PeriodItem {
  /** Kỳ công, dạng `YYYY-MM`. */
  period: string;
  status: "closed" | "open";
  status_label: string;
  is_locked: boolean;
  closed_at: string;
  closed_by: string | null;
  reopened_at: string | null;
  reopened_by: string | null;
  reopen_reason: string | null;
}

/**
 * Kỳ mà nút "Chốt sổ" sẽ nhắm tới.
 *
 * `null` nghĩa là không còn gì để chốt — mọi kỳ đã kết thúc đều đã chốt.
 */
export interface ClosablePeriod {
  period: string;
  /**
   * Số đơn còn chờ duyệt, theo từng loại.
   *
   * Khoá là **nhãn tiếng Việt do server đặt** chứ không phải mã cố định, nên
   * giao diện chỉ việc duyệt qua và in ra. Thêm một loại đơn ở đợt sau thì màn
   * này tự đúng, không phải sửa gì.
   */
  pending: Record<string, number>;
  /** Không còn đơn nào treo — bấm chốt được. */
  ready: boolean;
}

export interface PeriodList {
  periods: PeriodItem[];
  /**
   * Hai quyền TÁCH NHAU, và giao diện hỏi server thay vì tự suy từ danh sách
   * quyền: giám đốc chốt và mở khoá, admin chỉ chốt.
   */
  can_close: boolean;
  can_reopen: boolean;
  closable: ClosablePeriod | null;
}

/** `2026-08` thành `tháng 08/2026` — cách người ta đọc một kỳ công. */
export function formatPeriod(period: string): string {
  const [nam, thang] = period.split("-");

  return thang === undefined ? period : `tháng ${thang}/${nam}`;
}
