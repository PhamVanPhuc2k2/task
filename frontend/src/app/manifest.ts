import type { MetadataRoute } from "next";

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
    icons: [
      {
        src: "/icon.svg",
        sizes: "any",
        type: "image/svg+xml",
        purpose: "any",
      },
    ],
  };
}
