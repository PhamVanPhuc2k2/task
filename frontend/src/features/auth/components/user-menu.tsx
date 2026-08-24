"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";

import { Avatar } from "@/components/ui/pill";
import { cn } from "@/lib/cn";
import { THEME_CHOICES, THEME_LABELS, type ThemeChoice } from "@/lib/theme";
import { usePopover } from "@/lib/use-popover";
import { useTheme } from "@/lib/use-theme";

import { useLogout } from "../api/auth-api";
import type { AuthUser } from "../types/user";

const NHAN_VAI_TRO: Record<string, string> = {
  admin: "Quản trị hệ thống",
  giam_doc: "Giám đốc",
  truong_phong: "Trưởng phòng",
  nhan_vien: "Nhân viên",
};

/**
 * Menu tài khoản ở góc phải đầu trang.
 *
 * Bản trước không phải menu mà là một hàng gồm tên, ảnh đại diện và nút "Đăng
 * xuất" nằm phơi ra. Cách đó ngốn chiều ngang của thanh đầu trang cho một hành
 * động mà người ta dùng đúng một lần mỗi ngày — và quan trọng hơn: **không còn
 * chỗ nào để đặt các tuỳ chọn của tài khoản**.
 *
 * Gom vào một menu thả xuống giải quyết cả hai. Đầu trang gọn lại, và những
 * thứ thuộc về "tôi" — giao diện sáng/tối, cài đặt thông báo, đăng xuất — nằm
 * chung một chỗ đúng như thói quen từ mọi công cụ khác.
 */
export function UserMenu({ user }: { user: AuthUser }) {
  const router = useRouter();
  const logout = useLogout();
  const { boc, dangMo, moDong, dong } = usePopover();

  const dangXuat = async () => {
    await logout.mutateAsync();
    router.replace("/login");
  };

  const vaiTro = user.roles[0];
  const nhanVaiTro =
    vaiTro === undefined ? "—" : (NHAN_VAI_TRO[vaiTro] ?? vaiTro);

  return (
    <div ref={boc} className="relative">
      <button
        type="button"
        onClick={moDong}
        aria-expanded={dangMo}
        aria-haspopup="menu"
        aria-label="Tài khoản"
        className={cn(
          "focus-frame flex items-center gap-2 rounded-xl py-1 pr-2 pl-1 transition-colors",
          dangMo ? "bg-paper-raised" : "hover:bg-paper-raised",
        )}
      >
        {/* Dùng chung `Avatar` với phần còn lại của app: cùng một người thì ở
            đây và ở bảng công việc phải ra cùng chữ tắt và cùng màu, nếu không
            thì màu mất luôn tác dụng nhận diện. */}
        <Avatar name={user.name} />

        <span className="hidden text-left sm:block">
          <span className="block max-w-36 truncate text-[0.84rem] leading-tight font-medium">
            {user.name}
          </span>
          <span className="text-ink-faint block text-[0.72rem] leading-tight">
            {nhanVaiTro}
          </span>
        </span>

        <ChevronIcon
          className={cn("transition-transform", dangMo && "rotate-180")}
        />
      </button>

      {dangMo && (
        <div
          role="menu"
          className="border-line bg-paper-raised shadow-pop absolute top-full right-0 z-40 mt-2 w-66 origin-top-right rounded-2xl border p-1.5"
        >
          <div className="px-2.5 py-2">
            <p className="truncate text-[0.88rem] font-semibold">{user.name}</p>
            <p className="text-ink-faint truncate text-[0.76rem]">
              {user.email}
            </p>
          </div>

          <div className="bg-line my-1 h-px" />

          <ChonGiaoDien />

          <div className="bg-line my-1 h-px" />

          {/* Chỉ liệt kê trang có thật. Menu tài khoản mà bấm vào ra 404 thì
              người dùng mất tin vào cả phần còn lại của menu. */}
          <MucLink href="/settings/notifications" onClick={dong}>
            Cài đặt thông báo
          </MucLink>

          {/* Ẩn với người không có quyền — để họ không bấm rồi thấy màn "không
              có quyền". Chặn thật nằm ở backend, đây chỉ là chuyện lịch sự. */}
          {user.permissions.includes("setting.manage") && (
            <MucLink href="/settings/site" onClick={dong}>
              Cài đặt trang
            </MucLink>
          )}

          <div className="bg-line my-1 h-px" />

          <button
            type="button"
            role="menuitem"
            onClick={() => void dangXuat()}
            disabled={logout.isPending}
            className="focus-frame text-danger hover:bg-danger-surface flex w-full items-center rounded-lg px-2.5 py-2 text-left text-[0.85rem] transition-colors disabled:opacity-60"
          >
            {logout.isPending ? "Đang thoát…" : "Đăng xuất"}
          </button>
        </div>
      )}
    </div>
  );
}

