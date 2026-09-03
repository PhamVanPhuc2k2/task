import type { MetadataRoute } from "next";

import { API_BASE_URL } from "@/lib/api-url";

/**
 * Manifest để cài Explus lên màn hình chính điện thoại.
 *
 * Cố tình CHƯA có service worker. Một service worker cache sai trên ứng dụng
 * dữ liệu sống là loại lỗi tệ nhất: nhân viên thấy danh sách việc của hôm kia,
 * tưởng đã xong hết, và không có cách nào tự sửa ngoài xoá dữ liệu trình duyệt.
 * Việc đó để mục 1.10 làm cùng lúc với chiến lược cache và cơ chế cập nhật.
 *
 * Không có SW thì trang vẫn cài lên màn hình chính và chạy toàn màn hình được,
 * chỉ là không chạy được khi mất mạng — đúng với bản chất ứng dụng này.
 */
export default function manifest(): MetadataRoute.Manifest {
  return {
    name: "Explus — Quản lý công việc",
    short_name: "Explus",
    description: "Hệ thống quản lý công việc và nhân sự nội bộ Explus",
    start_url: "/",
    display: "standalone",
    orientation: "portrait",
    lang: "vi",
    background_color: "#0c0d09",
    theme_color: "#0c0d09",
    /*
    | Cùng một đường với biểu tượng trên tab (`app/layout.tsx`), nên đổi biểu
    | tượng trong Cài đặt trang là đổi cả hai. Trước đây chỗ này trỏ thẳng vào
    | `/icon.svg` còn tab lại lấy `favicon.ico` mặc định của Next — cùng một ứng
    | dụng mà màn hình chính và trình duyệt hiện hai nhận diện khác nhau.
    |
    | KHÔNG khai `type`: ảnh trả về là PNG hoặc WebP nếu công ty đã tải lên, và
    | SVG nếu chưa. Khai cứng một kiểu thì nó sai đúng một nửa số trường hợp,
    | mà `type` vốn là trường tuỳ chọn.
    */
    icons: [
      {
        src: `${API_BASE_URL}/site/icon`,
        sizes: "any",
        purpose: "any",
      },
    ],
  };
}
