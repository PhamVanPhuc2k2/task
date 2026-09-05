import type { ReactNode } from "react";

import { cn } from "@/lib/cn";
import { initials } from "@/lib/format";

/**
 * Viên nhãn tròn có chấm màu.
 *
 * Thuần giao diện — không biết trạng thái task hay dự án là gì. Nhãn mang
 * nghiệp vụ nằm ở `features/*`, vì `components/ui` không được import feature
 * (luật no-restricted-imports trong eslint.config.mjs).
 *
 * Luôn có CHỮ bên cạnh chấm màu, không bao giờ chỉ có màu: khoảng 8% nam giới
 * bị mù màu đỏ–lục và sẽ không phân biệt được hai trạng thái chỉ khác màu.
 */
export function Pill({
  dotClass,
  tone = "border-line bg-paper-raised text-ink-soft",
  className,
  children,
}: {
  dotClass?: string;
  /**
   * Bộ ba màu viền, nền và chữ.
   *
   * Là tham số RIÊNG chứ không gộp vào `className`, và lớp cơ sở tuyệt đối
   * không chứa màu nào — vì `cn` chỉ nối chuỗi chứ không giải quyết xung đột
   * như `tailwind-merge`. Khi hai lớp cùng đặt `border-color`, thứ tự trong tệp
   * CSS quyết định chứ không phải thứ tự truyền vào, và Tailwind sinh
   * `.border-danger-line` TRƯỚC `.border-line` nên lớp cơ sở thắng.
   *
   * Đó từng là một lỗi thật: nhãn "Trễ hạn" khai viền đỏ nhưng hiện ra viền
   * xám, và không có gì báo — chỉ nhìn kỹ mới thấy.
   */
  tone?: string;
  className?: string;
  children: ReactNode;
}) {
  return (
    <span
      className={cn(
        "inline-flex items-center gap-1.5 rounded-full border px-2.5 py-[0.15rem] text-[0.75rem] font-medium whitespace-nowrap",
        tone,
        className,
      )}
    >
      {dotClass && (
        <span
          aria-hidden="true"
          className={cn("size-1.5 shrink-0 rounded-full", dotClass)}
        />
      )}
      {children}
    </span>
  );
}

/**
 * Trạng thái của một đơn có người duyệt, và màu của nó.
 *
 * Ở `components/ui` chứ không ở một feature nào: đơn nghỉ, đơn đi muộn và đơn
 * giải trình công đi qua đúng một vòng đời, nên "đang chờ" phải có CÙNG MỘT MÀU
 * ở cả ba chỗ. Mỗi feature giữ một bảng riêng là ba bảng sẽ lệch nhau ở lần đổi
 * màu đầu tiên — và người dùng phải đọc chữ mới hiểu, đúng thứ màu sinh ra để
 * khỏi phải làm.
 *
 * Khai lại bốn trạng thái tại chỗ thay vì import kiểu từ feature: luật
 * no-restricted-imports cấm `components/ui` biết tới feature, và đây là bảng
 * MÀU nên nó chỉ cần biết bốn cái tên.
 */
export type RequestStatusValue =
  "pending" | "approved" | "rejected" | "cancelled";

/** Chờ duyệt là thứ DUY NHẤT cần ai đó nhìn tới. */
export const REQUEST_STATUS_TONE: Record<RequestStatusValue, string> = {
  pending: "border-notice-line bg-notice-surface text-notice",
  approved: "border-tone-line bg-tone-surface text-tone-ink",
  rejected: "border-danger-line bg-danger-surface text-danger",
  cancelled: "border-line bg-paper-sunken text-ink-faint",
};

/**
 * Avatar chữ. Đợt 1 chưa có ảnh đại diện nên dùng chữ cái đầu của tên.
 *
 * Màu nền lấy từ chính cái tên, không phải ngẫu nhiên: cùng một người luôn ra
 * cùng một màu ở mọi màn hình, nên nhìn màu là nhận ra người quen mà chưa cần
 * đọc chữ. Sáu tông đã đủ để phân biệt trong một danh sách, mà không biến bảng
 * việc thành hộp bút chì màu.
 */
const AVATAR_TONES = [
  "bg-emerald-100 text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-300",
  "bg-sky-100 text-sky-800 dark:bg-sky-500/15 dark:text-sky-300",
  "bg-amber-100 text-amber-800 dark:bg-amber-500/15 dark:text-amber-300",
  "bg-violet-100 text-violet-800 dark:bg-violet-500/15 dark:text-violet-300",
  "bg-rose-100 text-rose-800 dark:bg-rose-500/15 dark:text-rose-300",
  "bg-teal-100 text-teal-800 dark:bg-teal-500/15 dark:text-teal-300",
] as const;

function toneOf(name: string): string {
  let tong = 0;
  for (let i = 0; i < name.length; i += 1) tong += name.charCodeAt(i);

  // `?? AVATAR_TONES[0]` để chiều `noUncheckedIndexedAccess`: phép chia dư luôn
  // cho chỉ số hợp lệ, nhưng TypeScript không biết điều đó.
  return AVATAR_TONES[tong % AVATAR_TONES.length] ?? AVATAR_TONES[0];
}

export function Avatar({
  name,
  size = "md",
}: {
  name: string;
  size?: "sm" | "md";
}) {
  return (
    <span
      title={name}
      className={cn(
        "inline-flex shrink-0 items-center justify-center rounded-full font-semibold",
        toneOf(name),
        size === "sm" ? "size-6 text-[0.62rem]" : "size-8 text-[0.72rem]",
      )}
    >
      {initials(name)}
    </span>
  );
}
