"use client";

import type { InputHTMLAttributes, ReactNode } from "react";

/**
 * Các mảnh giao diện dùng chung cho luồng đăng nhập.
 *
 * Cố tình để trong `features/auth` chứ chưa nhấc lên `components/ui/`: bộ
 * component chung của cả hệ thống dựng ở mục 1.7, khi đã biết những màn khác
 * thực sự cần gì. Nhấc lên sớm lúc mới có một chỗ dùng là đoán mò.
 */

/** Tiêu đề một bước, kèm chỉ số tiến độ. */
export function StepHeading({
  step,
  total,
  title,
  description,
}: {
  step: number;
  total: number;
  title: string;
  description?: ReactNode;
}) {
  return (
    <header className="mb-7">
      <div className="mb-5 flex items-center gap-3">
        <span className="text-ink-faint font-mono text-[0.68rem] tracking-[0.18em]">
          <span className="text-accent">{String(step).padStart(2, "0")}</span>
          {" / "}
          {String(total).padStart(2, "0")}
        </span>

        {/* Thanh tiến độ: đoạn đã qua sáng lên. */}
        <span className="flex flex-1 gap-1.5">
          {Array.from({ length: total }, (_, index) => (
            <span
              key={index}
              className={`h-[3px] flex-1 rounded-full transition-colors duration-500 ${
                index < step
                  ? "bg-accent shadow-[0_0_12px_-1px_var(--accent)]"
                  : "bg-line-strong"
              }`}
            />
          ))}
        </span>
      </div>

      <h2 className="text-[1.75rem] leading-[1.15] font-bold tracking-[-0.025em]">
        {title}
      </h2>

      {description !== undefined && (
        <p className="text-ink-soft mt-2.5 text-[0.9rem] leading-relaxed">
          {description}
        </p>
      )}
    </header>
  );
}

export function FormError({ message }: { message: string }) {
  return (
    <div
      role="alert"
      className="border-danger-line bg-danger-surface text-danger flex items-start gap-2.5 rounded-lg border px-3.5 py-3 text-[0.85rem] leading-snug backdrop-blur-sm"
    >
      <span
        aria-hidden="true"
        className="border-danger-line mt-px grid h-4 w-4 shrink-0 place-items-center rounded-full border font-mono text-[0.6rem]"
      >
        !
      </span>
      <span>{message}</span>
    </div>
  );
}

export function Notice({ children }: { children: ReactNode }) {
  return (
    <div className="border-notice-line bg-notice-surface text-notice rounded-lg border px-3.5 py-3 text-[0.85rem] leading-relaxed backdrop-blur-sm">
      {children}
    </div>
  );
}

interface FieldProps extends InputHTMLAttributes<HTMLInputElement> {
  label: string;
  error?: string | undefined;
  hint?: string | undefined;
}

export function Field({ label, error, hint, id, ...props }: FieldProps) {
  const errorId = `${id}-error`;
  const hintId = `${id}-hint`;

  return (
    <div className="space-y-2">
      <label
        htmlFor={id}
        className="text-ink-soft block font-mono text-[0.66rem] tracking-[0.18em] uppercase"
      >
        {label}
      </label>

      {hint !== undefined && (
        <p id={hintId} className="text-ink-faint text-[0.82rem] leading-snug">
          {hint}
        </p>
      )}

      <input
        id={id}
        aria-invalid={error !== undefined}
        aria-describedby={
          error !== undefined
            ? errorId
            : hint !== undefined
              ? hintId
              : undefined
        }
        className={`text-ink placeholder:text-ink-faint w-full rounded-lg border bg-white/[0.03] px-4 py-3 text-[0.95rem] transition-all duration-200 outline-none focus:bg-white/[0.06] ${
          error !== undefined
            ? "border-danger-line focus:shadow-[0_0_0_3px_var(--danger-surface)]"
            : "border-line hover:border-line-strong focus:border-accent/60 focus:shadow-[0_0_0_3px_rgba(200,245,45,0.12)]"
        }`}
        {...props}
      />

      {error !== undefined && (
        <p id={errorId} className="text-danger text-[0.82rem]">
          {error}
        </p>
      )}
    </div>
  );
}

export function SubmitButton({
  children,
  pending,
  disabled,
}: {
  children: ReactNode;
  pending: boolean;
  disabled?: boolean;
}) {
  return (
    <button
      type="submit"
      disabled={pending || disabled === true}
      className="bg-accent text-on-accent hover:bg-accent-hover focus-frame sheen w-full rounded-lg px-4 py-3.5 text-[0.92rem] font-semibold tracking-tight shadow-[0_8px_28px_-10px_var(--accent)] transition-all duration-200 hover:shadow-[0_10px_34px_-8px_var(--accent)] disabled:cursor-not-allowed disabled:opacity-35 disabled:shadow-none"
    >
      <span className="relative z-10 inline-flex items-center justify-center gap-2">
        {pending && (
          <span
            aria-hidden="true"
            className="border-on-accent/30 border-t-on-accent h-3.5 w-3.5 animate-spin rounded-full border-2"
          />
        )}
        {children}
      </span>
    </button>
  );
}

export function SecondaryButton({
  children,
  onClick,
}: {
  children: ReactNode;
  onClick: () => void;
}) {
  return (
    <button
      type="button"
      onClick={onClick}
      className="border-line text-ink-soft hover:border-line-strong hover:text-ink focus-frame w-full rounded-lg border bg-white/[0.02] px-4 py-2.5 text-[0.85rem] transition-all duration-200 hover:bg-white/[0.05]"
    >
      {children}
    </button>
  );
}

export function GhostButton({
  children,
  onClick,
}: {
  children: ReactNode;
  onClick: () => void;
}) {
  return (
    <button
      type="button"
      onClick={onClick}
      className="text-ink-soft hover:text-accent focus-frame w-full rounded text-[0.85rem] underline decoration-dotted underline-offset-4 transition-colors duration-200"
    >
      {children}
    </button>
  );
}
