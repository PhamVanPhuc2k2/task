import type {
  InputHTMLAttributes,
  ReactNode,
  SelectHTMLAttributes,
  TextareaHTMLAttributes,
} from "react";
import { useId } from "react";

import { cn } from "@/lib/cn";

/**
 * Ô nhập lúc được chọn: viền đổi màu nhấn kèm một quầng sáng mỏng quanh viền.
 *
 * Trước đây dùng `focus-frame` — một đường viền vẽ tách rời bên ngoài ô. Nó
 * đúng cho nút và liên kết, nhưng với ô nhập thì quầng ôm sát viền cho biết
 * chính xác đang gõ vào ô nào, kể cả khi các ô xếp sát nhau trong một hàng lọc.
 */
const CONTROL =
  "border-line bg-paper-raised text-ink placeholder:text-ink-faint w-full rounded-xl border px-3.5 py-2 text-[0.9rem] outline-none transition-[border-color,box-shadow,background-color] focus:border-accent focus:ring-4 focus:ring-accent/20 disabled:bg-paper-sunken disabled:opacity-60";

/**
 * Nhãn + ô nhập + lỗi, nối với nhau bằng id sinh tự động.
 *
 * Bọc lại thay vì để mỗi màn hình tự viết `<label htmlFor>`: chỉ cần một chỗ
 * quên là ô đó mất nhãn với trình đọc màn hình, và lỗi kiểu đó không ai nhìn
 * bằng mắt mà thấy được.
 */
interface FieldProps {
  /**
   * Nhãn của ô.
   *
   * `ReactNode` chứ không `string`: trang cài đặt cần gắn thêm một viên nhãn
   * "đã sửa" ngay cạnh chữ. `ReactNode` là tập trên của `string` nên mọi chỗ
   * gọi cũ vẫn đúng nguyên.
   */
  label: ReactNode;
  error?: string | null;
  hint?: string;
  required?: boolean;
  children: (id: string, describedBy: string | undefined) => ReactNode;
}

export function Field({ label, error, hint, required, children }: FieldProps) {
  const id = useId();
  const hintId = hint ? `${id}-hint` : undefined;
  const errorId = error ? `${id}-error` : undefined;
  const describedBy = [hintId, errorId].filter(Boolean).join(" ") || undefined;

  return (
    <div>
      <label htmlFor={id} className="mb-1.5 block text-[0.85rem] font-medium">
        {label}
        {required && (
          <span className="text-danger ml-0.5" aria-hidden="true">
            *
          </span>
        )}
      </label>

      {children(id, describedBy)}

      {hint && !error && (
        <p id={hintId} className="text-ink-faint mt-1.5 text-[0.78rem]">
          {hint}
        </p>
      )}

      {error && (
        <p
          id={errorId}
          role="alert"
          className="text-danger mt-1.5 text-[0.8rem]"
        >
          {error}
        </p>
      )}
    </div>
  );
}

export function TextInput({
  className,
  ...props
}: InputHTMLAttributes<HTMLInputElement>) {
  return <input {...props} className={cn(CONTROL, className)} />;
}

export function TextArea({
  className,
  ...props
}: TextareaHTMLAttributes<HTMLTextAreaElement>) {
  return <textarea {...props} className={cn(CONTROL, "resize-y", className)} />;
}

export function SelectInput({
  className,
  children,
  ...props
}: SelectHTMLAttributes<HTMLSelectElement>) {
  return (
    <select {...props} className={cn(CONTROL, "cursor-pointer", className)}>
      {children}
    </select>
  );
}
