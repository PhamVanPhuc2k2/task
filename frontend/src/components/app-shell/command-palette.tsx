"use client";

import { useRouter } from "next/navigation";
import {
  useCallback,
  useEffect,
  useMemo,
  useRef,
  useState,
  useSyncExternalStore,
  type ComponentType,
  type Dispatch,
  type SetStateAction,
  type SVGProps,
} from "react";

import { cn } from "@/lib/cn";
import { THEME_LABELS, type ThemeChoice } from "@/lib/theme";
import { useTheme } from "@/lib/use-theme";

import { visibleNavItems, type Tone } from "./nav-items";

/**
 * Bảng lệnh mở bằng Ctrl+K.
 *
 * ## Vì sao một app nội bộ cần thứ này
 *
 * Đây là công cụ người ta mở suốt tám tiếng mỗi ngày, năm ngày mỗi tuần. Với
 * nhịp đó, khoảng cách giữa "rê chuột tìm mục trong thanh bên" và "gõ hai chữ
 * rồi Enter" cộng dồn thành hàng giờ mỗi năm.
 *
 * Nó cũng giải một vấn đề mà thanh bên không giải được: **thanh bên biến mất
 * trên điện thoại và màn hình hẹp**. Ở đó, điều hướng hiện tại là bấm nút hamburger,
 * đợi ngăn kéo trượt ra, rồi mới chọn.
 *
 * ## Vì sao dựng trên `<dialog>`
 *
 * Cùng lý do đã ghi ở `components/ui/dialog.tsx`: `showModal()` cho sẵn bẫy
 * tiêu điểm, đóng bằng Esc, chặn tương tác với nền. Bản tự dựng bằng div gần
 * như luôn thiếu ít nhất một trong ba.
 *
 * ## Cái nó CHƯA làm
 *
 * Chưa tìm được nội dung — không gõ tên một công việc rồi nhảy thẳng tới nó.
 * Việc đó cần một endpoint tìm kiếm ở backend chưa có. Hiện tại đây là bảng
 * điều hướng và hành động nhanh, và nhãn ô nhập nói đúng như vậy chứ không hứa
 * quá.
 */

interface Lenh {
  id: string;
  nhan: string;
  /** Chữ phụ bên phải — nhóm hoặc trạng thái hiện tại. */
  phu: string;
  /** Từ khoá phụ để gõ tiếng Việt không dấu vẫn ra. */
  timTheo: string;
  tone: Tone;
  icon: ComponentType<SVGProps<SVGSVGElement>>;
  chay: () => void;
}

