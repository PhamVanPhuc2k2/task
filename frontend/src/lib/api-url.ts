/**
 * Gốc của API — **nguồn sự thật duy nhất** cho địa chỉ backend.
 *
 * Tách khỏi `api-client.ts` vì hai chỗ chạy trên MÁY CHỦ cần đúng chuỗi này mà
 * không cần cả tầng HTTP: `app/layout.tsx` khai đường dẫn biểu tượng, và
 * `app/manifest.ts` khai icon cho PWA.
 *
 * Giá trị có thể là đường dẫn TƯƠNG ĐỐI (`/api/v1`) — đó là chủ ý, để ảnh Docker
 * build một lần chạy được ở mọi tên miền. Vì vậy chuỗi này chỉ dùng được ở nơi
 * **trình duyệt** phân giải nó; đừng `fetch()` nó từ phía server Next.
 */
export const API_BASE_URL =
  process.env.NEXT_PUBLIC_API_URL ?? "http://localhost:8000/api/v1";
