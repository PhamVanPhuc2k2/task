"use client";

import { zodResolver } from "@hookform/resolvers/zod";
import Link from "next/link";
import { useState } from "react";
import { useForm } from "react-hook-form";

import { ApiError, NetworkError } from "@/lib/api-client";

import { useForgotPassword } from "../api/auth-api";
import {
  forgotPasswordSchema,
  type ForgotPasswordInput,
} from "../schemas/password-schema";
import { Field, FormError, Notice, StepHeading } from "./form-primitives";
import { SubmitButton } from "./form-primitives";

/**
 * Xin link đặt lại mật khẩu.
 *
 * **Màn hình sau khi gửi không nói email có tồn tại hay không.** Backend đã cẩn
 * thận trả về cùng một câu cho mọi email; nếu ở đây hiện "đã gửi tới X" hay
 * "không tìm thấy email" thì công sức đó đổ sông — trang này lại thành công cụ
 * dò danh sách nhân sự của cả công ty.
 */
export function ForgotPasswordForm() {
  const xin = useForgotPassword();
  const [daGui, setDaGui] = useState(false);

  const {
    register,
    handleSubmit,
    setError,
    formState: { errors, isSubmitting },
  } = useForm<ForgotPasswordInput>({
    resolver: zodResolver(forgotPasswordSchema),
    defaultValues: { email: "" },
  });

  const onSubmit = handleSubmit(async (values) => {
    try {
      await xin.mutateAsync(values.email);
      setDaGui(true);
    } catch (error) {
      if (error instanceof ApiError) {
        const loi = error.fieldError("email");
        setError(loi !== null ? "email" : "root", {
          message: loi ?? error.message,
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

  if (daGui) {
    return (
      <div className="rise-in">
        <StepHeading
          step={1}
          total={2}
          title="Kiểm tra hộp thư"
          description="Nếu email vừa nhập có trong hệ thống, chúng tôi đã gửi một đường dẫn đặt lại mật khẩu."
        />

        <Notice>
          Đường dẫn hết hạn sau <strong>60 phút</strong> và chỉ dùng được một
          lần. Không thấy thư thì kiểm tra hộp thư rác.
        </Notice>

        <p className="text-ink-faint mt-6 text-center text-[0.82rem] leading-relaxed">
          <Link
            href="/login"
            className="hover:text-accent focus-frame rounded underline decoration-dotted underline-offset-4 transition-colors"
          >
            Quay lại đăng nhập
          </Link>
        </p>
      </div>
    );
  }

  return (
    <div className="rise-in">
      <StepHeading
        step={1}
        total={2}
        title="Quên mật khẩu"
        description="Nhập email công ty của bạn. Chúng tôi sẽ gửi một đường dẫn để đặt mật khẩu mới."
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

        <SubmitButton pending={isSubmitting}>
          {isSubmitting ? "Đang gửi" : "Gửi đường dẫn"}
        </SubmitButton>
      </form>

      <p className="text-ink-faint mt-6 text-center text-[0.82rem] leading-relaxed">
        Nhớ ra rồi?{" "}
        <Link
          href="/login"
          className="hover:text-accent focus-frame rounded underline decoration-dotted underline-offset-4 transition-colors"
        >
          Quay lại đăng nhập
        </Link>
      </p>
    </div>
  );
}
