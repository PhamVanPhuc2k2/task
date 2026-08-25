"use client";

import Link from "next/link";
import { usePathname, useRouter } from "next/navigation";
import { useEffect, useState, type ReactNode } from "react";

import { useHeartbeat } from "@/features/attendance/hooks/use-heartbeat";
import { formatMinutes } from "@/features/attendance/types/attendance";
import { useCurrentUser } from "@/features/auth/api/auth-api";
import { ExplusMark } from "@/features/auth/components/explus-mark";
import { UserMenu } from "@/features/auth/components/user-menu";
import { NotificationBell } from "@/features/notifications/components/notification-bell";
import { useSiteBranding } from "@/features/settings/api/site-api";
import { cn } from "@/lib/cn";

import { CommandButton, CommandPalette } from "./command-palette";
import {
  coPhanDoi,
  isActive,
  visibleNavGroups,
  type NavSection,
} from "./nav-items";

/**
 * Khung chung của phần đã đăng nhập: thanh bên, đầu trang, vùng nội dung.
 *
 * Mobile-first — nhân viên dùng điện thoại là chính (xem README mục 1.7). Trên
 * màn hình hẹp thanh bên là ngăn kéo trượt ra; từ `lg` trở lên nó cố định bên
 * trái và ngăn kéo biến mất hoàn toàn.
 *
 * **Khung luôn được vẽ ngay, không chờ `/auth/me`.** Bản trước trả về một ô
 * vuông 40px giữa màn hình trắng cho tới khi biết người dùng là ai, và điều đó
 * hỏng theo hai cách cùng lúc:
 *
 * 1. Người dùng nhìn thấy **trang trắng tinh** — trông như hỏng, không như
 *    đang tải.
 * 2. `children` chưa được gắn vào cây nên các truy vấn của trang chưa chạy.
 *    Kết quả là hai lượt gọi nối đuôi: `/auth/me` xong mới tới `/users`. Trên
 *    máy dev Windows, mỗi lượt ~1,5 giây nên F5 mất tới ba giây.
 *
 * Giờ khung, thanh bên và nội dung được vẽ ngay; chỉ những chỗ THỰC SỰ cần dữ
 * liệu người dùng mới hiện khung xương. Các truy vấn chạy song song.
 */
export function AppShell({ children }: { children: ReactNode }) {
  const router = useRouter();
  const pathname = usePathname();
  const { data: user, isError } = useCurrentUser();
  const [drawerOpen, setDrawerOpen] = useState(false);
  const [lenhMo, setLenhMo] = useState(false);

  // Nhịp tim chấm công. Chỉ chạy khi đã biết người dùng là ai — gửi trước đó
  // là một lượt 401 vô ích mỗi lần tải trang.
  const phutHomNay = useHeartbeat(user !== undefined);

  // Phiên hết hạn giữa chừng: đưa về đăng nhập thay vì để màn hình trống.
  //
  // Xoá cờ TRƯỚC khi chuyển trang. Cờ còn sót mà server đã hết phiên thì proxy
  // vẫn cho vào, trang lại phát hiện 401 và chuyển tiếp — quay vòng mãi, người
  // dùng chỉ thấy màn hình trắng.
  useEffect(() => {
    if (isError) {
      document.cookie = "explus_auth=; Max-Age=0; path=/";
      router.replace("/login");
    }
  }, [isError, router]);

  // Chưa biết quyền thì chưa vẽ mục nào — vẽ bừa rồi rút lại còn khó chịu hơn
  // là hiện khung xương một nhịp.
  const nhomMuc = user ? visibleNavGroups(user.permissions) : null;
  const quyen = user?.permissions ?? [];

  return (
    <div className="relative min-h-screen lg:grid lg:grid-cols-[15.5rem_1fr]">
      {/* Hoa văn dấu cộng rất mờ, nằm dưới toàn bộ nội dung. `fixed` để nó
          đứng yên khi cuộn — hoa văn trôi theo nội dung gây nhiễu thị giác. */}
      <div
        aria-hidden="true"
        className="plus-wash pointer-events-none fixed inset-0 -z-10"
      />

      {/* Thanh bên cố định — chỉ từ lg trở lên. */}
      <aside className="border-line hidden lg:sticky lg:top-0 lg:flex lg:h-screen lg:flex-col lg:border-r">
        <Brand />
        <NavList sections={nhomMuc} permissions={quyen} pathname={pathname} />
        <SidebarFooter />
      </aside>

      {/* Ngăn kéo cho màn hình hẹp. */}
      {drawerOpen && (
        <div className="fixed inset-0 z-40 lg:hidden">
          <button
            type="button"
            aria-label="Đóng menu"
            onClick={() => setDrawerOpen(false)}
            className="absolute inset-0 bg-black/40 backdrop-blur-sm"
          />
          <div className="bg-paper shadow-pop absolute inset-y-0 left-0 flex w-[17rem] flex-col">
            <Brand />
            {/* Đóng ngăn kéo ngay khi bấm một mục, chứ không đợi effect chạy
                theo pathname: bấm xong mà ngăn kéo vẫn che kín trang vừa mở
                thì người dùng tưởng bấm hụt. */}
            <NavList
              sections={nhomMuc}
              permissions={quyen}
              pathname={pathname}
              onNavigate={() => setDrawerOpen(false)}
            />
            <SidebarFooter />
          </div>
        </div>
      )}

      <div className="flex min-h-screen flex-col">
        <header className="border-line bg-paper/70 sticky top-0 z-30 border-b backdrop-blur-xl">
          <div className="flex h-14 items-center justify-between gap-3 px-3 sm:px-6">
            <div className="flex items-center gap-2">
              <button
                type="button"
                onClick={() => setDrawerOpen(true)}
                aria-label="Mở menu"
                className="focus-frame text-ink-soft hover:bg-paper-raised hover:text-ink rounded-lg p-2 transition-colors lg:hidden"
              >
                <BurgerIcon />
              </button>

              <span className="flex items-center gap-2 lg:hidden">
                <Brand mini />
              </span>
            </div>

            <div className="flex items-center gap-1.5">
              {user ? (
                <>
                  <CommandButton onClick={() => setLenhMo(true)} />
                  <GioHomNay phut={phutHomNay} />
                  <NotificationBell />
                  <UserMenu user={user} />
                </>
              ) : (
                <div
                  aria-hidden="true"
                  className="bg-line/60 h-8 w-24 animate-pulse rounded-lg"
                />
              )}
            </div>
          </div>
        </header>

        <main className="flex-1 px-4 py-6 sm:px-6 sm:py-9">
          <div className="mx-auto w-full max-w-6xl">{children}</div>
        </main>
      </div>

      {/* Chỉ gắn khi đã biết quyền: bảng lệnh liệt kê đúng những trang người
          này vào được, và dựng nó với danh sách rỗng rồi thay sau sẽ cho một
          khoảnh khắc bấm Ctrl+K ra bảng trống. */}
      {user && (
        <CommandPalette
          permissions={user.permissions}
          mo={lenhMo}
          setMo={setLenhMo}
        />
      )}
    </div>
  );
}

