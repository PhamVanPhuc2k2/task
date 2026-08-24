/** Khớp với App\Http\Controllers\Api\V1\Attendance\AttendanceTimelineController. */

/** Một đoạn trên trục thời gian. Giờ dạng `HH:MM`, giờ Việt Nam. */
export interface TimelineSegment {
  start: string;
  end: string;
  minutes: number;
  /**
   * Phần khe rơi vào giờ nghỉ trưa. Chỉ có ở `gaps`.
   *
   * Khe vẫn được vẽ nguyên vẹn — người xem cần thấy đúng khoảng trống có thật.
   * Chỉ có phép ĐẾM là tách đôi: phần trưa không bị gọi là ngồi không.
   */
  lunch_minutes?: number;
}

export interface TimelineRow {
  user: { id: string; name: string; department: string | null };
  /** Các phiên có tương tác thật. */
  sessions: TimelineSegment[];
  /**
   * Khoảng lặng **giữa** hai phiên — thời gian không có tương tác nào.
   *
   * Chỉ khe ở giữa, không tính trước phiên đầu và sau phiên cuối: chưa tới giờ
   * làm và đã nghỉ thì không phải "ngồi không".
   *
   * Mỗi khe đều dài hơn 10 phút, vì nhịp tim cách nhau dưới ngưỡng đó đã được
   * gộp vào cùng một phiên ngay từ lúc ghi.
   */
  gaps: TimelineSegment[];
  worked_minutes: number;
  idle_minutes: number;
  /**
   * Phần khoảng lặng rơi vào giờ nghỉ trưa.
   *
   * Tách khỏi `idle_minutes` chứ không gộp: nghỉ trưa là quyền, không phải dấu
   * hiệu lười. Gộp vào thì ngày nào cũng có một khoảng vàng 90 phút cho mọi
   * người — cờ bật cho tất cả là cờ vô nghĩa.
   */
  lunch_minutes: number;
  first_seen: string | null;
  last_seen: string | null;
  late_minutes: number;
  late_excused: boolean;
  on_leave: boolean;
}

export interface DayTimeline {
  date: string;
  /**
   * Khung giờ vẽ, **do dữ liệu quyết**.
   *
   * Mặc định phủ ca làm, tự nới ra khi có người làm ngoài ca. Cắt cứng 08h–18h
   * thì phiên lúc 21h biến mất khỏi màn hình mà không có gì báo.
   */
  range: { start: string; end: string };
  shift: {
    morning_start: string;
    lunch_start: string;
    lunch_end: string;
    end: string;
  };
  rows: TimelineRow[];
}

/** `HH:MM` thành số phút tính từ nửa đêm. */
export function toMinutes(hhmm: string): number {
  const [h, m] = hhmm.split(":").map(Number);

  return (h ?? 0) * 60 + (m ?? 0);
}
