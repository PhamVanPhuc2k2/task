"use client";

import { useState } from "react";

import {
  Notice,
  SecondaryButton,
  StepHeading,
  SubmitButton,
} from "./form-primitives";

/**
 * Hiện mã khôi phục — chỉ đúng một lần này.
 *
 * Buộc tích xác nhận đã lưu trước khi vào app. Không có bước chặn này thì người
 * dùng bấm qua luôn, và ngày mất điện thoại là ngày mất tài khoản.
 */
export function RecoveryCodesStep({
  codes,
  onDone,
}: {
  codes: string[];
  onDone: () => void;
}) {
  const [daLuu, setDaLuu] = useState(false);
  const [daChep, setDaChep] = useState(false);

  const chepTatCa = async () => {
    await navigator.clipboard.writeText(codes.join("\n"));
    setDaChep(true);
    window.setTimeout(() => setDaChep(false), 2000);
  };

  const taiVe = () => {
    const noiDung = [
      "Mã khôi phục Explus",
      "Mỗi mã chỉ dùng được một lần.",
      "",
      ...codes,
    ].join("\n");

    const url = URL.createObjectURL(
      new Blob([noiDung], { type: "text/plain;charset=utf-8" }),
    );

    const link = document.createElement("a");
    link.href = url;
    link.download = "explus-ma-khoi-phuc.txt";
    link.click();

    URL.revokeObjectURL(url);
  };

  return (
    <div className="rise-in">
      <StepHeading
        step={3}
        total={3}
        title="Lưu mã khôi phục"
        description="Dùng khi bạn mất điện thoại. Mỗi mã chỉ dùng được một lần."
      />

      <div className="space-y-5">
        <Notice>
          Các mã này{" "}
          <strong className="font-semibold">sẽ không hiển thị lại</strong>. Hãy
          lưu ngay bây giờ.
        </Notice>

        <ul className="border-line grid grid-cols-2 gap-x-4 gap-y-3 rounded-xl border bg-black/25 p-4 font-mono text-[0.85rem]">
          {codes.map((code, index) => (
            <li key={code} className="flex items-baseline gap-2.5">
              <span className="text-ink-faint text-[0.66rem]">
                {String(index + 1).padStart(2, "0")}
              </span>
              <span className="text-ink tracking-wider">{code}</span>
            </li>
          ))}
        </ul>

        <div className="grid grid-cols-2 gap-2.5">
          <SecondaryButton onClick={() => void chepTatCa()}>
            {daChep ? "Đã chép ✓" : "Chép tất cả"}
          </SecondaryButton>
          <SecondaryButton onClick={taiVe}>Tải file .txt</SecondaryButton>
        </div>

        <label
          className={`flex cursor-pointer items-start gap-3 rounded-lg border p-3.5 text-[0.88rem] transition-all duration-200 ${
            daLuu
              ? "border-accent/40 bg-accent/[0.07]"
              : "border-line hover:border-line-strong bg-white/[0.02]"
          }`}
        >
          <input
            type="checkbox"
            checked={daLuu}
            onChange={(e) => setDaLuu(e.target.checked)}
            className="accent-accent mt-0.5 h-4 w-4"
          />
          <span>Tôi đã lưu các mã khôi phục ở nơi an toàn.</span>
        </label>

        <form
          onSubmit={(event) => {
            event.preventDefault();
            onDone();
          }}
        >
          <SubmitButton pending={false} disabled={!daLuu}>
            Vào hệ thống
          </SubmitButton>
        </form>
      </div>
    </div>
  );
}