/**
 * Giờ làm hôm nay, ngay trên đầu trang.
 *
 * Nhân viên **nhìn thấy đúng con số mà quản lý nhìn thấy**, theo thời gian
 * thực, không phải xin mới biết. Đó là khác biệt giữa tự theo dõi và bị theo
 * dõi lén — và là điều kiện để tính năng này không phá niềm tin.
 *
 * Ẩn cho tới khi có số đầu tiên: hiện "0h" ngay lúc vừa vào app trông như hệ
 * thống đang không ghi nhận gì.
 */
function GioHomNay({ phut }: { phut: number | null }) {
  if (phut === null) return null;

  return (
    <Link
      href="/attendance"
      title="Giờ làm hôm nay — bấm để xem cả tháng"
      className="focus-frame border-line bg-paper-raised text-ink-soft hover:border-line-strong hover:text-ink hidden items-center gap-1.5 rounded-lg border px-2.5 py-1 text-[0.8rem] font-medium tabular-nums transition-colors sm:inline-flex"
    >
      <span
        aria-hidden="true"
        className="bg-accent size-1.5 shrink-0 rounded-full"
      />
      {formatMinutes(phut)}
    </Link>
  );
}

/**
 * Logo và tên công ty, lấy từ cài đặt trang.
 *
 * Luôn có đường lùi: chưa tải nhận diện xong, hoặc chưa ai đặt logo, thì dùng
 * dấu cộng vẽ tay và tên mặc định. Không để khoảng trắng — một đầu trang trống
 * trong lúc chờ mạng trông như trang bị lỗi.
 */
function Brand({ mini = false }: { mini?: boolean }) {
  const { data } = useSiteBranding();

  const ten = data?.company_short_name ?? "explus";
  const logo = data?.logo_url ?? null;

  return (
    <div
      className={cn(
        "flex items-center gap-2.5",
        // Bản `mini` dùng ở đầu trang trên màn hình hẹp: bỏ chiều cao và lề
        // của thanh bên, vì nó nằm trong một thanh đã có sẵn cả hai.
        mini ? "" : "h-14 px-5",
      )}
    >
      {logo === null ? (
        // Dấu cộng đặt trong ô bo tròn nền lime nhạt: biến logo thành một vật
        // thể có khối thay vì một hình trôi nổi cạnh chữ.
        <span
          className={cn(
            "bg-accent-surface border-accent-line flex items-center justify-center rounded-lg border",
            mini ? "size-6" : "size-7",
          )}
        >
          <ExplusMark
            className={cn("text-accent-ink", mini ? "size-3.5" : "size-4")}
          />
        </span>
      ) : (
        // `object-contain` vì logo do người dùng tải lên, tỉ lệ không đoán được
        // — `cover` sẽ cắt mất hai đầu của một logo nằm ngang.
        // Logo hiển thị ở 24–80px và tối đa 1MB. Đưa qua `next/image` để tối
        // ưu một ảnh nhỏ như vậy là không đáng, và nó buộc server Next phải
        // với được host API — thêm `images.remotePatterns`, thêm một điểm
        // hỏng lúc chạy. Directive phải nằm NGAY dòng trên thẻ img.
        // eslint-disable-next-line @next/next/no-img-element
        <img
          src={logo}
          alt=""
          className={cn(
            "shrink-0 rounded-lg object-contain",
            mini ? "size-6" : "size-7",
          )}
        />
      )}
      <span
        className={cn(
          "leading-none font-semibold tracking-tight",
          mini ? "text-[0.95rem]" : "text-[1.08rem]",
        )}
      >
        {ten}
      </span>
    </div>
  );
}

