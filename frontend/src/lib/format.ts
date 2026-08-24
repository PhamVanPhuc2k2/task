/**
 * Định dạng ngày giờ và số cho người dùng Việt Nam.
 *
 * Backend luôn trả về UTC kèm offset; việc đổi sang giờ Việt Nam làm ở đây,
 * tầng hiển thị. Xem README, "Quy ước dữ liệu, thời gian & tiền tệ".
 *
 * Múi giờ ghi cứng `Asia/Ho_Chi_Minh` chứ không lấy theo máy: một nhân viên
 * mở máy đang để múi giờ khác phải thấy đúng hạn theo giờ công ty, không phải
 * theo giờ nơi họ đang ngồi.
 */

const TIMEZONE = "Asia/Ho_Chi_Minh";
const LOCALE = "vi-VN";

/** `dd/mm/yyyy` — chuẩn quen thuộc ở Việt Nam. */
export function formatDate(iso: string | null | undefined): string {
  if (!iso) return "—";

  return new Intl.DateTimeFormat(LOCALE, {
    timeZone: TIMEZONE,
    day: "2-digit",
    month: "2-digit",
    year: "numeric",
  }).format(new Date(iso));
}

/** `dd/mm/yyyy HH:mm`. */
export function formatDateTime(iso: string | null | undefined): string {
  if (!iso) return "—";

  return new Intl.DateTimeFormat(LOCALE, {
    timeZone: TIMEZONE,
    day: "2-digit",
    month: "2-digit",
    year: "numeric",
    hour: "2-digit",
    minute: "2-digit",
  }).format(new Date(iso));
}

/** `Thứ Hai, 10 tháng 8` — dùng cho tiêu đề trang. */
export function formatLongDate(date: Date = new Date()): string {
  return new Intl.DateTimeFormat(LOCALE, {
    timeZone: TIMEZONE,
    weekday: "long",
    day: "numeric",
    month: "long",
  }).format(date);
}

/**
 * Khoảng cách tới hạn, nói bằng lời: "trễ 3 ngày", "còn 2 ngày", "hôm nay".
 *
 * Người đọc cần biết *còn bao lâu*, không cần đọc rồi tự trừ ngày trong đầu.
 */
export function formatDueDistance(iso: string | null | undefined): string {
  if (!iso) return "Không hạn";

  const ngay = ngayTheoGioVN(new Date(iso));
  const homNay = ngayTheoGioVN(new Date());
  const lech = Math.round((ngay - homNay) / 86_400_000);

  if (lech === 0) return "Hôm nay";
  if (lech === 1) return "Ngày mai";
  if (lech === -1) return "Hôm qua";
  if (lech < 0) return `Trễ ${Math.abs(lech)} ngày`;
  if (lech <= 30) return `Còn ${lech} ngày`;

  return formatDate(iso);
}

/**
 * Mốc thời gian trong quá khứ, nói bằng lời: "5 phút trước", "3 ngày trước".
 * Quá 7 ngày thì hiện ngày cụ thể — "47 ngày trước" không ai nhẩm ra được.
 */
export function formatTimeAgo(iso: string | null | undefined): string {
  if (!iso) return "—";

  const giay = Math.round((Date.now() - new Date(iso).getTime()) / 1000);

  if (giay < 60) return "Vừa xong";
  if (giay < 3600) return `${Math.floor(giay / 60)} phút trước`;
  if (giay < 86_400) return `${Math.floor(giay / 3600)} giờ trước`;
  if (giay < 604_800) return `${Math.floor(giay / 86_400)} ngày trước`;

  return formatDateTime(iso);
}

/** Số giờ DECIMAL từ backend về dạng đọc được: "7.50" → "7,5 giờ". */
export function formatHours(value: string | null | undefined): string {
  if (!value) return "—";

  const so = Number(value);
  if (Number.isNaN(so)) return "—";

  return `${so.toLocaleString(LOCALE, { maximumFractionDigits: 2 })} giờ`;
}

/**
 * ISO 8601 → giá trị cho ô `<input type="datetime-local">`.
 *
 * Ô đó chỉ nhận `yyyy-MM-ddTHH:mm` không kèm múi giờ. Phải dựng chuỗi theo giờ
 * Việt Nam chứ KHÔNG theo giờ máy: cả ứng dụng hiển thị giờ Việt Nam, nên một
 * người mở máy đang để múi giờ Nhật sẽ thấy hạn hiện 22:00 ở mọi chỗ nhưng ô
 * sửa lại hiện 00:00 — và bấm lưu là hạn nhảy đi hai tiếng.
 *
 * Backend hiểu chuỗi không có offset theo giờ Việt Nam, khớp với hàm này —
 * xem `App\Support\Time\IncomingDateTime`.
 */
export function toDatetimeLocalValue(iso: string | null | undefined): string {
  if (!iso) return "";

  const phan = new Intl.DateTimeFormat("en-CA", {
    timeZone: TIMEZONE,
    year: "numeric",
    month: "2-digit",
    day: "2-digit",
    hour: "2-digit",
    minute: "2-digit",
    hourCycle: "h23",
  }).formatToParts(new Date(iso));

  const lay = (loai: Intl.DateTimeFormatPartTypes) =>
    phan.find((p) => p.type === loai)?.value ?? "00";

  return `${lay("year")}-${lay("month")}-${lay("day")}T${lay("hour")}:${lay("minute")}`;
}

/** Chữ cái đầu của tên, dùng cho avatar chữ. */
export function initials(name: string): string {
  const phan = name.trim().split(/\s+/);
  const dau = phan[0]?.[0] ?? "";
  const cuoi = phan.length > 1 ? (phan[phan.length - 1]?.[0] ?? "") : "";

  return (dau + cuoi).toUpperCase() || "?";
}

/** Tên gọi thân mật: lấy chữ cuối trong tên đầy đủ kiểu Việt Nam. */
export function shortName(name: string): string {
  return name.trim().split(/\s+/).slice(-1)[0] ?? name;
}

/**
 * Nửa đêm của một thời điểm theo giờ Việt Nam, tính bằng mili giây.
 *
 * Không dùng `setHours(0,0,0,0)` vì hàm đó theo múi giờ của MÁY: cùng một
 * task, máy để giờ Nhật và máy để giờ Việt Nam sẽ ra số ngày lệch nhau.
 */
function ngayTheoGioVN(date: Date): number {
  const phan = new Intl.DateTimeFormat("en-CA", {
    timeZone: TIMEZONE,
    year: "numeric",
    month: "2-digit",
    day: "2-digit",
  }).format(date);

  return Date.parse(`${phan}T00:00:00Z`);
}
