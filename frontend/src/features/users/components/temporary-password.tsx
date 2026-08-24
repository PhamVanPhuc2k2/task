"use client";

import { useState } from "react";

import { Button } from "@/components/ui/button";

/**
 * Hiện mật khẩu tạm — thứ chỉ tồn tại đúng một lần.
 *
 * Database chỉ lưu bản băm, nên đóng hộp thoại này là mất luôn: muốn có lại
 * phải đặt lại mật khẩu lần nữa. Vì vậy giao diện nói thẳng điều đó thay vì để
 * người dùng tự phát hiện sau khi đã đóng.
 */
export function TemporaryPassword({
  password,
  hoTen,
}: {
  password: string;
  hoTen: string;
}) {
  const [daChep, setDaChep] = useState(false);

  async function chep() {
    try {
      await navigator.clipboard.writeText(password);
      setDaChep(true);
      setTimeout(() => setDaChep(false), 2000);
    } catch {
      // Trình duyệt chặn clipboard (thường do không chạy HTTPS). Mật khẩu vẫn
      // hiện rõ trên màn hình nên người dùng chép tay được — không cần báo lỗi
      // to tiếng về một thứ họ vẫn làm được.
      setDaChep(false);
    }
  }

  return (
    <div className="space-y-4">
      <div className="border-notice-line bg-notice-surface rounded-xl border px-4 py-3">
        <p className="text-notice text-[0.86rem] leading-relaxed">
          <strong>Chép ngay bây giờ.</strong> Mật khẩu này chỉ hiện một lần —
          đóng cửa sổ là không xem lại được, muốn có lại phải đặt lại mật khẩu.
        </p>
      </div>

      <div>
        <p className="text-ink-faint mb-1.5 text-[0.8rem]">
          Mật khẩu tạm của {hoTen}
        </p>
        <div className="border-line bg-paper-raised flex items-center gap-3 rounded-xl border px-4 py-3">
          <code className="min-w-0 flex-1 font-mono text-[1rem] break-all select-all">
            {password}
          </code>
          <Button size="sm" onClick={() => void chep()} className="shrink-0">
            {daChep ? "Đã chép" : "Chép"}
          </Button>
        </div>
      </div>

      <p className="text-ink-faint text-[0.82rem] leading-relaxed">
        Đưa mật khẩu này cho nhân viên qua kênh riêng, không gửi trong nhóm chat
        chung. Lần đăng nhập đầu, hệ thống sẽ bắt họ thiết lập xác thực hai lớp;
        nhắc họ đổi mật khẩu ngay sau đó.
      </p>
    </div>
  );
}
