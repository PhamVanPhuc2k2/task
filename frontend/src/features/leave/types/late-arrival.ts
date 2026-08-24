/** Khớp với App\Http\Controllers\Api\V1\Leave\*LateArrival*. */

import type { LeaveStatusValue } from "./leave";

export interface LateArrivalItem {
  id: string;
  /** Ngày xin đi muộn, `YYYY-MM-DD` theo lịch Việt Nam. */
  date: string;
  /** Giờ dự kiến đến, `HH:MM` giờ Việt Nam. */
  expected_arrival: string;
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

export interface MyLateArrivals {
  requests: LateArrivalItem[];
  total: number;
  limit: number;
  /**
   * Khoảng ngày nộp được, **do server nói ra**.
   *
   * Không tự tính từ `new Date()`: đồng hồ máy người dùng có thể lệch, và múi
   * giờ trình duyệt có thể không phải giờ Việt Nam khi nhân viên đi công tác.
   */
  window: { earliest: string; latest: string };
  /**
   * Ca làm, cũng do server nói ra.
   *
   * Hardcode "08:15" ở đây là mở đường cho hai nơi nói hai giờ khác nhau ngay
   * sau lần công ty đổi ca đầu tiên.
   */
  shift: { morning_start: string; end: string };
}

export interface TeamLateArrivals {
  requests: LateArrivalItem[];
  total: number;
  limit: number;
  pending: number;
}