function NavList({
  sections,
  permissions,
  pathname,
  onNavigate,
}: {
  /** `null` = chưa biết quyền của người dùng, hiện khung xương. */
  sections: NavSection[] | null;
  permissions: string[];
  pathname: string;
  onNavigate?: () => void;
}) {
  if (sections === null) {
    return (
      <nav aria-label="Điều hướng chính" className="flex-1 space-y-1 px-3 py-2">
        {Array.from({ length: 5 }, (_, i) => (
          <div
            key={i}
            aria-hidden="true"
            className="bg-line/60 h-9 animate-pulse rounded-xl"
          />
        ))}
      </nav>
    );
  }

  return (
    <nav
      aria-label="Điều hướng chính"
      className="flex-1 overflow-y-auto px-3 py-2"
    >
      {sections.map((phan) => (
        <div key={phan.group} className="mb-4 last:mb-0">
          {/*
            Tiêu đề nhóm là phần tử ngữ nghĩa, không phải chữ trang trí:
            `aria-labelledby` nối nó với danh sách bên dưới, nên trình đọc màn
            hình đọc "Quản trị, danh sách 3 mục" thay vì đổ liền một mạch mười
            hai liên kết không có ranh giới nào.
          */}
          {phan.label !== null && (
            <h2
              id={`nav-${phan.group}`}
              className="text-ink-faint mb-1.5 px-4 text-[0.68rem] font-semibold tracking-[0.09em] uppercase"
            >
              {phan.label}
            </h2>
          )}

          <ul
            aria-labelledby={
              phan.label === null ? undefined : `nav-${phan.group}`
            }
            className="space-y-0.5"
          >
            {phan.items.map((item) => {
              const active = isActive(item, pathname);
              const Icon = item.icon;
              const coDoi = coPhanDoi(item, permissions);

              return (
                <li key={item.href}>
                  <Link
                    href={item.href}
                    onClick={onNavigate}
                    aria-current={active ? "page" : undefined}
                    data-tone={item.tone}
                    className={cn(
                      "focus-tone group relative flex items-center gap-2.5 rounded-xl py-2 pr-3 pl-4 text-[0.89rem] transition-all duration-200",
                      active
                        ? "bg-tone-surface text-ink font-semibold"
                        : "text-ink-soft hover:bg-paper-raised hover:text-ink",
                    )}
                  >
                    {/*
                  Vạch sắc bên trái cho mục đang mở.

                  Đây là mảnh nối thị giác với vạch ở đầu trang: cùng một màu,
                  cùng một hình dạng, ở hai đầu màn hình. Mắt tự nối hai thứ đó
                  lại và người dùng biết mình đang ở đâu mà không phải đọc.
                */}
                    <span
                      aria-hidden="true"
                      className={cn(
                        "bg-tone absolute top-1/2 left-0 h-4 w-[3px] -translate-y-1/2 rounded-full transition-all duration-200",
                        active
                          ? "opacity-100"
                          : "opacity-0 group-hover:opacity-40",
                      )}
                    />

                    <Icon
                      className={cn(
                        "transition-colors",
                        active
                          ? "text-tone-ink"
                          : "text-ink-faint group-hover:text-ink-soft",
                      )}
                    />

                    <span className="truncate">{item.label}</span>

                    {/*
                  Viên nhãn "cả đội": trang này có thêm một tầng dành cho người
                  quản lý, ngay bên dưới phần của chính mình.

                  Không tách thành mục riêng vì chúng là MỘT trang. Nhưng cũng
                  không im lặng: thiếu dấu hiệu này thì "Chấm công" của giám đốc
                  và "Chấm công" của nhân viên trông y hệt nhau trên thanh bên,
                  trong khi mở ra là hai màn hình khác hẳn.
                */}
                    {coDoi && (
                      <span className="border-line text-ink-faint ml-auto shrink-0 rounded-full border px-1.5 py-px text-[0.63rem] leading-[1.35] font-medium">
                        cả đội
                      </span>
                    )}
                  </Link>
                </li>
              );
            })}
          </ul>
        </div>
      ))}
    </nav>
  );
}

function SidebarFooter() {
  return (
    <div className="text-ink-faint px-5 py-4 text-[0.72rem]">
      © {new Date().getFullYear()} Explus
    </div>
  );
}

function BurgerIcon() {
  return (
    <svg
      viewBox="0 0 24 24"
      aria-hidden="true"
      className="size-5"
      fill="none"
      stroke="currentColor"
      strokeWidth="1.75"
      strokeLinecap="round"
    >
      <path d="M4 7h16M4 12h16M4 17h16" />
    </svg>
  );
}
