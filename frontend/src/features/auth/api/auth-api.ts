import {
  useMutation,
  useQuery,
  useQueryClient,
  type UseMutationResult,
  type UseQueryResult,
} from "@tanstack/react-query";

import { api, fetchCsrfCookie, type ApiError } from "@/lib/api-client";

import type { LoginInput } from "../schemas/login-schema";
import type { AuthUser, AuthUserResponse } from "../types/user";

export const authKeys = {
  me: ["auth", "me"] as const,
};

/** Kênh xác thực hai lớp mà backend đang dùng. */
export type TwoFactorChannel = "email" | "totp";

/**
 * Kết quả bước một. Mật khẩu đúng KHÔNG có nghĩa là đã đăng nhập —
 * hệ thống bắt buộc xác thực hai lớp với mọi tài khoản.
 */
export interface LoginStepOneResult {
  data: {
    /** Đã bật: sang bước nhập mã. Với kênh email, mã đã được gửi đi rồi. */
    two_factor_required?: boolean;
    /** Chưa bật: buộc thiết lập trước khi dùng được. */
    two_factor_setup_required?: boolean;
    channel?: TwoFactorChannel;
    can_resend?: boolean;
    /** Email đã che bớt, ví dụ `ng***@congty.vn`. */
    sent_to?: string | null;
  };
}

export interface TwoFactorSetupData {
  data: {
    channel: TwoFactorChannel;
    instructions: string;
    /** Chỉ có với kênh TOTP. */
    secret: string | null;
    qr_code_svg: string | null;
    /** Chỉ có với kênh email. */
    sent_to: string | null;
    can_resend: boolean;
  };
}

export interface TwoFactorResendResult {
  data: { sent_to: string; can_resend: boolean };
}

export interface TwoFactorConfirmResult {
  data: { recovery_codes: string[] };
}

export interface TwoFactorChallengeInput {
  code?: string;
  recovery_code?: string;
}

export function useCurrentUser(): UseQueryResult<AuthUser, ApiError> {
  return useQuery({
    queryKey: authKeys.me,
    queryFn: async () => (await api.get<AuthUserResponse>("/auth/me")).data,
    // Chưa đăng nhập trả 401 — đó là câu trả lời hợp lệ, không phải lỗi mạng.
    retry: false,
    staleTime: 5 * 60_000,
  });
}

export function useLoginStepOne(): UseMutationResult<
  LoginStepOneResult,
  ApiError,
  LoginInput
> {
  return useMutation({
    mutationFn: async (input: LoginInput) => {
      // Sanctum yêu cầu lấy cookie CSRF trước request ghi đầu tiên.
      await fetchCsrfCookie();

      return api.post<LoginStepOneResult>("/auth/login", input);
    },
  });
}

export interface ForgotPasswordResult {
  message: string;
}

/**
 * Xin link đặt lại mật khẩu.
 *
 * Backend **luôn** trả về cùng một câu dù email có tồn tại hay không — đừng
 * suy ra gì từ phản hồi và đừng hiện thông báo khác nhau, vì làm thế là dựng
 * lại đúng cái công cụ dò danh sách nhân sự mà backend cẩn thận tránh.
 */
export function useForgotPassword(): UseMutationResult<
  ForgotPasswordResult,
  ApiError,
  string
> {
  return useMutation({
    mutationFn: async (email: string) => {
      await fetchCsrfCookie();

      return api.post<ForgotPasswordResult>("/auth/forgot-password", { email });
    },
  });
}

export interface ResetPasswordInput {
  token: string;
  email: string;
  password: string;
  password_confirmation: string;
}

export function useResetPassword(): UseMutationResult<
  void,
  ApiError,
  ResetPasswordInput
> {
  return useMutation({
    mutationFn: async (input: ResetPasswordInput) => {
      await fetchCsrfCookie();

      return api.post<void>("/auth/reset-password", input);
    },
  });
}

export interface ChangePasswordInput {
  current_password: string;
  password: string;
  password_confirmation: string;
}

/**
 * Người đang đăng nhập tự đổi mật khẩu của mình.
 *
 * Khác `useResetPassword` ở chỗ **không cần token qua email**: người dùng đã
 * ngồi trong phiên rồi, nên thứ chứng minh danh tính là mật khẩu HIỆN TẠI.
 * Backend kiểm bằng rule `current_password` của Laravel.
 *
 * Không gọi `fetchCsrfCookie()` như hai hook kia: chúng chạy lúc CHƯA đăng
 * nhập nên có thể chưa có cookie nào; ở đây phiên đã có sẵn cookie CSRF từ lúc
 * đăng nhập, và `apiFetch` tự gửi kèm nó ở mỗi request ghi.
 *
 * Trả về 204, không có thân phản hồi.
 */
export function useChangePassword(): UseMutationResult<
  void,
  ApiError,
  ChangePasswordInput
> {
  return useMutation({
    mutationFn: (input) => api.patch<void>("/auth/password", input),
  });
}

export function useTwoFactorSetup(): UseMutationResult<
  TwoFactorSetupData,
  ApiError,
  void
> {
  return useMutation({
    mutationFn: () => api.get<TwoFactorSetupData>("/auth/two-factor/setup"),
  });
}

export function useTwoFactorResend(): UseMutationResult<
  TwoFactorResendResult,
  ApiError,
  void
> {
  return useMutation({
    mutationFn: () =>
      api.post<TwoFactorResendResult>("/auth/two-factor/resend"),
  });
}

export function useTwoFactorConfirm(): UseMutationResult<
  TwoFactorConfirmResult,
  ApiError,
  string
> {
  return useMutation({
    mutationFn: (code: string) =>
      api.post<TwoFactorConfirmResult>("/auth/two-factor/confirm", { code }),
  });
}

export function useTwoFactorChallenge(): UseMutationResult<
  AuthUser,
  ApiError,
  TwoFactorChallengeInput
> {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: async (input: TwoFactorChallengeInput) =>
      (await api.post<AuthUserResponse>("/auth/two-factor-challenge", input))
        .data,
    onSuccess: (user) => {
      queryClient.setQueryData(authKeys.me, user);
    },
  });
}

export function useLogout(): UseMutationResult<void, ApiError, void> {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: async () => {
      await api.post<void>("/auth/logout");
    },
    onSuccess: () => {
      // Xoá sạch cache: dữ liệu của người vừa đăng xuất không được để lại cho
      // người đăng nhập tiếp theo trên cùng máy nhìn thấy.
      queryClient.clear();
    },
  });
}
