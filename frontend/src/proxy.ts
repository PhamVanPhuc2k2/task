import { NextResponse, type NextRequest } from "next/server";

/**
 * Route guard.
 *
 * Từ Next.js 16, Middleware đổi tên thành Proxy và file phải đặt tại
 * `src/proxy.ts`. Đặt tên `middleware.ts` như bản cũ thì file không chạy và
 * không có cảnh báo nào.
 *
 * Đây CHỈ là kiểm tra lạc quan để người chưa đăng nhập không phải tải cả trang
 * rồi mới bị đá ra. Tài liệu Next.js nói rõ Proxy không dùng làm lớp phân
 * quyền. Phân quyền thật nằm ở backend: mọi endpoint đều qua `auth:sanctum` +
 * `active` + Policy. Cờ giả không lừa được server.
 */

/**
 * Cờ do backend đặt khi đăng nhập thành công, xoá khi đăng xuất.
 *
 * KHÔNG dùng cookie phiên của Laravel (`explus_session`) làm tín hiệu: Laravel
 * cấp cookie đó cho MỌI người, kể cả khách vừa mở trang đăng nhập. Đã từng
 * dùng nhầm và tạo ra vòng lặp chuyển hướng bất tận giữa `/` và `/login` —
 * màn hình trắng, không báo lỗi gì.
 */
const AUTH_FLAG = "explus_auth";

/**
 * Đường vào không cần đăng nhập.
 *
 * Hai trang mật khẩu bắt buộc phải nằm đây: người đang cần chúng chính là người
 * không đăng nhập được. Quên khai thì link trong email dẫn thẳng về `/login`,
 * và tính năng vô dụng đúng với người nó nhắm tới.
 */
const PUBLIC_PATHS = ["/login", "/forgot-password", "/reset-password"];

export function proxy(request: NextRequest): NextResponse {
  const { pathname } = request.nextUrl;

  const isPublic = PUBLIC_PATHS.some((path) => pathname.startsWith(path));

  if (isPublic) {
    // Cố tình KHÔNG đá người có cờ về trang chủ.
    //
    // Cờ có thể cũ (phiên đã hết hạn phía server mà cookie còn), và nếu ở đây
    // đá về `/` trong khi `/` lại đá về `/login` thì thành vòng lặp. Người đã
    // đăng nhập mà mở lại `/login` chỉ thấy form — vô hại.
    return NextResponse.next();
  }

  if (!request.cookies.has(AUTH_FLAG)) {
    const loginUrl = new URL("/login", request.url);
    // Nhớ trang người dùng định vào để đăng nhập xong quay lại đúng chỗ.
    loginUrl.searchParams.set("redirect", pathname);

    return NextResponse.redirect(loginUrl);
  }

  return NextResponse.next();
}

export const config = {
  matcher: [
    // Bỏ qua tài nguyên tĩnh và tệp có phần mở rộng.
    "/((?!_next/static|_next/image|favicon.ico|.*\\..*).*)",
  ],
};
