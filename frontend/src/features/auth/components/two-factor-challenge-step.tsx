"use client";

import { useState, type FormEvent } from "react";

import { ApiError, NetworkError } from "@/lib/api-client";

import {
  useTwoFactorChallenge,
  useTwoFactorResend,
  type TwoFactorChannel,
} from "../api/auth-api";
import {
  Field,
  FormError,
  GhostButton,
  StepHeading,
  SubmitButton,
} from "./form-primitives";
import { OtpInput } from "./otp-input";
import { ResendButton } from "./resend-button";

/** Bước hai: nhập mã, hoặc mã khôi phục khi không nhận được. */
export function TwoFactorChallengeStep({
  onVerified,
  channel,
  sentTo,
  canResend,
}: {
  onVerified: () => void;
  channel: TwoFactorChannel;
  sentTo: string | null;
  canResend: boolean;
}) {
  const challenge = useTwoFactorChallenge();
  const resend = useTwoFactorResend();
  const [dungMaKhoiPhuc, setDungMaKhoiPhuc] = useState(false);
  const [giaTri, setGiaTri] = useState("");
  const [loi, setLoi] = useState<string | null>(null);
  const [thongBao, setThongBao] = useState<string | null>(null);

  const guiMa = async (ma: string) => {
    setLoi(null);

    try {
      await challenge.mutateAsync(
        dungMaKhoiPhuc ? { recovery_code: ma } : { code: ma },
      );
      onVerified();
    } catch (error) {
      setLoi(
        error instanceof ApiError || error instanceof NetworkError
          ? error.message
          : "Đã xảy ra lỗi không xác định. Vui lòng thử lại.",
      );
    }
  };

  const guiLai = async () => {
    setLoi(null);
    setThongBao(null);
    setGiaTri("");

    try {
      await resend.mutateAsync();
      setThongBao("Đang gửi mã mới. Mã cũ không còn dùng được.");
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
    void guiMa(giaTri);
  };

  // "Đang gửi" chứ không phải "đã gửi": server nhận yêu cầu rồi trả về ngay,
  // thư rời hàng đợi sau đó khoảng một giây. Nói "đã gửi" trong khi hộp thư còn
  // trống là đẩy người dùng đi bấm "gửi lại" — thành ra chậm hơn chờ.
  const moTa = dungMaKhoiPhuc
    ? "Nhập một mã khôi phục bạn đã lưu. Mỗi mã chỉ dùng được một lần."
    : channel === "email"
      ? `Đang gửi mã gồm 6 số tới ${sentTo ?? "hộp thư của bạn"}. Thư thường tới sau vài giây và có hiệu lực trong 10 phút.`
      : "Mở ứng dụng xác thực trên điện thoại và nhập 6 số đang hiển thị.";

  return (
    <div className="rise-in">
      <StepHeading
        step={2}
        total={2}
        title="Xác thực đăng nhập"
        description={moTa}
      />

      <form onSubmit={onSubmit} noValidate className="space-y-5">
        {loi !== null && <FormError message={loi} />}

        {thongBao !== null && loi === null && (
          <p
            role="status"
            className="border-accent/30 bg-accent/[0.07] text-accent rounded-lg border px-3.5 py-2.5 text-[0.85rem]"
          >
            {thongBao}
          </p>
        )}

        {dungMaKhoiPhuc ? (
          <Field
            id="recovery-code"
            label="Mã khôi phục"
            placeholder="XXXXX-XXXXX"
            autoComplete="one-time-code"
            autoFocus
            className="font-mono"
            value={giaTri}
            onChange={(e) => setGiaTri(e.target.value.toUpperCase())}
          />
        ) : (
          <div className="space-y-2.5">
            <span className="text-ink-soft block font-mono text-[0.66rem] tracking-[0.18em] uppercase">
              Mã xác thực
            </span>
            <OtpInput
              value={giaTri}
              onChange={setGiaTri}
              // Đủ 6 số là gửi luôn, không bắt bấm thêm nút.
              onComplete={(ma) => void guiMa(ma)}
              disabled={challenge.isPending}
              invalid={loi !== null}
            />
          </div>
        )}

        <SubmitButton
          pending={challenge.isPending}
          disabled={dungMaKhoiPhuc ? giaTri === "" : giaTri.length < 6}
        >
          {challenge.isPending ? "Đang kiểm tra" : "Đăng nhập"}
        </SubmitButton>

        {canResend && !dungMaKhoiPhuc && (
          <ResendButton onResend={guiLai} pending={resend.isPending} />
        )}

        <GhostButton
          onClick={() => {
            setDungMaKhoiPhuc((truoc) => !truoc);
            setGiaTri("");
            setLoi(null);
            setThongBao(null);
          }}
        >
          {dungMaKhoiPhuc
            ? "Quay lại nhập mã thường"
            : "Không vào được hộp thư? Dùng mã khôi phục"}
        </GhostButton>
      </form>
    </div>
  );
}
