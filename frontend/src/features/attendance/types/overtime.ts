/** Khớp với App\Http\Controllers\Api\V1\Attendance\*Overtime*. */

import type { RequestStatusValue } from "@/components/ui/pill";

/** Loại ngày trên lịch công ty. Khớp App\Support\Enums\DayKind. */
export type DayKindValue = "working" | "weekly_rest" | "holiday";

export interface OvertimeItem {
  id: string;
  work_date: string;
  /** `HH:MM` giờ Việt Nam. */
  start_time: string;
  end_time: string;
  /** Số phút đã đăng ký. */
  minutes: number;
  reason: string;

  day_kind: DayKindValue;
  day_kind_label: string;

  /** Hệ số phần trăm — 150, 200 hoặc 300. */
  rate_percent: number;
  /**
   * `false` = con số này còn đổi được.
   *
   * Hệ số chỉ đóng băng lúc DUYỆT. Trước đó nó được tính sống theo lịch hiện
   * tại, và lịch có thể đổi — nhân sự nhập thêm một ngày lễ, hoặc công ty đổi
   * lịch tuần. Giao diện phải nói "dự kiến" khi cờ này còn `false`.
   */
  rate_is_final: boolean;

  status: RequestStatusValue;
  status_label: string;
  is_editable: boolean;
  created_at: string | null;

  review: {
    by: string | null;
    at: string;
    note: string | null;
    /** Số phút NGƯỜI DUYỆT chốt — có thể ít hơn số đã đăng ký. */
    approved_minutes: number | null;
  } | null;

  /** Chỉ có ở hộp duyệt của quản lý. */
  user?: { id: string; name: string; department: string | null };
}

/** Chính sách hiện hành, do server nói ra. */
export interface OvertimePolicy {
  rate_working_percent: number;
  rate_weekly_rest_percent: number;
  rate_holiday_percent: number;
  /** 0 = tắt trần. */
  max_minutes_per_day: number;
  max_minutes_per_month: number;
  max_minutes_per_year: number;
}

export interface MyOvertime {
  requests: OvertimeItem[];
  total: number;
  limit: number;
  /**
   * Khoảng ngày đăng ký được, **do server nói ra**.
   *
   * Không tự tính từ `new Date()`: đồng hồ máy người dùng có thể lệch, và múi
   * giờ trình duyệt có thể không phải giờ Việt Nam khi nhân viên đi công tác.
   */
  window: { earliest: string; latest: string };
  policy: OvertimePolicy;
  /** Số phút đã đăng ký tháng này và năm nay — để đối chiếu với ba trần. */
  used: { month: number; year: number };
}

export interface TeamOvertime {
  requests: OvertimeItem[];
  total: number;
  limit: number;
  pending: number;
}

/**
 * Hệ số của một ngày cụ thể, hỏi trước khi đăng ký.
 *
 * `shift` là `null` với ngày nghỉ và ngày lễ — hôm đó không ai đi làm nên mọi
 * mốc giờ đều là làm thêm, và ô nhập giờ không bị chặn khoảng nào.
 */
export interface OvertimePreview {
  work_date: string;
  day_kind: DayKindValue;
  day_kind_label: string;
  rate_percent: number;
  rate_is_final: boolean;
  shift: { start: string; end: string } | null;
}

/** Màu của từng loại ngày. Ngày càng đắt thì càng nổi. */
export const DAY_KIND_TONE: Record<DayKindValue, string> = {
  working: "border-line bg-paper-sunken text-ink-soft",
  weekly_rest: "border-notice-line bg-notice-surface text-notice",
  holiday: "border-danger-line bg-danger-surface text-danger",
};

/** `480` thành `8h`, `450` thành `7h30`. Dùng lại phép định dạng của bảng công. */
export { formatMinutes } from "./attendance";