/**
 * Công tắc ba nấc, không phải công tắc bật/tắt.
 *
 * Bật/tắt hai nấc buộc người dùng phải chọn phe: hoặc luôn sáng, hoặc luôn
 * tối. Nấc thứ ba — "theo máy" — mới là thứ đa số muốn, vì máy của họ đã tự
 * đổi theo giờ trong ngày rồi. Bỏ nấc đó đi là bắt người ta tự tay đổi app hai
 * lần mỗi ngày.
 */
function ChonGiaoDien() {
  const { chon, doi } = useTheme();

  return (
    <div className="px-2.5 py-1.5">
      <p className="text-ink-faint mb-1.5 text-[0.72rem] font-medium">
        Giao diện
      </p>

      <div
        role="radiogroup"
        aria-label="Giao diện"
        className="border-line bg-paper-sunken grid grid-cols-3 gap-0.5 rounded-lg border p-0.5"
      >
        {THEME_CHOICES.map((t) => (
          <button
            key={t}
            type="button"
            role="radio"
            aria-checked={chon === t}
            onClick={() => doi(t)}
            className={cn(
              "focus-frame flex items-center justify-center gap-1 rounded-md py-1.5 text-[0.74rem] font-medium transition-colors",
              chon === t
                ? "bg-paper-raised text-ink shadow-card"
                : "text-ink-faint hover:text-ink-soft",
            )}
          >
            <BieuTuongGiaoDien chon={t} />
            {THEME_LABELS[t]}
          </button>
        ))}
      </div>
    </div>
  );
}

function MucLink({
  href,
  onClick,
  children,
}: {
  href: string;
  onClick: () => void;
  children: string;
}) {
  return (
    <Link
      href={href}
      role="menuitem"
      onClick={onClick}
      className="focus-frame text-ink-soft hover:bg-paper-sunken hover:text-ink block rounded-lg px-2.5 py-2 text-[0.85rem] transition-colors"
    >
      {children}
    </Link>
  );
}

function BieuTuongGiaoDien({ chon }: { chon: ThemeChoice }) {
  const chung = {
    viewBox: "0 0 24 24",
    "aria-hidden": true,
    className: "size-3.5 shrink-0",
    fill: "none",
    stroke: "currentColor",
    strokeWidth: 1.9,
    strokeLinecap: "round" as const,
    strokeLinejoin: "round" as const,
  };

  if (chon === "light") {
    return (
      <svg {...chung}>
        <circle cx="12" cy="12" r="4" />
        <path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4" />
      </svg>
    );
  }

  if (chon === "dark") {
    return (
      <svg {...chung}>
        <path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z" />
      </svg>
    );
  }

  return (
    <svg {...chung}>
      <rect x="2.5" y="4" width="19" height="13" rx="2" />
      <path d="M8 21h8" />
    </svg>
  );
}

function ChevronIcon({ className }: { className?: string }) {
  return (
    <svg
      viewBox="0 0 24 24"
      aria-hidden="true"
      className={cn("text-ink-faint size-4 shrink-0", className)}
      fill="none"
      stroke="currentColor"
      strokeWidth="2"
      strokeLinecap="round"
      strokeLinejoin="round"
    >
      <path d="m6 9 6 6 6-6" />
    </svg>
  );
}
