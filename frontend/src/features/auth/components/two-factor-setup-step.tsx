"use client";

import { useEffect, useState, type FormEvent } from "react";

import { ApiError } from "@/lib/api-client";

import {
  useTwoFactorConfirm,
  useTwoFactorResend,
  useTwoFactorSetup,
} from "../api/auth-api";
import {
  FormError,
  Notice,
  StepHeading,
  SubmitButton,
} from "./form-primitives";
import { OtpInput } from "./otp-input";
import { ResendButton } from "./resend-button";

/**
 * Thiết lập lần đầu.
 *
 * Kênh email: mã đã được gửi tới hộp thư, chỉ cần nhập.
 * Kênh TOTP: quét mã QR rồi nhập mã ứng dụng hiển thị.
 *
 * Bắt buộc với nhân viên mới — hệ thống không cho vào nếu chưa bật.
 */
export function TwoFactorSetupStep({
  onConfirmed,
}: {
  onConfirmed: (recoveryCodes: string[]) => void;
}) {
  const setup = useTwoFactorSetup();
  const confirm = useTwoFactorConfirm();
  const resend = useTwoFactorResend();
  const [code, setCode] = useState("");
  const [loi, setLoi] = useState<string | null>(null);

  const { mutate: taiThietLap } = setup;

  useEffect(() => {
    taiThietLap();
  }, [taiThietLap]);

  const xacNhan = async (ma: string) => {
    setLoi(null);

    try {
      const result = await confirm.mutateAsync(ma);
      onConfirmed(result.data.recovery_codes);
    } catch (error) {
      setLoi(
        error instanceof ApiError
          ? error.message
          : "Đã xảy ra lỗi không xác định. Vui lòng thử lại.",
      );
    }
  };

  const guiLai = async () => {
    setLoi(null);
    setCode("");

    try {
      await resend.mutateAsync();
    } catch (error) {
      setLoi(
        error instanceof ApiError
          ? error.message
          : "Không gửi lại được. Vui lòng thử lại sau.",
      );
    }
  };

  const onSubmit = (event: FormEvent) => {
    event.preventDefault();
    void xacNhan(code);
  };

  const duLieu = setup.data?.data;

  return (
    <div className="rise-in">
      <StepHeading
        step={2}
        total={3}
        title="Bảo vệ tài khoản"
        description="Công ty yêu cầu xác thực hai lớp với mọi nhân viên. Chỉ mất một phút và chỉ làm một lần."
      />

      <div className="space-y-5">
        {setup.isPending && (
          <div className="bg-line h-52 animate-pulse rounded-xl" />
        )}

        {duLieu !== undefined && (
          <>
            <Notice>{duLieu.instructions}</Notice>

            {/* Mã QR chỉ có ở kênh TOTP. */}
            {duLieu.qr_code_svg !== null && (
              <div className="border-line rounded-xl border bg-white/[0.03] p-5">
                <div
                  className="mx-auto w-fit rounded-lg bg-white p-3 shadow-[0_0_40px_-12px_var(--accent)] [&_svg]:block [&_svg]:h-40 [&_svg]:w-40"
                  // SVG do chính backend sinh bằng thư viện QR, không phải dữ
                  // liệu người dùng nhập — chèn trực tiếp là an toàn.
                  dangerouslySetInnerHTML={{ __html: duLieu.qr_code_svg }}
                />

                {duLieu.secret !== null && (
                  <details className="border-line mt-5 border-t pt-4 text-[0.82rem]">
                    <summary className="text-ink-soft hover:text-accent focus-frame cursor-pointer rounded transition-colors">
                      Không quét được mã?
                    </summary>
                    <p className="text-ink-faint mt-2.5">
                      Nhập tay khoá này vào ứng dụng:
                    </p>
                    <code className="border-line text-ink mt-2 block rounded-lg border bg-black/30 px-3 py-2.5 font-mono text-[0.78rem] tracking-wider break-all">
                      {duLieu.secret}
                    </code>
                  </details>
                )}
              </div>
            )}

            <form onSubmit={onSubmit} noValidate className="space-y-5">
              {loi !== null && <FormError message={loi} />}

              <div className="space-y-2.5">
                <span className="text-ink-soft block font-mono text-[0.66rem] tracking-[0.18em] uppercase">
                  Nhập mã để xác nhận
                </span>
                <OtpInput
                  value={code}
                  onChange={setCode}
                  onComplete={(ma) => void xacNhan(ma)}
                  disabled={confirm.isPending}
                  invalid={loi !== null}
                />
              </div>

              <SubmitButton
                pending={confirm.isPending}
                disabled={code.length < 6}
              >
                {confirm.isPending ? "Đang xác nhận" : "Bật xác thực hai lớp"}
              </SubmitButton>

              {duLieu.can_resend && (
                <ResendButton onResend={guiLai} pending={resend.isPending} />
              )}
            </form>
          </>
        )}
      </div>
    </div>
  );
}
