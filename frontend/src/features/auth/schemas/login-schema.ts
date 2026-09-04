import { z } from "zod";

/**
 * Khớp với App\Http\Requests\Auth\LoginRequest phía backend.
 *
 * Validate ở client chỉ để người dùng biết lỗi trước khi gửi. Server vẫn
 * validate lại — xem README, bảng quy ước frontend.
 */
export const loginSchema = z.object({
  email: z
    .string()
    .min(1, "Vui lòng nhập email.")
    .email("Email không đúng định dạng."),
  password: z.string().min(1, "Vui lòng nhập mật khẩu."),
  /**
   * Ghi nhớ đăng nhập trên máy này.
   *
   * Bật thì Laravel phát thêm một cookie sống 400 ngày, và mỗi lần phiên ngắn
   * hết hạn nó tự lập lại phiên mới — không phải chờ mã OTP qua email. Đúng vai
   * trò của refresh token.
   */
  remember: z.boolean(),
});

export type LoginInput = z.infer<typeof loginSchema>;
