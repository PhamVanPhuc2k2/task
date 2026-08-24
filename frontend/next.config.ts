import type { NextConfig } from "next";

const nextConfig: NextConfig = {
  /*
  | Gói kèm đúng những gì cần để chạy, không mang cả node_modules.
  |
  | Không có dòng này thì image production phải chép nguyên `node_modules` —
  | vài trăm MB cho một ứng dụng mà phần lớn phụ thuộc chỉ dùng lúc build.
  | `standalone` sinh ra `.next/standalone/server.js` cùng đúng các gói nó thật
  | sự nạp lúc chạy.
  */
  output: "standalone",

  /*
  | Không lộ phiên bản Next trong header phản hồi.
  |
  | `X-Powered-By: Next.js` nói cho người quét biết đang chạy gì để họ tra thẳng
  | danh sách lỗ hổng của đúng phiên bản đó. Không phải lớp bảo vệ, nhưng là
  | thông tin không có lý do gì để cho đi.
  */
  poweredByHeader: false,
};

export default nextConfig;
