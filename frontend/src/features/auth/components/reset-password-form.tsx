"use client";

import { zodResolver } from "@hookform/resolvers/zod";
import Link from "next/link";
import { useRouter, useSearchParams } from "next/navigation";
import { useState } from "react";
import { useForm } from "react-hook-form";

import { ApiError, NetworkError } from "@/lib/api-client";

import { useResetPassword } from "../api/auth-api";
import {
  resetPasswordSchema,
  type ResetPasswordFormInput,
} from "../schemas/password-schema";
import {
  Field,
  FormError,
  Notice,
  StepHeading,
  SubmitButton,
} from "./form-primitives";

/**
 * Đặt mật khẩu mới bằng token trong email.
 *
 * `token` và `email` lấy từ query string do backend dựng ra. Thiếu một trong
 * hai thì hiện luôn màn hỏng thay vì để người dùng gõ xong mật khẩu rồi mới báo
 * lỗi — trường hợp này xảy ra thật khi ứng dụng email cắt mất đuôi đường dẫn.
 */
export function ResetPasswordForm() {
  const params = useSearchParams();
  const router = useRouter();
  const datLai = useResetPassword();
  const [xong, setXong] = useState(false);

  const token = params.get("token") ?? "";
  const email = params.get("email") ?? "";

  const {
    register,
    handleSubmit,
    setError,
    formState: { errors, isSubmitting },
  } = useForm<ResetPasswordFormInput>({
    resolver: zodResolver(resetPasswordSchema),
    defaultValues: { password: "", password_confirmation: "" },
  });

  const onSubmit = handleSubmit(async (values) => {
    try {
      await datLai.mutateAsync({
        token,
        email,
        password: values.password,
        password_confirmation: values.password_confirmation,
      });
      setXong(true);
    } catch (error) {
      if (error instanceof ApiError) {
        const loiMatKhau = error.fieldError("password");

        // Lỗi token đi vào dải lỗi chung: người dùng không sửa được nó bằng
        // cách gõ lại, họ phải xin gửi link mới.
        setError(loiMatKhau !== null ? "password" : "root", {
          message: loiMatKhau ?? error.message,
        });

        return;
      }

      setError("root", {
        message:
          error instanceof NetworkError
            ? error.message
            : "Đã xảy ra lỗi không xác định. Vui lòng thử lại.",
      });
    }
  });

  if (token === "" || email === "") {
    return (
      <div className="rise-in">
        <StepHeading
          step={2}
          total={2}
          title="Đường dẫn không hợp lệ"
          description="Đường dẫn thiếu thông tin. Có thể ứng dụng email đã cắt mất một phần khi bạn bấm vào."
        />

        <Link
          href="/forgot-password"
          className="bg-accent text-on-accent hover:bg-accent-hover focus-frame block w-full rounded-lg px-4 py-3.5 text-center text-[0.92rem] font-semibold tracking-tight transition-colors"
        >
          Xin đường dẫn mới
        </Link>
      </div>
    );
  }

  if (xong) {
    return (
      <div className="rise-in">
        <StepHeading
          step={2}
          total={2}
          title="Đã đổi mật khẩu"
          description="Mật khẩu mới đã có hiệu lực. Mọi thiết bị đang đăng nhập bằng mật khẩu cũ đã bị đăng xuất."
        />

        <Notice>
          Lần đăng nhập tới vẫn cần <strong>mã xác thực hai lớp</strong> như
          thường — đổi mật khẩu không tắt lớp bảo vệ đó.
        </Notice>

        <div className="mt-6">
          <button
            type="button"
            onClick={() => router.push("/login")}
            className="bg-accent text-on-accent hover:bg-accent-hover focus-frame w-full rounded-lg px-4 py-3.5 text-[0.92rem] font-semibold tracking-tight transition-colors"
          >
            Đăng nhập
          </button>
        </div>
      </div>
    );
  }

  return (
    <div className="rise-in">
      <StepHeading
        step={2}
        total={2}
        title="Đặt mật khẩu mới"
        description={
          <>
            Cho tài khoản <strong className="text-ink">{email}</strong>.
          </>
        }
      />

      <form onSubmit={onSubmit} noValidate className="space-y-5">
        {errors.root?.message !== undefined && (
          <FormError message={errors.root.message} />
        )}

        <Field
          id="password"
          label="Mật khẩu mới"
          type="password"
          autoComplete="new-password"
          placeholder="••••••••••••"
          autoFocus
          hint="Tối thiểu 12 ký tự, có cả chữ và số. Không dùng mật khẩu đã từng bị lộ."
          error={errors.password?.message}
          {...register("password")}
        />

        <Field
          id="password_confirmation"
          label="Nhập lại mật khẩu mới"
          type="password"
          autoComplete="new-password"
          placeholder="••••••••••••"
          error={errors.password_confirmation?.message}
          {...register("password_confirmation")}
        />

        <SubmitButton pending={isSubmitting}>
          {isSubmitting ? "Đang lưu" : "Đổi mật khẩu"}
        </SubmitButton>
      </form>

      <p className="text-ink-faint mt-6 text-center text-[0.82rem] leading-relaxed">
        Đường dẫn hết hạn?{" "}
        <Link
          href="/forgot-password"
          className="hover:text-accent focus-frame rounded underline decoration-dotted underline-offset-4 transition-colors"
        >
          Xin gửi lại
        </Link>
      </p>
    </div>
  );
}
