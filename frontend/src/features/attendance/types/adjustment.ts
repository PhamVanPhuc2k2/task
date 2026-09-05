/** Khớp với App\Http\Controllers\Api\V1\Attendance\*Adjustment*. */

import type { RequestStatusValue } from "@/components/ui/pill";

export type AdjustmentStatusValue = RequestStatusValue;

export interface AdjustmentItem {
  id: string;
  /** Ngày công được giải trình, `YYYY-MM-DD` theo lịch Việt Nam. */
  work_date: string;
  reason: string;
  /**
   * Số phút người nộp cho là đúng.
   *
   * `null` nghĩa là *"xin bỏ qua ngày này, tôi không đề nghị con số nào"* —
   * trường hợp thường gặp nhất, vì người đi gặp khách cả ngày không đếm phút.
   * Hiện nó khác hẳn `0`: `0` là một lời khai, `null` là không khai.
   */
  requested_minutes: number | null;
  status: AdjustmentStatusValue;
  status_label: string;
  /** Chỉ đơn đang chờ mới rút được. */
  is_editable: boolean;
  created_at: string | null;
  /** `null` = chưa ai xử lý. */
  review: {
    by: string | null;
    at: string;
    note: string | null;
    /**
     * Số phút **người duyệt** chốt — có thể khác `requested_minutes`.
     *
     * Hiện riêng chứ không đè lên số đã xin: người nộp cần thấy ngay là con số
     * đã bị điều chỉnh, chứ không phải tự đi so lại bảng công.
     */
    approved_minutes: number | null;
  } | null;
  /** Chỉ có ở hộp duyệt của quản lý. */
  user?: { id: string; name: string; department: string | null };
}

export interface MyAdjustments {
  requests: AdjustmentItem[];
  total: number;
  limit: number;
  /**
   * Ngày muộn nhất nộp được, **do server nói ra**.
   *
   * Không tự tính từ `new Date()`: đồng hồ máy người dùng có thể lệch, và múi
   * giờ trình duyệt có thể không phải giờ Việt Nam khi nhân viên đi công tác.
   */
  latest_date: string;
}

export interface TeamAdjustments {
  requests: AdjustmentItem[];
  total: number;
  limit: number;
  pending: number;
}

/**
 * `480` thành `8h`, `450` thành `7h30`.
 *
 * Dùng lại `formatMinutes` của bảng công thay vì viết bản riêng: hai chỗ nói về
 * cùng một đại lượng, và hai cách viết khác nhau cho cùng một số phút là thứ
 * khiến người ta tưởng đang xem hai con số khác nhau.
 */
export { formatMinutes } from "./attendance";
