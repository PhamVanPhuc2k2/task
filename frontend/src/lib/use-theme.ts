"use client";

import { useCallback, useEffect, useSyncExternalStore } from "react";

import {
  docLuaChon,
  luuLuaChon,
  theoDoiHeMay,
  THEME_EVENT,
  type ThemeChoice,
} from "./theme";

/**
 * Đọc và đổi lựa chọn giao diện.
 *
 * Dùng `useSyncExternalStore` chứ không `useState`: nguồn sự thật nằm ở
 * `localStorage` và ở `<html data-theme>`, tức là **bên ngoài React**. Nếu mỗi
 * chỗ giữ một `useState` riêng thì mở hai menu cùng lúc sẽ thấy hai giá trị
 * khác nhau.
 *
 * `getServerSnapshot` trả `"system"` vì máy chủ không biết máy của người dùng
 * đang để sáng hay tối — và đoán bừa thì React sẽ báo lệch hydrate.
 */
export function useTheme(): {
  chon: ThemeChoice;
  doi: (chon: ThemeChoice) => void;
} {
  const chon = useSyncExternalStore<ThemeChoice>(
    dangKy,
    docLuaChon,
    () => "system",
  );

  // Máy đổi sáng/tối giữa chừng (hẹn giờ theo hoàng hôn chẳng hạn) thì app
  // phải đổi theo — nhưng chỉ khi người dùng đang để "theo máy".
  useEffect(theoDoiHeMay, []);

  const doi = useCallback((moi: ThemeChoice) => luuLuaChon(moi), []);

  return { chon, doi };
}

function dangKy(onStoreChange: () => void): () => void {
  window.addEventListener(THEME_EVENT, onStoreChange);
  // `storage` bắn khi TAB KHÁC đổi giá trị. Không có nó thì mở app ở hai tab,
  // đổi màu ở tab này, tab kia vẫn hiện lựa chọn cũ.
  window.addEventListener("storage", onStoreChange);

  return () => {
    window.removeEventListener(THEME_EVENT, onStoreChange);
    window.removeEventListener("storage", onStoreChange);
  };
}
