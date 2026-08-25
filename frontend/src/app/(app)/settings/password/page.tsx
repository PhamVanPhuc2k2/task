"use client";

import Link from "next/link";
import { useState } from "react";

import { Button } from "@/components/ui/button";
import { Field, TextInput } from "@/components/ui/field";
import { useChangePassword } from "@/features/auth/api/auth-api";

/**
 * Người dùng tự đổi mật khẩu của mình.
 *
 * ## Vì sao màn này tồn tại
 *
 * Endpoint `PATCH /auth/password` đã có từ lâu và đã có test, nhưng **không có
 * chỗ nào trong giao diện gọi tới nó**. Nghĩa là đường duy nhất để đổi mật
 * khẩu là: đăng xuất → "Quên mật khẩu" → mở email → bấm link.
 *
 * Ghép với việc hệ thống không bắt đổi mật khẩu ở lần đăng nhập đầu, chuỗi
 * hỏng ra thế này:
 *
 *   HR đọc mật khẩu tạm cho nhân viên → nhân viên đăng nhập được → không có
 *   gì nhắc đổi → mà muốn đổi cũng không tìm thấy chỗ nào → mật khẩu HR biết,
 *   có thể còn nằm trong Zalo, sống mãi.
 *
 * Với hệ thống giữ dữ liệu lương thì đó là lỗ thật.
 *
 * ## Vì sao vẫn hỏi mật khẩu hiện tại
 *
 * Người dùng đã ngồi trong phiên rồi, nên nghe như thừa. Nhưng nó chặn đúng
 * một tình huống: máy bỏ quên không khoá màn hình. Không có ô đó thì bất kỳ ai
 * đi ngang cũng đổi được mật khẩu và chiếm luôn tài khoản — mà nạn nhân chỉ
 * phát hiện khi lần sau đăng nhập không được.
 */
export default function ChangePasswordPage() {
  const doi = useChangePassword();

  const [hienTai, setHienTai] = useState("");
  const [moi, setMoi] = useState("");
  const [nhapLai, setNhapLai] = useState("");
  const [xong, setXong] = useState(false);

  const loi = doi.error;

  function luu(su: React.FormEvent): void {
    su.preventDefault();

    doi.mutate(
      {
        current_password: hienTai,
        password: moi,
        password_confirmation: nhapLai,
      },
      {
        onSuccess: () => {
          // Xoá sạch ba ô ngay khi thành công. Để mật khẩu nằm lại trong form
          // sau khi đã lưu là để nó nằm trên một màn hình có thể không khoá.
          setHienTai("");
          setMoi("");
          setNhapLai("");
          setXong(true);
        },
      },
    );
  }

  return (
    <div data-tone="all" className="enter mx-auto max-w-lg space-y-6">
      <header>
        <Link
          href="/"
          className="text-ink-faint hover:text-ink focus-frame inline-block rounded text-[0.82rem]"
        >
          ← Hôm nay của tôi
        </Link>
        <h1 className="mt-2 text-[1.65rem] leading-tight font-semibold tracking-[-0.035em]">
          Đổi mật khẩu
        </h1>
        <p className="text-ink-soft mt-1.5 text-[0.9rem]">
          Nếu bạn vẫn đang dùng mật khẩu tạm do quản trị viên cấp thì hãy đổi
          ngay — người cấp nó vẫn biết chuỗi đó.
        </p>
      </header>

      {xong && (
        <p
          role="status"
          className="border-line bg-paper-sunken rounded-xl border px-4 py-3 text-[0.87rem] leading-relaxed"
        >
          <strong>Đã đổi mật khẩu.</strong> Bạn vẫn đang đăng nhập trên máy này,
          nhưng những thiết bị từng chọn “ghi nhớ đăng nhập” sẽ phải nhập lại.
          Lần đăng nhập tới vẫn cần mã xác thực hai lớp như thường.
        </p>
      )}

      <form
        onSubmit={luu}
        className="tone-card space-y-4 rounded-2xl p-5"
        noValidate
      >
        <Field
          label="Mật khẩu hiện tại"
          required
          error={loi?.fieldError("current_password")}
        >
          {(id, describedBy) => (
            <TextInput
              id={id}
              aria-describedby={describedBy}
              type="password"
              autoComplete="current-password"
              value={hienTai}
              onChange={(su) => setHienTai(su.target.value)}
              placeholder="••••••••••••"
              autoFocus
            />
          )}
        </Field>

        <Field
          label="Mật khẩu mới"
          required
          error={loi?.fieldError("password")}
          hint="Tối thiểu 12 ký tự, có cả chữ và số. Không dùng mật khẩu đã từng bị lộ trong các vụ rò rỉ."
        >
          {(id, describedBy) => (
            <TextInput
              id={id}
              aria-describedby={describedBy}
              type="password"
              autoComplete="new-password"
              value={moi}
              onChange={(su) => setMoi(su.target.value)}
              placeholder="••••••••••••"
            />
          )}
        </Field>

        <Field
          label="Nhập lại mật khẩu mới"
          required
          error={loi?.fieldError("password_confirmation")}
        >
          {(id, describedBy) => (
            <TextInput
              id={id}
              aria-describedby={describedBy}
              type="password"
              autoComplete="new-password"
              value={nhapLai}
              onChange={(su) => setNhapLai(su.target.value)}
              placeholder="••••••••••••"
            />
          )}
        </Field>

        {/* Lỗi không gắn được vào ô nào. Không hiện ở đâu thì bấm Lưu xong
            không có gì xảy ra và người dùng không biết vì sao. */}
        {loi && loi.errors === null && (
          <p
            role="alert"
            className="border-danger-line bg-danger-surface text-danger rounded-xl border px-4 py-3 text-[0.85rem] leading-relaxed"
          >
            {loi.message}
          </p>
        )}

        <div className="border-line border-t pt-4">
          <Button type="submit" variant="primary" disabled={doi.isPending}>
            {doi.isPending ? "Đang lưu…" : "Đổi mật khẩu"}
          </Button>
        </div>
      </form>

      <p className="text-ink-faint text-[0.82rem] leading-relaxed">
        Quên mất mật khẩu hiện tại? Đăng xuất rồi bấm{" "}
        <strong className="text-ink-soft">Quên mật khẩu</strong> ở màn đăng nhập
        — hệ thống gửi đường dẫn đặt lại về email của bạn.
      </p>
    </div>
  );
}
