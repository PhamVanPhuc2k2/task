/** Khớp với App\Http\Controllers\Api\V1\Settings. */

export type SettingType = "text" | "integer" | "boolean";

/** Nhóm để chia trang thành từng mục. */
export type SettingGroup = "branding" | "attendance" | "report" | "leave";

/**
 * Mô tả một cài đặt, **do server khai**.
 *
 * Giao diện dựng form từ danh sách này thay vì tự viết lại. Thêm một cài đặt mới
 * ở backend thì form tự có — không phải sửa hai chỗ rồi quên một chỗ.
 */
export interface SettingField {
  key: string;
  label: string;
  type: SettingType;
  group: SettingGroup;
  default: string | number | boolean | null;
}

export type SettingValue = string | number | boolean | null;

export interface SiteSettingsData {
  values: Record<string, SettingValue>;
  fields: SettingField[];
}

/** Nhận diện công ty — đường công khai, trang đăng nhập cũng lấy được. */
export interface SiteBranding {
  company_name: string;
  company_short_name: string;
  /** `null` = chưa đặt logo, giao diện dùng dấu cộng vẽ tay. */
  logo_url: string | null;
}

export const GROUP_LABELS: Record<SettingGroup, string> = {
  branding: "Nhận diện",
  attendance: "Ca làm & chấm công",
  report: "Báo cáo ngày",
  leave: "Nghỉ phép",
};

export const GROUP_HINTS: Record<SettingGroup, string> = {
  branding: "Tên và logo hiện trên trang đăng nhập và đầu trang.",
  attendance:
    "Đổi ca làm là đổi cách tính đi muộn cho cả công ty. Số giờ đã ghi nhận không bị tính lại — chỉ các ngày sau đó dùng mốc mới.",
  report: "Giờ nhắc và số ngày được nộp báo cáo bù.",
  leave: "Khoảng ngày nhân viên được xin nghỉ, và độ dài tối đa một đơn.",
};
