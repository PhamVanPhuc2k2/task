"use client";

import { zodResolver } from "@hookform/resolvers/zod";
import Link from "next/link";
import { useForm } from "react-hook-form";

import { ApiError, NetworkError } from "@/lib/api-client";

import { useLoginStepOne, type TwoFactorChannel } from "../api/auth-api";
import { loginSchema, type LoginInput } from "../schemas/login-schema";
import { Field, FormError, StepHeading, SubmitButton } from "./form-primitives";

/** Bước một: email và mật khẩu. */
export function CredentialsStep({
  onTwoFactorRequired,
  onSetupRequired,
}: {
  onTwoFactorRequired: (
    channel: TwoFactorChannel,
    sentTo: string | null,
    canResend: boolean,
  ) => void;
  onSetupRequired: () => void;
}) {
  const login = useLoginStepOne();

  const {
    register,
    handleSubmit,
    setError,
    formState: { errors, isSubmitting },
  } = useForm<LoginInput>({
    resolver: zodResolver(loginSchema),
    defaultValues: { email: "", password: "", remember: false },
  });

  const onSubmit = handleSubmit(async (values) => {
    try {
      const result = await login.mutateAsync(values);

      if (result.data.two_factor_required === true) {
        onTwoFactorRequired(
          result.data.channel ?? "email",
          result.data.sent_to ?? null,
          result.data.can_resend ?? false,
        );
      } else {
        onSetupRequired();
      }
    } catch (error) {
      if (error instanceof ApiError) {
        const emailError = error.fieldError("email");
        const passwordError = error.fieldError("password");

        if (emailError !== null) setError("email", { message: emailError });
        if (passwordError !== null)
          setError("password", { message: passwordError });

        // Sai mật khẩu, tài khoản bị khoá, quá số lần thử: hiện ở đầu form.
        if (emailError === null && passwordError === null) {
          setError("root", { message: error.message });
        }

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

  return (
    <div className="rise-in">
      <StepHeading
        step={1}
        total={2}
        title="Chào mừng trở lại"
        description="Đăng nhập bằng tài khoản công ty cấp."
      />

      <form onSubmit={onSubmit} noValidate className="space-y-5">
        {errors.root?.message !== undefined && (
          <FormError message={errors.root.message} />
        )}

        <Field
          id="email"
          label="Email"
          type="email"
          autoComplete="username"
          placeholder="ten.ban@explus.vn"
          autoFocus
          error={errors.email?.message}
          {...register("email")}
        />

        <Field
          id="password"
          label="Mật khẩu"
          type="password"
          autoComplete="current-password"
          placeholder="••••••••••••"
          error={errors.password?.message}
          {...register("password")}
        />

        {/*
          Mặc định KHÔNG tích.

          Đăng nhập lại ở hệ thống này tốn một vòng email OTP chứ không phải chỉ
          gõ mật khẩu, nên ghi nhớ là thứ đáng giá — nhưng nó phải là lựa chọn
          của người dùng, không phải mặc định áp cho cả máy mượn lẫn máy chung.
        */}
        <label className="flex cursor-pointer items-start gap-2.5">
          <input
            type="checkbox"
            className="accent-accent mt-0.5 size-4 shrink-0"
            {...register("remember")}
          />
          <span className="text-ink-soft text-[0.84rem] leading-snug">
            Ghi nhớ đăng nhập trên máy này
            <span className="text-ink-faint block text-[0.78rem]">
              Đỡ phải chờ mã OTP mỗi lần quay lại. Đừng tích nếu đây là máy dùng
              chung.
            </span>
          </span>
        </label>

        <SubmitButton pending={isSubmitting}>
          {isSubmitting ? "Đang kiểm tra" : "Tiếp tục"}
        </SubmitButton>
      </form>

      <p className="text-ink-faint mt-6 text-center text-[0.82rem] leading-relaxed">
        <Link
          href="/forgot-password"
          className="hover:text-accent focus-frame rounded underline decoration-dotted underline-offset-4 transition-colors"
        >
          Quên mật khẩu?
        </Link>
      </p>
    </div>
  );
}
