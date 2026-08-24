"use client";

import { useQueryClient } from "@tanstack/react-query";
import { useRouter, useSearchParams } from "next/navigation";
import { useState } from "react";

import { authKeys, type TwoFactorChannel } from "../api/auth-api";
import type { AuthUser } from "../types/user";
import { CredentialsStep } from "./credentials-step";
import { RecoveryCodesStep } from "./recovery-codes-step";
import { TwoFactorChallengeStep } from "./two-factor-challenge-step";
import { TwoFactorSetupStep } from "./two-factor-setup-step";

/**
 * Điều phối các bước đăng nhập.
 *
 * Hệ thống bắt buộc xác thực hai lớp với mọi tài khoản, nên đúng mật khẩu chưa
 * phải là vào được:
 *
 *   nhập mật khẩu ──┬─ đã bật  ──→ nhập mã ────────────────────────→ vào app
 *                   └─ chưa bật ──→ nhận mã → nhập → lưu mã khôi phục → vào app
 *
 * Trạng thái "đã qua bước mật khẩu" nằm ở session phía server, không ở đây —
 * client chỉ nhớ đang hiển thị màn nào.
 */
type Step =
  | { name: "credentials" }
  | {
      name: "challenge";
      channel: TwoFactorChannel;
      sentTo: string | null;
      canResend: boolean;
    }
  | { name: "setup" }
  | { name: "recovery-codes"; codes: string[] };

export function AuthFlow() {
  const router = useRouter();
  const searchParams = useSearchParams();
  const [step, setStep] = useState<Step>({ name: "credentials" });

  const queryClient = useQueryClient();

  /**
   * Vào app sau khi xác thực xong.
   *
   * Người nhìn được toàn công ty (quản trị viên, giám đốc) đi thẳng tới màn
   * Tổng quan. Trang mặc định "Hôm nay của tôi" lọc theo `assignee_id` của
   * chính họ, mà không ai giao việc cho giám đốc — nên với họ, màn hình đầu
   * tiên sau khi đăng nhập là một dòng "Bạn không còn việc nào đang mở".
   *
   * Quyền đọc từ đệm mà bước nhập mã vừa ghi vào (`setQueryData(authKeys.me)`),
   * nên không tốn thêm request. Không có trong đệm — đường thiết lập 2FA lần
   * đầu — thì về `/` như cũ; màn đó có sẵn lối rẽ sang Tổng quan.
   *
   * `redirect` trên URL luôn được ưu tiên: người dùng bấm vào một liên kết sâu
   * rồi bị hỏi đăng nhập thì phải quay về đúng chỗ họ định tới.
   */
  const goToApp = () => {
    const dich = searchParams.get("redirect");

    if (dich !== null) {
      router.replace(dich);

      return;
    }

    const user = queryClient.getQueryData<AuthUser>(authKeys.me);

    router.replace(
      user?.permissions.includes("task.view.all") === true ? "/overview" : "/",
    );
  };

  switch (step.name) {
    case "credentials":
      return (
        <CredentialsStep
          onTwoFactorRequired={(channel, sentTo, canResend) =>
            setStep({ name: "challenge", channel, sentTo, canResend })
          }
          onSetupRequired={() => setStep({ name: "setup" })}
        />
      );

    case "challenge":
      return (
        <TwoFactorChallengeStep
          onVerified={goToApp}
          channel={step.channel}
          sentTo={step.sentTo}
          canResend={step.canResend}
        />
      );

    case "setup":
      return (
        <TwoFactorSetupStep
          onConfirmed={(codes) => setStep({ name: "recovery-codes", codes })}
        />
      );

    case "recovery-codes":
      return <RecoveryCodesStep codes={step.codes} onDone={goToApp} />;
  }
}
