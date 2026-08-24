"use client";

import { useRef, type ClipboardEvent, type KeyboardEvent } from "react";

const LENGTH = 6;

/**
 * Ô nhập mã OTP tách thành 6 ký tự.
 *
 * Không chỉ để đẹp — nhân viên nhập trên điện thoại là chính, và ô rời cho ba
 * thứ mà một ô dài không có: bàn phím số bật sẵn, tự nhảy ô, và nhìn là biết
 * đang thiếu mấy số.
 *
 * Dán cả mã từ tin nhắn hoặc ứng dụng cũng điền đúng vào các ô.
 */
export function OtpInput({
  value,
  onChange,
  onComplete,
  disabled,
  invalid,
}: {
  value: string;
  onChange: (value: string) => void;
  onComplete?: (value: string) => void;
  disabled?: boolean;
  invalid?: boolean;
}) {
  const refs = useRef<Array<HTMLInputElement | null>>([]);

  const focusAt = (index: number) => {
    refs.current[Math.max(0, Math.min(LENGTH - 1, index))]?.focus();
  };

  const setDigits = (next: string) => {
    const cleaned = next.replace(/\D/g, "").slice(0, LENGTH);
    onChange(cleaned);

    if (cleaned.length === LENGTH) {
      onComplete?.(cleaned);
    }
  };

  const handleInput = (index: number, raw: string) => {
    const digits = raw.replace(/\D/g, "");

    if (digits === "") {
      return;
    }

    const next =
      value.slice(0, index) + digits + value.slice(index + digits.length);

    setDigits(next);
    focusAt(index + digits.length);
  };

  const handleKeyDown = (
    index: number,
    event: KeyboardEvent<HTMLInputElement>,
  ) => {
    if (event.key === "Backspace") {
      event.preventDefault();

      if (value[index] !== undefined && value[index] !== "") {
        setDigits(value.slice(0, index) + value.slice(index + 1));
      } else if (index > 0) {
        setDigits(value.slice(0, index - 1) + value.slice(index));
        focusAt(index - 1);
      }

      return;
    }

    if (event.key === "ArrowLeft") {
      event.preventDefault();
      focusAt(index - 1);
    }

    if (event.key === "ArrowRight") {
      event.preventDefault();
      focusAt(index + 1);
    }
  };

  const handlePaste = (event: ClipboardEvent<HTMLInputElement>) => {
    event.preventDefault();
    const pasted = event.clipboardData.getData("text");
    setDigits(pasted);
    focusAt(pasted.replace(/\D/g, "").length);
  };

  return (
    <div
      className="flex gap-2 sm:gap-2.5"
      role="group"
      aria-label="Mã xác thực gồm 6 chữ số"
    >
      {Array.from({ length: LENGTH }, (_, index) => {
        const daNhap = value[index] !== undefined && value[index] !== "";

        return (
          <input
            key={index}
            ref={(element) => {
              refs.current[index] = element;
            }}
            type="text"
            inputMode="numeric"
            autoComplete={index === 0 ? "one-time-code" : "off"}
            maxLength={1}
            disabled={disabled}
            value={value[index] ?? ""}
            aria-label={`Chữ số thứ ${index + 1}`}
            aria-invalid={invalid === true}
            onChange={(event) => handleInput(index, event.target.value)}
            onKeyDown={(event) => handleKeyDown(index, event)}
            onPaste={handlePaste}
            onFocus={(event) => event.target.select()}
            className={`text-ink h-14 w-full rounded-lg border text-center font-mono text-[1.25rem] transition-all duration-200 outline-none focus:shadow-[0_0_0_3px_rgba(200,245,45,0.14)] disabled:opacity-40 ${
              invalid === true
                ? "border-danger-line bg-danger-surface"
                : daNhap
                  ? "border-accent/45 bg-accent/[0.07] shadow-[0_0_20px_-8px_var(--accent)]"
                  : "border-line focus:border-accent/60 bg-white/[0.03]"
            }`}
          />
        );
      })}
    </div>
  );
}