export function CommandPalette({
  permissions,
  mo,
  setMo,
}: {
  permissions: string[];
  /* Điều khiển từ `AppShell`, không tự giữ: nút "Tìm nhanh" ở đầu trang cũng
     phải mở được đúng bảng này. Hai state riêng thì bấm nút sẽ không mở gì. */
  mo: boolean;
  setMo: Dispatch<SetStateAction<boolean>>;
}) {
  const router = useRouter();
  const dialog = useRef<HTMLDialogElement>(null);
  const oNhap = useRef<HTMLInputElement>(null);
  const [tim, setTim] = useState("");
  const [chiSo, setChiSo] = useState(0);
  const { chon, doi } = useTheme();

  const dong = useCallback(() => setMo(false), [setMo]);

  // Ctrl+K / ⌘K ở bất kỳ đâu trong app.
  useEffect(() => {
    function phim(e: KeyboardEvent) {
      if (e.key.toLowerCase() !== "k" || !(e.metaKey || e.ctrlKey)) return;

      // Trình duyệt đã dành sẵn ⌘K cho thanh địa chỉ. Không chặn thì bấm phím
      // tắt trong app sẽ nhảy ra ngoài trình duyệt.
      e.preventDefault();
      setMo((v) => !v);
    }

    window.addEventListener("keydown", phim);
    return () => window.removeEventListener("keydown", phim);
  }, [setMo]);

  useEffect(() => {
    const el = dialog.current;
    if (!el) return;

    if (mo && !el.open) {
      el.showModal();
      setTim("");
      setChiSo(0);
      oNhap.current?.focus();
    } else if (!mo && el.open) {
      el.close();
    }
  }, [mo]);

  const lenh = useMemo<Lenh[]>(() => {
    const dieuHuong = visibleNavItems(permissions).map<Lenh>((item) => ({
      id: `nav:${item.href}`,
      nhan: item.label,
      phu: "Đi tới",
      timTheo: khongDau(item.label),
      tone: item.tone,
      icon: item.icon,
      chay: () => router.push(item.href),
    }));

    const giaoDien = (["light", "dark", "system"] as ThemeChoice[]).map<Lenh>(
      (t) => ({
        id: `theme:${t}`,
        nhan: `Giao diện: ${THEME_LABELS[t]}`,
        phu: chon === t ? "Đang dùng" : "Đổi",
        timTheo: khongDau(`giao dien ${THEME_LABELS[t]} theme sang toi`),
        tone: "all",
        icon: IconGiaoDien,
        chay: () => doi(t),
      }),
    );

    return [...dieuHuong, ...giaoDien];
  }, [permissions, router, chon, doi]);

  const ketQua = useMemo(() => {
    const q = khongDau(tim.trim());
    if (q === "") return lenh;

    // Khớp theo TỪ, không theo chuỗi con: gõ "cong" phải ra "Công việc" nhưng
    // không nên ra mọi mục tình cờ chứa hai chữ cái đó ở giữa từ khác.
    const tu = q.split(/\s+/);
    return lenh.filter((l) => tu.every((t) => l.timTheo.includes(t)));
  }, [lenh, tim]);

  // Danh sách ngắn lại sau khi gõ thì con trỏ có thể trỏ ra ngoài mảng.
  const viTri = Math.min(chiSo, Math.max(0, ketQua.length - 1));

  const chayMuc = (l: Lenh | undefined) => {
    if (!l) return;
    setMo(false);
    l.chay();
  };

  return (
    <dialog
      ref={dialog}
      onClose={dong}
      onClick={(e) => {
        if (e.target === dialog.current) dong();
      }}
      aria-label="Bảng lệnh"
      className="bg-paper-raised text-ink border-line shadow-pop mx-auto mt-[12vh] mb-auto w-[min(34rem,calc(100vw-2rem))] rounded-2xl border p-0 backdrop:bg-black/50 backdrop:backdrop-blur-sm"
    >
      <div className="border-line flex items-center gap-2.5 border-b px-4">
        <IconTim />
        <input
          ref={oNhap}
          value={tim}
          onChange={(e) => {
            setTim(e.target.value);
            setChiSo(0);
          }}
          onKeyDown={(e) => {
            if (e.key === "ArrowDown") {
              e.preventDefault();
              setChiSo((i) => (i + 1) % Math.max(1, ketQua.length));
            } else if (e.key === "ArrowUp") {
              e.preventDefault();
              setChiSo(
                (i) =>
                  (i - 1 + Math.max(1, ketQua.length)) %
                  Math.max(1, ketQua.length),
              );
            } else if (e.key === "Enter") {
              e.preventDefault();
              chayMuc(ketQua[viTri]);
            }
          }}
          placeholder="Đi tới trang, đổi giao diện…"
          aria-label="Tìm lệnh"
          className="placeholder:text-ink-faint w-full bg-transparent py-3.5 text-[0.95rem] outline-none"
        />
        <kbd className="border-line text-ink-faint hidden rounded border px-1.5 py-0.5 font-mono text-[0.68rem] sm:block">
          Esc
        </kbd>
      </div>

      <div className="max-h-[min(24rem,55vh)] overflow-y-auto p-1.5">
        {ketQua.length === 0 ? (
          <p className="text-ink-faint px-3 py-8 text-center text-[0.85rem]">
            Không có lệnh nào khớp “{tim}”.
          </p>
        ) : (
          <ul>
            {ketQua.map((l, i) => {
              const Icon = l.icon;
              const dangChon = i === viTri;

              return (
                <li key={l.id}>
                  <button
                    type="button"
                    data-tone={l.tone}
                    // `pointerdown` chứ không `click`: chuột nhả ra sau khi
                    // dialog đã đóng thì sự kiện click không bao giờ tới nơi.
                    onPointerDown={(e) => {
                      e.preventDefault();
                      chayMuc(l);
                    }}
                    onMouseMove={() => setChiSo(i)}
                    className={cn(
                      "flex w-full items-center gap-2.5 rounded-lg px-2.5 py-2 text-left text-[0.88rem] transition-colors",
                      dangChon ? "bg-tone-surface text-ink" : "text-ink-soft",
                    )}
                  >
                    <Icon
                      className={cn(
                        "size-4 shrink-0",
                        dangChon ? "text-tone-ink" : "text-ink-faint",
                      )}
                    />
                    <span className="flex-1 truncate">{l.nhan}</span>
                    <span className="text-ink-faint shrink-0 text-[0.72rem]">
                      {l.phu}
                    </span>
                  </button>
                </li>
              );
            })}
          </ul>
        )}
      </div>

      <div className="border-line text-ink-faint flex items-center gap-3 border-t px-4 py-2 text-[0.7rem]">
        <Phim k="↑↓" mo="chọn" />
        <Phim k="↵" mo="mở" />
        <Phim k="Esc" mo="đóng" />
      </div>
    </dialog>
  );
}

