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
});

export type LoginInput = z.infer<typeof loginSchema>;
