"use client";

import { useRef, useState, type KeyboardEvent } from "react";

import { useAssignableUsers } from "@/features/users/api/directory-api";
import { cn } from "@/lib/cn";

/**
 * Ô soạn thảo có gợi ý nhắc tên.
 *
 * Gõ `@` rồi vài chữ sẽ mở danh sách đồng nghiệp; chọn một người thì ô chèn
 * vào `@[Tên hiển thị](uuid)`. Backend chỉ dò đúng dạng này — xem
 * `SyncCommentMentionsAction` để biết vì sao không dò `@tên` gõ tay: tên người
 * Việt trùng nhau rất nhiều, và nhắc nhầm người trong hệ thống giao việc nghĩa
 * là gửi thông báo về một việc không phải của họ.
 */
/** Id của danh sách gợi ý, dùng để nối `aria-controls` với `role="listbox"`. */
const DS_GOI_Y = "goi-y-nhac-ten";

export function MentionTextarea({
  value,
  onChange,
  placeholder,
  rows = 3,
  id,
  autoFocus,
}: {
  value: string;
  onChange: (value: string) => void;
  placeholder?: string;
  rows?: number;
  id?: string;
  autoFocus?: boolean;
}) {
  const ref = useRef<HTMLTextAreaElement>(null);
  const [tuKhoa, setTuKhoa] = useState<string | null>(null);
  const [chon, setChon] = useState(0);

  const { data: danhBa } = useAssignableUsers();
  const nguoi = danhBa?.people;

  const goiY =
    tuKhoa === null
      ? []
      : (nguoi ?? [])
          .filter((n) =>
            n.name
              .toLocaleLowerCase("vi")
              .includes(tuKhoa.toLocaleLowerCase("vi")),
          )
          .slice(0, 6);

  /** Đoạn `@abc` đang gõ dở, tính từ con trỏ ngược về trước. */
  function doTuKhoa(text: string, caret: number) {
    const truoc = text.slice(0, caret);
    const khop = /@([^\s@\][()]*)$/.exec(truoc);

    setTuKhoa(khop === null ? null : (khop[1] ?? ""));
    setChon(0);
  }

  function chonNguoi(nguoi: { id: string; name: string }) {
    const el = ref.current;
    if (el === null || tuKhoa === null) return;

    const caret = el.selectionStart;
    const batDau = caret - tuKhoa.length - 1; // trừ cả ký tự '@'

    const moi =
      value.slice(0, batDau) +
      `@[${nguoi.name}](${nguoi.id}) ` +
      value.slice(caret);

    onChange(moi);
    setTuKhoa(null);

    // Đưa con trỏ về ngay sau dấu nhắc vừa chèn, nếu không nó nhảy về cuối ô
    // và người dùng đang viết giữa câu sẽ mất chỗ.
    const viTriMoi = batDau + `@[${nguoi.name}](${nguoi.id}) `.length;
    requestAnimationFrame(() => {
      el.focus();
      el.setSelectionRange(viTriMoi, viTriMoi);
    });
  }

  function onKeyDown(event: KeyboardEvent<HTMLTextAreaElement>) {
    if (goiY.length === 0) return;

    if (event.key === "ArrowDown") {
      event.preventDefault();
      setChon((i) => (i + 1) % goiY.length);
    } else if (event.key === "ArrowUp") {
      event.preventDefault();
      setChon((i) => (i - 1 + goiY.length) % goiY.length);
    } else if (event.key === "Enter" || event.key === "Tab") {
      const nguoi = goiY[chon];
      if (nguoi) {
        event.preventDefault();
        chonNguoi(nguoi);
      }
    } else if (event.key === "Escape") {
      setTuKhoa(null);
    }
  }

  return (
    <div className="relative">
      <textarea
        ref={ref}
        id={id}
        rows={rows}
        value={value}
        placeholder={placeholder}
        autoFocus={autoFocus}
        // Ô nhập có gợi ý là combobox theo ARIA 1.2. Không khai role này thì
        // `aria-expanded` vô hiệu trên textarea, và trình đọc màn hình không
        // biết có danh sách đang mở bên dưới.
        role="combobox"
        aria-autocomplete="list"
        aria-expanded={goiY.length > 0}
        aria-controls={goiY.length > 0 ? DS_GOI_Y : undefined}
        aria-activedescendant={
          goiY[chon] ? `${DS_GOI_Y}-${goiY[chon].id}` : undefined
        }
        onKeyDown={onKeyDown}
        onChange={(e) => {
          onChange(e.target.value);
          doTuKhoa(e.target.value, e.target.selectionStart);
        }}
        onClick={(e) => doTuKhoa(value, e.currentTarget.selectionStart)}
        onBlur={() => {
          // Trễ một nhịp để cú bấm vào danh sách kịp chạy trước khi nó đóng.
          setTimeout(() => setTuKhoa(null), 150);
        }}
        className="border-line bg-paper-raised text-ink placeholder:text-ink-faint focus:border-accent focus:ring-accent/20 w-full resize-y rounded-xl border px-3.5 py-2 text-[0.9rem] transition-[border-color,box-shadow] outline-none focus:ring-4"
      />

      {goiY.length > 0 && (
        <ul
          id={DS_GOI_Y}
          role="listbox"
          aria-label="Gợi ý nhắc tên"
          className="border-line bg-paper-raised shadow-pop absolute z-20 mt-1 w-full max-w-sm overflow-hidden rounded-xl border"
        >
          {goiY.map((nguoi, i) => (
            <li key={nguoi.id}>
              <button
                type="button"
                id={`${DS_GOI_Y}-${nguoi.id}`}
                role="option"
                aria-selected={i === chon}
                onMouseDown={(e) => e.preventDefault()}
                onClick={() => chonNguoi(nguoi)}
                className={cn(
                  "block w-full px-3 py-2 text-left text-[0.86rem]",
                  i === chon ? "bg-paper-raised" : "hover:bg-paper-raised",
                )}
              >
                {nguoi.name}
                {nguoi.department && (
                  <span className="text-ink-faint ml-2 text-[0.76rem]">
                    {nguoi.department}
                  </span>
                )}
              </button>
            </li>
          ))}
        </ul>
      )}
    </div>
  );
}