/**
 * Nút mở bảng lệnh, đặt ở đầu trang.
 *
 * Một phím tắt không ai biết là một phím tắt không tồn tại. Nút này vừa mở
 * được bằng chuột, vừa **dạy** phím tắt bằng cách hiện luôn tổ hợp phím lên
 * mặt nút.
 */
export function CommandButton({ onClick }: { onClick: () => void }) {
  /*
  | Nhãn phím phải khớp máy đang dùng: người Mac bấm ⌘K, người Windows bấm
  | Ctrl+K. Hiện sai nhãn thì phím tắt trở thành hướng dẫn sai.
  |
  | `useSyncExternalStore` chứ không `useState` + `useEffect`: đây đúng là bài
  | toán "đọc một giá trị chỉ có ở trình duyệt mà không lệch hydrate", và đó là
  | việc mà API này sinh ra để làm. Bản dùng effect vừa gây thêm một lượt vẽ,
  | vừa bị chính ESLint của React chặn.
  */
  const phimTat = useSyncExternalStore(KHONG_DOI, docPhimTat, () => "Ctrl K");

  return (
    <button
      type="button"
      onClick={onClick}
      aria-label="Mở bảng lệnh"
      className="focus-frame border-line bg-paper-raised text-ink-faint hover:border-line-strong hover:text-ink-soft hidden items-center gap-2 rounded-lg border py-1.5 pr-1.5 pl-2.5 text-[0.8rem] transition-colors md:inline-flex"
    >
      <IconTim />
      <span>Tìm nhanh</span>
      <kbd className="border-line bg-paper text-ink-faint rounded border px-1.5 py-0.5 font-mono text-[0.68rem]">
        {phimTat}
      </kbd>
    </button>
  );
}

/** Nền tảng không đổi giữa chừng, nên không cần nghe gì cả. */
const KHONG_DOI = () => () => {};

function docPhimTat(): string {
  return /Mac|iPhone|iPad/i.test(navigator.userAgent) ? "⌘K" : "Ctrl K";
}

function Phim({ k, mo }: { k: string; mo: string }) {
  return (
    <span className="flex items-center gap-1">
      <kbd className="border-line rounded border px-1 font-mono text-[0.68rem]">
        {k}
      </kbd>
      {mo}
    </span>
  );
}

/**
 * Bỏ dấu tiếng Việt để gõ không dấu vẫn tìm ra.
 *
 * Người Việt gõ nhanh thường không bỏ dấu. Không có bước này thì gõ "cham cong"
 * không ra "Chấm công" — và người dùng kết luận là ô tìm kiếm hỏng.
 */
function khongDau(s: string): string {
  return s
    .toLowerCase()
    .normalize("NFD")
    .replace(/[̀-ͯ]/g, "")
    .replace(/đ/g, "d");
}

function IconTim() {
  return (
    <svg
      viewBox="0 0 24 24"
      aria-hidden="true"
      className="size-4 shrink-0"
      fill="none"
      stroke="currentColor"
      strokeWidth="2"
      strokeLinecap="round"
    >
      <circle cx="11" cy="11" r="7" />
      <path d="m20 20-3.5-3.5" />
    </svg>
  );
}

function IconGiaoDien(props: SVGProps<SVGSVGElement>) {
  return (
    <svg
      viewBox="0 0 24 24"
      aria-hidden="true"
      fill="none"
      stroke="currentColor"
      strokeWidth="1.8"
      strokeLinecap="round"
      strokeLinejoin="round"
      {...props}
    >
      <circle cx="12" cy="12" r="9" />
      <path d="M12 3a9 9 0 0 0 0 18z" fill="currentColor" stroke="none" />
    </svg>
  );
}
