/** Khớp với App\Http\Controllers\Api\V1\Attendance. */

export type AttendanceDecisionValue = "confirmed" | "waived" | "flagged";

export const DECISIONS: {
  value: AttendanceDecisionValue;
  label: string;
  description: string;
}[] = [
  {
    value: "confirmed",
    label: "Ghi nhận",
    description: "Số hệ thống đo được là đúng, không cần chỉnh.",
  },
  {
    value: "waived",
    label: "Bỏ qua",
    description: "Giờ thấp nhưng có lý do chính đáng. Ấn định số giờ nếu cần.",
  },
  {
    value: "flagged",
    label: "Cần hỏi lại",
    description: "Chưa kết luận, đánh dấu để trao đổi với nhân viên.",
  },
];

/**
 * Kết quả đối chiếu giờ công với báo cáo ngày.
 *
 * Khớp với `App\Support\Enums\ReportMatch`. Chỉ `missing_report` là thứ
 * cần ai đó nhìn tới — xem chú thích ở lớp PHP để biết vì sao `report_only`
 * cố ý không được tính là bất thường.
 */
export type ReportMatchValue =
  | "ok"
  | "missing_report"
  | "report_only"
  | "idle"
  | "holiday"
  /** Ngày nghỉ có đơn ĐÃ DUYỆT — được miễn chấm công. */
  | "on_leave";

export interface AttendanceCell {
  minutes: number;
  /** Số hệ thống đo được, giữ nguyên kể cả khi người quản lý đã ấn định số khác. */
  measured_minutes: number;
  session_count: number;
  first_seen_at?: string | null;
  last_seen_at?: string | null;
  decision: AttendanceDecisionValue | null;
  decision_label: string | null;
  reason: string | null;
  has_report: boolean;
  report_match: ReportMatchValue;
  report_match_label: string;
  /**
   * Số phút đến muộn so với giờ vào ca. `0` = đúng giờ hoặc không có phiên nào.
   *
   * Đứng CẠNH `minutes` chứ không trừ vào nó: người đến muộn mà làm bù tới tối
   * vẫn được tính đủ giờ. Hai con số trả lời hai câu hỏi khác nhau.
   */
  late_minutes: number;
  /** Có đơn xin đi muộn đã duyệt bao được ngày này không. */
  late_excused: boolean;
}

export interface MyAttendance {
  month: string;
  days: string[];
  holidays: Record<string, string>;
  cells: Record<string, AttendanceCell>;
  total_minutes: number;
  days_worked: number;
  /** Số ngày có giờ làm mà chưa nộp báo cáo, chưa ai xử lý. */
  missing_report_days: number;
}

export interface AttendanceRow {
  user: { id: string; name: string; department: string | null };
  cells: Record<string, AttendanceCell>;
  total_minutes: number;
  days_worked: number;
  missing_report_days: number;
}

export interface TeamAttendance {
  month: string;
  days: string[];
  holidays: Record<string, string>;
  rows: AttendanceRow[];
  can_review: boolean;
}

export interface WorkSessionRow {
  started_at: string;
  ended_at: string;
  minutes: number;
  source: string;
}

export interface WorkDayDetail {
  work_date: string;
  user: { id: string; name: string };
  sessions: WorkSessionRow[];
  /** Số lần người này đụng vào công việc trong ngày — thứ số giờ không nói được. */
  task_activity_count: number;
}

/** `372` → `"6h12"`. Giờ công đọc bằng giờ và phút, không bằng số thập phân. */
export function formatMinutes(minutes: number): string {
  if (minutes <= 0) return "—";

  const gio = Math.floor(minutes / 60);
  const phut = minutes % 60;

  if (gio === 0) return `${phut}p`;

  return phut === 0 ? `${gio}h` : `${gio}h${String(phut).padStart(2, "0")}`;
}
