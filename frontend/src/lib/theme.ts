/**
 * Chọn giao diện sáng / tối.
 *
 * ## Vì sao không dùng `@media (prefers-color-scheme: dark)`
 *
 * Bản trước để CSS tự đổi theo cài đặt máy. Nghe thì gọn, nhưng nó bỏ mất một
 * việc: **người dùng không có cách nào chọn**. Máy để sáng mà muốn app tối thì
 * chịu — phải đi đổi cài đặt của cả hệ điều hành.
 *
 * Nên biến trạng thái được đưa lên `<html data-theme>`, và CSS chỉ đọc thuộc
 * tính đó. Ba lựa chọn:
 *
 *   - `light` / `dark` — người dùng quyết, máy nói gì cũng kệ
 *   - `system`         — theo máy, và đổi ngay khi máy đổi (xem `theoDoiHeMay`)
 *
 * `system` là mặc định: nó giữ nguyên hành vi cũ cho người không quan tâm.
 *
 * ## Cái giá phải trả, nói rõ
 *
 * Cách này cần JavaScript chạy thì mới có màu tối. Chấp nhận được vì đây là app
 * nội bộ sau đăng nhập, vốn đã không chạy được nếu tắt JS. Đổi lại là người
 * dùng có quyền chọn — và đó là thứ đáng hơn.
 */

export type ThemeChoice = "light" | "dark" | "system";

export const THEME_CHOICES: readonly ThemeChoice[] = [
  "light",
  "dark",
  "system",
];

export const THEME_LABELS: Record<ThemeChoice, string> = {
  light: "Sáng",
  dark: "Tối",
  system: "Theo máy",
};

export const THEME_STORAGE_KEY = "explus:theme";

/** Sự kiện tự đặt, để mọi chỗ đang hiện lựa chọn cùng cập nhật một lượt. */
export const THEME_EVENT = "explus:theme-change";

const TRUY_VAN_TOI = "(prefers-color-scheme: dark)";

export function docLuaChon(): ThemeChoice {
  try {
    const v = localStorage.getItem(THEME_STORAGE_KEY);
    return v === "light" || v === "dark" ? v : "system";
  } catch {
    // Chế độ riêng tư của Safari ném lỗi khi đọc localStorage. Không được để
    // cả app trắng màn hình chỉ vì không đọc được một tuỳ chọn màu sắc.
    return "system";
  }
}

/** Quy `system` về màu thật sẽ được vẽ. */
export function quyDoi(chon: ThemeChoice): "light" | "dark" {
  if (chon !== "system") return chon;
  return window.matchMedia(TRUY_VAN_TOI).matches ? "dark" : "light";
}

export function apDung(chon: ThemeChoice): void {
  document.documentElement.dataset.theme = quyDoi(chon);
}

export function luuLuaChon(chon: ThemeChoice): void {
  try {
    if (chon === "system") {
      localStorage.removeItem(THEME_STORAGE_KEY);
    } else {
      localStorage.setItem(THEME_STORAGE_KEY, chon);
    }
  } catch {
    // Không lưu được thì lựa chọn chỉ sống hết phiên này. Vẫn tốt hơn là ném
    // lỗi ra giữa lúc người dùng đang bấm nút.
  }

  apDung(chon);
  window.dispatchEvent(new CustomEvent(THEME_EVENT));
}

/**
 * Nghe máy đổi sáng/tối. Chỉ có tác dụng khi đang để `system` — người đã tự
 * chọn thì máy đổi cũng không được đè lên lựa chọn của họ.
 */
export function theoDoiHeMay(): () => void {
  const mq = window.matchMedia(TRUY_VAN_TOI);
  const xuLy = () => {
    if (docLuaChon() === "system") apDung("system");
  };

  mq.addEventListener("change", xuLy);
  return () => mq.removeEventListener("change", xuLy);
}

/**
 * Đoạn mã chạy TRƯỚC khi trang vẽ khung hình đầu tiên.
 *
 * Bắt buộc phải đồng bộ và nằm thẳng trong `<head>`. Nếu để React đặt
 * `data-theme` sau khi hydrate thì người dùng chế độ tối sẽ thấy **một nháy
 * trắng** mỗi lần tải trang — lỗi nhỏ nhưng chói mắt, và là thứ đầu tiên người
 * ta nhận ra ở một app làm ẩu.
 *
 * Viết bằng chuỗi thay vì import hàm ở trên: nó phải chạy được khi chưa có
 * bundle nào được tải.
 */
export const SCRIPT_CHONG_NHAY = `(function(){try{var c=localStorage.getItem("${THEME_STORAGE_KEY}");var d=c==="dark"||(c!=="light"&&matchMedia("${TRUY_VAN_TOI}").matches);document.documentElement.dataset.theme=d?"dark":"light"}catch(e){document.documentElement.dataset.theme="light"}})()`;
