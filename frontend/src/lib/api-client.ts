/**
 * HTTP client dùng chung cho toàn bộ ứng dụng.
 *
 * Mọi lời gọi API đi qua đây để lỗi được chuẩn hoá về một dạng duy nhất.
 * Không gọi fetch() trực tiếp trong component.
 */

import { API_BASE_URL as BASE_URL } from "./api-url";

/** Dạng lỗi thống nhất mà backend trả về. Xem README mục 1.4. */
export interface ApiErrorBody {
  /** Thông báo hiển thị được cho người dùng, đã dịch sang tiếng Việt. */
  message: string;
  /** Mã lỗi nội bộ để frontend xử lý theo trường hợp, ví dụ `TASK_NOT_FOUND`. */
  code?: string;
  /** Lỗi validate theo từng field. */
  errors?: Record<string, string[]>;
}

export class ApiError extends Error {
  readonly status: number;
  readonly code: string | null;
  readonly errors: Record<string, string[]> | null;

  constructor(status: number, body: Partial<ApiErrorBody>) {
    super(body.message ?? "Đã xảy ra lỗi không xác định.");
    this.name = "ApiError";
    this.status = status;
    this.code = body.code ?? null;
    this.errors = body.errors ?? null;
  }

  /** Lấy thông báo lỗi đầu tiên của một field, dùng để hiển thị cạnh ô nhập. */
  fieldError(field: string): string | null {
    return this.errors?.[field]?.[0] ?? null;
  }
}

export class NetworkError extends Error {
  constructor(cause: unknown) {
    super("Không kết nối được tới máy chủ. Kiểm tra lại đường truyền.");
    this.name = "NetworkError";
    this.cause = cause;
  }
}

interface RequestOptions extends Omit<RequestInit, "body"> {
  body?: unknown;
  /** Tham số query, giá trị null/undefined sẽ bị bỏ qua. */
  query?: Record<string, string | number | boolean | null | undefined>;
}

function buildUrl(path: string, query?: RequestOptions["query"]): string {
  /*
  | `BASE_URL` có thể là đường dẫn TƯƠNG ĐỐI (`/api/v1`), và đó là chủ ý: ảnh
  | Docker build một lần chạy được ở mọi tên miền, vì trình duyệt tự phân giải
  | theo origin đang mở. Nhúng địa chỉ tuyệt đối vào lúc build là trói ảnh vào
  | đúng một tên miền.
  |
  | Nhưng `new URL()` đòi base **tuyệt đối**. Truyền base tương đối vào là nó
  | ném `TypeError` ngay — TRƯỚC khi có request nào rời trình duyệt.
  |
  | Hỏng im lặng đúng kiểu tệ nhất: tab Network không thấy request nào cả, còn
  | người dùng nhận câu "Không kết nối được tới máy chủ" trong khi máy chủ hoàn
  | toàn bình thường. Mọi lệnh curl từ phía server đều xanh. Đã mất một buổi vì
  | đúng lỗi này lúc deploy lên extask.us.
  */
  const goc =
    typeof window === "undefined" ? "http://localhost" : window.location.origin;

  const base = /^https?:\/\//.test(BASE_URL) ? BASE_URL : `${goc}${BASE_URL}`;

  const url = new URL(
    path.startsWith("/") ? path.slice(1) : path,
    base.endsWith("/") ? base : `${base}/`,
  );

  if (query) {
    for (const [key, value] of Object.entries(query)) {
      if (value !== null && value !== undefined) {
        url.searchParams.set(key, String(value));
      }
    }
  }

  return url.toString();
}

const BACKEND_URL =
  process.env.NEXT_PUBLIC_BACKEND_URL ?? "http://localhost:8000";

/**
 * Lấy cookie CSRF của Sanctum.
 *
 * Bắt buộc gọi trước request ghi đầu tiên của một phiên. Sanctum đặt cookie
 * `XSRF-TOKEN`, và mọi POST/PATCH/DELETE sau đó phải gửi lại giá trị đó ở
 * header `X-XSRF-TOKEN`, nếu không sẽ bị chặn với lỗi 419.
 */
export async function fetchCsrfCookie(): Promise<void> {
  await fetch(`${BACKEND_URL}/sanctum/csrf-cookie`, {
    credentials: "include",
    headers: { Accept: "application/json" },
  });
}

/** Đọc giá trị một cookie. Trả về null khi chạy trên server. */
function readCookie(name: string): string | null {
  if (typeof document === "undefined") {
    return null;
  }

  const match = document.cookie.match(
    new RegExp(
      `(?:^|; )${name.replace(/[.*+?^${}()|[\]\\]/g, "\\$&")}=([^;]*)`,
    ),
  );

  return match?.[1] === undefined ? null : decodeURIComponent(match[1]);
}

export async function apiFetch<T>(
  path: string,
  { body, query, headers, ...init }: RequestOptions = {},
): Promise<T> {
  let response: Response;

  const csrfToken = readCookie("XSRF-TOKEN");

  // Tải tệp lên gửi FormData chứ không gửi JSON. Với FormData thì KHÔNG được
  // tự đặt Content-Type: trình duyệt phải tự sinh header kèm chuỗi `boundary`,
  // đặt tay là mất boundary và server đọc ra một thân request rỗng.
  const laFormData =
    typeof FormData !== "undefined" && body instanceof FormData;

  try {
    response = await fetch(buildUrl(path, query), {
      ...init,
      // Sanctum xác thực bằng cookie, bắt buộc gửi kèm.
      credentials: "include",
      headers: {
        Accept: "application/json",
        ...(body !== undefined &&
          !laFormData && { "Content-Type": "application/json" }),
        ...(csrfToken !== null && { "X-XSRF-TOKEN": csrfToken }),
        ...headers,
      },
      ...(body !== undefined && {
        body: laFormData ? (body as FormData) : JSON.stringify(body),
      }),
    });
  } catch (cause) {
    throw new NetworkError(cause);
  }

  if (response.status === 204) {
    return undefined as T;
  }

  const text = await response.text();
  let payload: unknown = null;

  if (text.length > 0) {
    try {
      payload = JSON.parse(text);
    } catch {
      // Backend trả về thứ không phải JSON — thường là trang lỗi 500 của server.
      throw new ApiError(response.status, {
        message: "Máy chủ trả về dữ liệu không hợp lệ.",
      });
    }
  }

  if (!response.ok) {
    throw new ApiError(
      response.status,
      (payload ?? {}) as Partial<ApiErrorBody>,
    );
  }

  return payload as T;
}

export const api = {
  get: <T>(path: string, options?: RequestOptions) =>
    apiFetch<T>(path, { ...options, method: "GET" }),

  post: <T>(path: string, body?: unknown, options?: RequestOptions) =>
    apiFetch<T>(path, { ...options, method: "POST", body }),

  patch: <T>(path: string, body?: unknown, options?: RequestOptions) =>
    apiFetch<T>(path, { ...options, method: "PATCH", body }),

  put: <T>(path: string, body?: unknown, options?: RequestOptions) =>
    apiFetch<T>(path, { ...options, method: "PUT", body }),

  delete: <T>(path: string, options?: RequestOptions) =>
    apiFetch<T>(path, { ...options, method: "DELETE" }),
};
