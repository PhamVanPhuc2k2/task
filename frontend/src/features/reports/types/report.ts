/** Khớp với App\Http\Controllers\Api\V1\Reports. */

export type ReportStatusValue = "draft" | "submitted" | "reviewed";

export interface ReportTask {
  id: string;
  title: string;
}

export interface DailyReport {
  id: string;
  report_date: string;
  content: string;
  status: ReportStatusValue;
  status_label: string;
  is_editable: boolean;
  submitted_at: string | null;
  author: { id: string; name: string; department: string | null } | null;
  tasks: ReportTask[];
  review: { by: string | null; at: string; note: string | null } | null;
}

export interface MyReports {
  month: string;
  days: string[];
  reports: DailyReport[];
  submitted_count: number;
  /**
   * Khoảng ngày còn nộp được, **do server nói ra**.
   *
   * Không tự tính từ `new Date()`: đồng hồ máy người dùng có thể lệch, và múi
   * giờ trình duyệt có thể không phải giờ Việt Nam khi nhân viên đi công tác.
   * Tự tính thì giao diện mở ô soạn cho một ngày mà API sẽ từ chối.
   */
  window: { earliest: string; latest: string };
}

export interface TeamReportRow {
  user: { id: string; name: string; department: string | null };
  /** `null` = chưa nộp. Bản nháp cũng tính là chưa nộp. */
  report: DailyReport | null;
  /** Có bản nháp nhưng chưa nộp — khác hẳn với chưa động gì. */
  has_draft: boolean;
}

export interface TeamReports {
  date: string;
  rows: TeamReportRow[];
  submitted: number;
  total: number;
  can_review: boolean;
}

/** Hôm nay theo giờ Việt Nam, dạng `Y-m-d`. */
export function todayInVietnam(): string {
  return new Intl.DateTimeFormat("en-CA", {
    timeZone: "Asia/Ho_Chi_Minh",
  }).format(new Date());
}
