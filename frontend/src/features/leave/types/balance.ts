/** Khớp với App\Http\Controllers\Api\V1\Leave\*LeaveBalance*. */

export interface LeaveBalanceItem {
  year: number;
  /** Số được hưởng, đã áp ghi đè nếu có. */
  entitled_days: number;
  /**
   * Số hệ thống tự tính theo Điều 113 và 114, giữ lại kể cả khi đã bị ghi đè.
   *
   * Nhờ nó màn hình nói được *"tự tính 12, nhân sự đặt 15"* — không có thì ba
   * tháng sau không ai trả lời được câu "con số này đến từ đâu".
   */
  computed_entitled_days: number;
  is_overridden: boolean;
  carried_over_days: number;
  /** Điều chỉnh tay. **Được phép âm.** */
  adjustment_days: number;
  total_days: number;
  /** Đã dùng — tính theo NGÀY CÔNG, và gồm cả đơn đang chờ duyệt. */
  used_days: number;
  /**
   * Còn lại. **Được phép âm** — ai đó đã duyệt vượt quỹ.
   *
   * Kẹp về 0 khi hiển thị sẽ giấu mất đúng cái tình huống cần người nhìn tới.
   */
  remaining_days: number;
  note: string | null;
  /** Chỉ có ở màn của nhân sự và màn quỹ của tôi. */
  previous_remaining_days?: number;
  /** Chỉ có ở bảng của nhân sự. */
  user?: {
    id: string;
    name: string;
    department: string | null;
    joined_at: string | null;
  };
}

/** Chính sách hiện hành, do server nói ra để màn hình giải thích được con số. */
export interface LeavePolicy {
  base_days: number;
  seniority_step_years: number;
  seniority_extra_days: number;
  carry_over_max_days: number;
}

export interface TeamLeaveBalances {
  year: number;
  balances: LeaveBalanceItem[];
  total: number;
  limit: number;
  /** Xem được không có nghĩa là sửa được — `leave.balance.manage` là quyền riêng. */
  can_manage: boolean;
  policy: LeavePolicy;
}

/**
 * `2.5` thành `2,5` và `3.0` thành `3` — cách người Việt viết số ngày.
 *
 * Dấu phẩy chứ không dấu chấm: "2.5 ngày" đọc ra hai nghìn năm trăm với người
 * quen định dạng Việt Nam. Giữ dấu trừ nguyên vẹn — số âm ở đây có nghĩa.
 */
export function formatDays(days: number): string {
  return Number.isInteger(days)
    ? String(days)
    : days.toFixed(1).replace(".", ",");
}
