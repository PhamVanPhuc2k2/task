"use client";

import { useRef } from "react";

import { Button } from "@/components/ui/button";
import { ExplusMark } from "@/features/auth/components/explus-mark";

import { useRemoveLogo, useSiteBranding, useUploadLogo } from "../api/site-api";

/**
 * Đổi logo công ty.
 *
 * ## Không nhận SVG, có chủ ý
 *
 * SVG là XML và chạy được script. Tệp này được phục vụ từ chính tên miền của
 * ứng dụng, nên một SVG có mã nhúng là lỗ hổng XSS ngay trong trang đăng nhập.
 * Backend cũng chặn — đây chỉ là lớp thứ nhất, để người dùng biết ngay ở ô chọn
 * tệp thay vì tải lên xong mới bị từ chối.
 *
 * ## Xoá logo là quay về dấu cộng vẽ tay
 *
 * Không phải để trống. `ExplusMark` luôn là đường lùi, nên trang không bao giờ
 * có một khoảng trắng ở chỗ đáng ra là logo.
 */
export function LogoUploader() {
  const nhanDien = useSiteBranding();
  const tai = useUploadLogo();
  const xoa = useRemoveLogo();

  const oTep = useRef<HTMLInputElement>(null);

  const logo = nhanDien.data?.logo_url ?? null;

  return (
    <section className="tone-card rounded-2xl p-5">
      <h2 className="text-[0.95rem] font-semibold tracking-tight">Logo</h2>
      <p className="text-ink-faint mt-1 mb-4 max-w-2xl text-[0.84rem] leading-relaxed">
        Hiện ở đầu trang và trên trang đăng nhập. PNG, JPG hoặc WebP, tối đa
        1MB. Không nhận SVG — tệp SVG chạy được mã.
      </p>

      <div className="flex flex-wrap items-center gap-5">
        {/* Ô xem trước dùng nền lõm và kích thước cố định: logo nền trắng đặt
            trên thẻ trắng thì không thấy đâu là mép ảnh. */}
        <div className="border-line bg-paper-sunken flex size-20 shrink-0 items-center justify-center overflow-hidden rounded-2xl border">
          {logo === null ? (
            <ExplusMark className="text-accent-ink size-8" />
          ) : (
            // Ảnh do người dùng tải lên, kích thước không đoán trước được —
            // `object-contain` để logo ngang không bị cắt hai đầu.
            // Logo hiển thị ở 24–80px và tối đa 1MB. Đưa qua `next/image` để tối
            // ưu một ảnh nhỏ như vậy là không đáng, và nó buộc server Next phải
            // với được host API — thêm `images.remotePatterns`, thêm một điểm
            // hỏng lúc chạy. Directive phải nằm NGAY dòng trên thẻ img.
            // eslint-disable-next-line @next/next/no-img-element
            <img
              src={logo}
              alt="Logo công ty"
              className="size-full object-contain p-1.5"
            />
          )}
        </div>

        <div className="flex flex-wrap items-center gap-2.5">
          <input
            ref={oTep}
            type="file"
            accept="image/png,image/jpeg,image/webp"
            className="sr-only"
            onChange={(e) => {
              const tep = e.target.files?.[0];

              if (tep) tai.mutate(tep);

              // Xoá giá trị ô: không xoá thì chọn LẠI đúng tệp vừa chọn sẽ
              // không kích hoạt `change`, và người dùng tưởng nút hỏng.
              e.target.value = "";
            }}
          />

          <Button
            variant="primary"
            loading={tai.isPending}
            onClick={() => oTep.current?.click()}
          >
            {logo === null ? "Chọn ảnh" : "Đổi ảnh"}
          </Button>

          {logo !== null && (
            <Button
              variant="ghost"
              loading={xoa.isPending}
              onClick={() => xoa.mutate()}
            >
              Xoá logo
            </Button>
          )}
        </div>
      </div>

      {tai.error && (
        <p role="alert" className="text-danger mt-3 text-[0.84rem]">
          {tai.error.fieldError("logo") ?? tai.error.message}
        </p>
      )}
    </section>
  );
}
