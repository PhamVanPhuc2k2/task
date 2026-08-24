"use client";

import { useEffect, useState } from "react";

const COOLDOWN_SECONDS = 45;

/**
 * Nút gửi lại mã, khoá tạm sau mỗi lần bấm.
 *
 * Đếm ngược không phải để làm khó người dùng — nó nói cho họ biết phải chờ bao
 * lâu. Không có nó thì họ bấm liên tục, đụng giới hạn phía server và nhận lỗi
 * 429 khó hiểu.
 */
export function ResendButton({
  onResend,
  pending,
}: {
  onResend: () => Promise<void>;
  pending: boolean;
}) {
  const [conLai, setConLai] = useState(0);

  useEffect(() => {
    if (conLai <= 0) {
      return;
    }

    const id = window.setTimeout(() => setConLai((truoc) => truoc - 1), 1000);

    return () => window.clearTimeout(id);
  }, [conLai]);

  const bam = async () => {
    await onResend();
    setConLai(COOLDOWN_SECONDS);
  };

  const khoa = conLai > 0 || pending;

  return (
    <button
      type="button"
      onClick={() => void bam()}
      disabled={khoa}
      className="text-ink-soft enabled:hover:text-accent focus-frame w-full rounded text-[0.85rem] underline decoration-dotted underline-offset-4 transition-colors duration-200 disabled:no-underline disabled:opacity-55"
    >
      {pending
        ? "Đang gửi lại…"
        : conLai > 0
          ? `Gửi lại mã sau ${conLai}s`
          : "Không nhận được mã? Gửi lại"}
    </button>
  );
}
