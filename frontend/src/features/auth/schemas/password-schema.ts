import { z } from "zod";

/**
 * Khớp với App\Http\Requests\Auth\ResetPasswordRequest.
 *
 * Validate ở client chỉ để người dùng biết lỗi trước khi gửi. Server vẫn
 * validate lại — và server còn kiểm thêm một thứ client **không thể** kiểm:
 * mật khẩu có nằm trong danh sách đã bị lộ của HaveIBeenPwned không. Nên form
 * này có thể xanh mà server vẫn từ chối, và đó là đúng.
 */
export const forgotPasswordSchema = z.object({
  email: z
    .string()
    .min(1, "Vui lòng nhập email.")
    .email("Email không đúng định dạng."),
});

export type ForgotPasswordInput = z.infer<typeof forgotPasswordSchema>;

export const resetPasswordSchema = z
  .object({
    password: z
      .string()
      .min(12, "Mật khẩu phải có ít nhất 12 ký tự.")
      .regex(/\p{L}/u, "Mật khẩu phải có ít nhất một chữ cái.")
      .regex(/\d/, "Mật khẩu phải có ít nhất một chữ số."),
    password_confirmation: z.string().min(1, "Vui lòng nhập lại mật khẩu."),
  })
  .refine((v) => v.password === v.password_confirmation, {
    message: "Hai lần nhập không khớp.",
    path: ["password_confirmation"],
  });

export type ResetPasswordFormInput = z.infer<typeof resetPasswordSchema>;
