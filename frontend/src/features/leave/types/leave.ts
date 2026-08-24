/** Khớp với App\Http\Controllers\Api\V1\Leave. */

export type LeaveStatusValue =
  "pending" | "approved" | "rejected" | "cancelled";

export interface LeaveTypeOption {
  value: string;
  label: string;
}

export interface LeaveRequestItem {
  id: string;
  type: string;
  type_label: string;
  start_date: string;
  end_date: string;
  /** Số ngày trên lịch, không trừ cuối tuần và ngày lễ. */
  days: number;
  reason: string;
  status: LeaveStatusValue;
  status_label: string;
  /** Chỉ đơn đang chờ mới rút được. */
  is_editable: boolean;
  created_at: string | null;
  /** `null` = chưa ai xử lý. */
  review: { by: string | null; at: string; note: string | null } | null;
  /** Chỉ có ở hộp duyệt của quản lý. */
  user?: { id: string; name: string; department: string | null };
}

export interface MyLeave {
  requests: LeaveRequestItem[];
  total: number;
  types: LeaveTypeOption[];
  /**
   * Khoảng ngày nộp được, **do server nói ra**.
   *
   * Không tự tính từ `new Date()`: đồng hồ máy người dùng có thể lệch, và múi
   * giờ trình duyệt có thể không phải giờ Việt Nam khi nhân viên đi công tác.
   */
  window: { earliest: string; latest: string; max_days: number };
}

export interface TeamLeave {
  requests: LeaveRequestItem[];
  status: LeaveStatusValue;
  /** Số đơn đang chờ — luôn đúng, kể cả khi đang xem tab khác. */
  pending_count: number;
  total: number;
  can_approve: boolean;
}

/** Màu của từng trạng thái. Chờ duyệt là thứ DUY NHẤT cần ai đó nhìn tới. */
export const LEAVE_STATUS_TONE: Record<LeaveStatusValue, string> = {
  pending: "border-notice-line bg-notice-surface text-notice",
  approved: "border-tone-line bg-tone-surface text-tone-ink",
  rejected: "border-danger-line bg-danger-surface text-danger",
  cancelled: "border-line bg-paper-sunken text-ink-faint",
};
