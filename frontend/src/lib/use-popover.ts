"use client";

import { useEffect, useRef, useState, type RefObject } from "react";

/**
 * Menu thả xuống: mở/đóng, bấm ra ngoài, phím Esc.
 *
 * Gom lại một chỗ vì cả chuông thông báo lẫn menu tài khoản đều cần đúng ba
 * hành vi này, và bản viết tay ở mỗi nơi đều thiếu **Esc** — người dùng bàn
 * phím mở menu ra rồi không có cách nào đóng lại ngoài việc bấm chuột.
 */
export function usePopover<T extends HTMLElement = HTMLDivElement>(): {
  boc: RefObject<T | null>;
  dangMo: boolean;
  moDong: () => void;
  dong: () => void;
} {
  const boc = useRef<T>(null);
  const [dangMo, setDangMo] = useState(false);

  useEffect(() => {
    if (!dangMo) return;

    // `pointerdown` chứ không `click`: `click` chạy sau khi phần tử bên trong
    // đã xử lý xong, nên bấm vào một mục sẽ vừa điều hướng vừa đóng — và trên
    // một số trình duyệt thứ tự đó đảo ngược.
    function ngoai(event: PointerEvent) {
      if (boc.current && !boc.current.contains(event.target as Node)) {
        setDangMo(false);
      }
    }

    function phim(event: KeyboardEvent) {
      if (event.key !== "Escape") return;

      setDangMo(false);

      // Trả tiêu điểm về nút đã mở menu. Không làm thì tiêu điểm rơi về đầu
      // trang và người dùng bàn phím phải Tab lại từ đầu.
      boc.current?.querySelector("button")?.focus();
    }

    document.addEventListener("pointerdown", ngoai);
    document.addEventListener("keydown", phim);

    return () => {
      document.removeEventListener("pointerdown", ngoai);
      document.removeEventListener("keydown", phim);
    };
  }, [dangMo]);

  return {
    boc,
    dangMo,
    moDong: () => setDangMo((v) => !v),
    dong: () => setDangMo(false),
  };
}
