"use client";

import { useEffect, useRef, type ReactNode } from "react";

/**
 * Hộp thoại dựng trên thẻ `<dialog>` của trình duyệt.
 *
 * Dùng phần tử gốc thay vì tự dựng overlay bằng div: `showModal()` cho sẵn bẫy
 * tiêu điểm, đóng bằng phím Esc, chặn tương tác với nền và lớp `::backdrop` —
 * bốn thứ mà bản tự viết gần như luôn làm thiếu ít nhất một.
 */
export function Dialog({
  open,
  onClose,
  title,
  description,
  children,
}: {
  open: boolean;
  onClose: () => void;
  title: string;
  description?: string;
  children: ReactNode;
}) {
  const ref = useRef<HTMLDialogElement>(null);

  useEffect(() => {
    const el = ref.current;
    if (!el) return;

    if (open && !el.open) {
      el.showModal();
    } else if (!open && el.open) {
      el.close();
    }
  }, [open]);

  return (
    <dialog
      ref={ref}
      // `close` bắn cả khi người dùng bấm Esc — báo ngược lên để state cha
      // không kẹt ở `open: true` rồi lần sau bấm nút không mở lại được.
      onClose={onClose}
      onClick={(event) => {
        // Bấm ra ngoài thì đóng. Sự kiện click trên chính thẻ dialog nghĩa là
        // trúng phần backdrop, vì nội dung nằm trong div con.
        if (event.target === ref.current) onClose();
      }}
      className="bg-paper-raised text-ink border-line shadow-pop m-auto w-[min(34rem,calc(100vw-2rem))] rounded-2xl border p-0 backdrop:bg-black/45 backdrop:backdrop-blur-sm"
    >
      <div className="p-5 sm:p-6">
        <h2 className="text-[1.1rem] font-semibold tracking-tight">{title}</h2>

        {description && (
          <p className="text-ink-faint mt-1.5 text-[0.85rem] leading-relaxed">
            {description}
          </p>
        )}

        <div className="mt-5">{children}</div>
      </div>
    </dialog>
  );
}
