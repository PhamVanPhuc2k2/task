import type { ButtonHTMLAttributes, ReactNode } from "react";

import { cn } from "@/lib/cn";

type Variant = "primary" | "secondary" | "ghost" | "danger";
type Size = "sm" | "md";

/**
 * Nút chính có bóng đổ, ba nút còn lại thì không.
 *
 * Trên một màn hình chỉ nên có một việc "đáng làm nhất". Bóng đổ nâng đúng nút
 * đó lên khỏi mặt phẳng, nên mắt tìm thấy nó trước cả khi kịp đọc chữ. Cho mọi
 * nút cùng bóng đổ thì không nút nào nổi lên và tín hiệu mất sạch.
 */
const VARIANTS: Record<Variant, string> = {
  primary:
    "bg-accent text-on-accent hover:bg-accent-hover font-semibold shadow-card hover:shadow-lift",
  secondary:
    "border-line bg-paper-raised hover:border-line-strong hover:bg-paper-sunken border font-medium",
  ghost: "text-ink-soft hover:bg-paper-sunken hover:text-ink font-medium",
  danger:
    "border-danger-line bg-danger-surface text-danger hover:border-danger border font-medium",
};

const SIZES: Record<Size, string> = {
  sm: "h-8 gap-1.5 rounded-lg px-3 text-[0.82rem]",
  md: "h-10 gap-2 rounded-xl px-4 text-[0.9rem]",
};

/**
 * Lún xuống 1px khi bấm.
 *
 * Phản hồi tức thì lúc ngón tay chạm, không phải đợi mạng trả lời. Trên điện
 * thoại — nơi không có trạng thái rê chuột — đây là dấu hiệu duy nhất cho biết
 * cú chạm đã ăn.
 */
const BASE =
  "focus-frame inline-flex items-center justify-center whitespace-nowrap transition-all duration-150 active:translate-y-px";

interface ButtonProps extends ButtonHTMLAttributes<HTMLButtonElement> {
  variant?: Variant;
  size?: Size;
  /** Hiện vòng xoay và khoá nút. Dùng khi đang gửi request. */
  loading?: boolean;
  children?: ReactNode;
}

export function Button({
  variant = "secondary",
  size = "md",
  loading = false,
  disabled,
  className,
  children,
  ...props
}: ButtonProps) {
  return (
    <button
      {...props}
      disabled={disabled === true || loading}
      // aria-busy để trình đọc màn hình biết nút đang xử lý, không chỉ là
      // hiệu ứng nhìn thấy được.
      aria-busy={loading || undefined}
      className={cn(
        BASE,
        "cursor-pointer",
        "disabled:cursor-not-allowed disabled:opacity-50 disabled:shadow-none disabled:active:translate-y-0",
        VARIANTS[variant],
        SIZES[size],
        className,
      )}
    >
      {loading && <Spinner />}
      {children}
    </button>
  );
}

/**
 * Class của nút, để gắn lên thẻ `<a>` / `<Link>`.
 *
 * Cần vì "đi tới trang khác" phải là liên kết thật, không phải nút: liên kết
 * mở được trong tab mới, sao chép được địa chỉ, và trình đọc màn hình đọc đúng
 * là liên kết. Lồng `<a>` trong `<button>` thì HTML không hợp lệ.
 */
export function buttonClass(
  variant: Variant = "secondary",
  size: Size = "md",
  className?: string,
): string {
  return cn(BASE, VARIANTS[variant], SIZES[size], className);
}

export function Spinner({ className }: { className?: string }) {
  return (
    <span
      aria-hidden="true"
      className={cn(
        "size-3.5 animate-spin rounded-full border-2 border-current border-t-transparent",
        className,
      )}
    />
  );
}
