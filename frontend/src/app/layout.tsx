import type { Metadata } from "next";
import { Be_Vietnam_Pro, JetBrains_Mono } from "next/font/google";
import "./globals.css";
import { SCRIPT_CHONG_NHAY } from "@/lib/theme";

import { Providers } from "./providers";

/**
 * Be Vietnam Pro: bộ chữ hình học do người Việt thiết kế, dựng dấu chuẩn và
 * đầy đủ. Đây là lý do chọn nó thay vì các font phổ thông — dấu tiếng Việt
 * trên font không có subset `vietnamese` sẽ bị ghép tạm và lệch.
 */
const beVietnam = Be_Vietnam_Pro({
  variable: "--font-be-vietnam",
  subsets: ["latin", "vietnamese"],
  weight: ["400", "500", "600", "700"],
  display: "swap",
});

/** Dùng cho mã OTP, mã khôi phục và nhãn kỹ thuật — chữ đều bề rộng dễ đối chiếu. */
const jetbrainsMono = JetBrains_Mono({
  variable: "--font-jetbrains",
  subsets: ["latin"],
  weight: ["400", "500"],
  display: "swap",
});

export const metadata: Metadata = {
  title: {
    default: "Explus",
    template: "%s · Explus",
  },
  description: "Hệ thống quản lý công việc và nhân sự nội bộ Explus",
};

export default function RootLayout({ children }: LayoutProps<"/">) {
  return (
    <html
      lang="vi"
      className={`${beVietnam.variable} ${jetbrainsMono.variable} h-full antialiased`}
      /*
      | Script chống nháy bên dưới gắn `data-theme` vào thẻ này TRƯỚC khi React
      | hydrate. HTML từ máy chủ không có thuộc tính đó, nên React thấy lệch và
      | cảnh báo — dù đây đúng là điều mình muốn xảy ra.
      |
      | Không phải chuyện thẩm mỹ ở console: một cảnh báo lệch hydrate luôn nổ
      | ra mỗi lần tải trang sẽ **che mất cảnh báo thật** khi nó xuất hiện, và
      | người ta quen mắt tới mức thôi đọc.
      |
      | `suppressHydrationWarning` chỉ có tác dụng đúng MỘT cấp — trên thẻ này
      | nó chỉ tắt cảnh báo cho thuộc tính của chính `<html>`, không giấu gì
      | bên trong.
      */
      suppressHydrationWarning
    >
      <head>
        {/*
          Đặt màu nền TRƯỚC khung hình đầu tiên.

          Không có đoạn này thì người để chế độ tối thấy một nháy trắng mỗi lần
          tải trang, vì React chỉ gắn `data-theme` sau khi hydrate xong. Xem
          `src/lib/theme.ts` để biết vì sao phải viết dưới dạng chuỗi.
        */}
        {/* Chuỗi hằng do chính dự án viết — không có dữ liệu người dùng đi
            vào đây, nên `dangerouslySetInnerHTML` ở đây là an toàn. */}
        <script dangerouslySetInnerHTML={{ __html: SCRIPT_CHONG_NHAY }} />
      </head>
      <body className="bg-paper text-ink flex min-h-full flex-col">
        <Providers>{children}</Providers>
      </body>
    </html>
  );
}
