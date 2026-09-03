"use client";

import { useRef } from "react";

import { Button } from "@/components/ui/button";
import { ExplusMark } from "@/features/auth/components/explus-mark";

import {
  useRemoveBrandingImage,
  useSiteBranding,
  useUploadBrandingImage,
  type AnhNhanDien,
} from "../api/site-api";

/** Nhãn và ràng buộc tệp của từng loại ảnh. */
const CAU_HINH: Record<
  AnhNhanDien,
  { tieuDe: string; moTa: string; accept: string }
> = {
  logo: {
    tieuDe: "Logo",
    moTa: "Hiện ở đầu trang và trên trang đăng nhập. PNG, JPG hoặc WebP, tối đa 1MB. Không nhận SVG — tệp SVG chạy được mã.",
    accept: "image/png,image/jpeg,image/webp",
  },
  icon: {
    tieuDe: "Biểu tượng",
    moTa: "Hiện trên tab trình duyệt và màn hình chính điện thoại. PNG hoặc WebP, phải vuông, cạnh từ 64 đến 512px, tối đa 512KB. Đổi xong trình duyệt có thể mất vài phút mới hiện ảnh mới.",
    accept: "image/png,image/webp",
  },
};

/**
 * Đổi một ảnh nhận diện — logo hoặc biểu tượng.
 *
 * ## Vì sao là HAI ô tải, không phải một
 *
 * Logo công ty thường nằm ngang: một dấu hiệu cộng với tên viết bằng chữ. Ảnh
 * đó co xuống 16×16 pixel trên tab trình duyệt thì thành vệt mờ — chữ biến mất
 * trước tiên. Biểu tượng là bài toán ngược lại: vuông, một hình duy nhất, phải
 * đọc được ở kích thước rất nhỏ.
 *
 * Ép hai yêu cầu đó vào cùng một tệp là hỏng cả hai, nên chúng là hai ô riêng
 * với ràng buộc riêng.
 *
 * ## Không nhận SVG, có chủ ý
 *
 * SVG là XML và chạy được script. Tệp này được phục vụ từ chính tên miền của
 * ứng dụng, nên một SVG có mã nhúng là lỗ hổng XSS ngay trong trang đăng nhập.
 * Backend cũng chặn — đây chỉ là lớp thứ nhất, để người dùng biết ngay ở ô chọn
 * tệp thay vì tải lên xong mới bị từ chối.
 *
 * ## Xoá là quay về dấu cộng vẽ tay
 *
 * Không phải để trống. `ExplusMark` luôn là đường lùi, nên không chỗ nào có một
 * khoảng trắng ở vị trí đáng ra là ảnh.
 */
export function BrandingImageUploader({ loai }: { loai: AnhNhanDien }) {
  const nhanDien = useSiteBranding();
  const tai = useUploadBrandingImage(loai);
  const xoa = useRemoveBrandingImage(loai);

  const oTep = useRef<HTMLInputElement>(null);

  const cauHinh = CAU_HINH[loai];
  const anh =
    (loai === "logo" ? nhanDien.data?.logo_url : nhanDien.data?.icon_url) ??
    null;

  return (
    <section className="tone-card rounded-2xl p-5">
      <h2 className="text-[0.95rem] font-semibold tracking-tight">
        {cauHinh.tieuDe}
      </h2>
      <p className="text-ink-faint mt-1 mb-4 max-w-2xl text-[0.84rem] leading-relaxed">
        {cauHinh.moTa}
      </p>

      <div className="flex flex-wrap items-center gap-5">
        <OXemTruoc anh={anh} vuong={loai === "icon"} />

        {/* Biểu tượng xem trước thêm ở đúng 16px — kích thước thật trên tab.
            Không có ô này thì người ta chọn một ảnh nhiều chi tiết, thấy đẹp ở
            ô 80px, và chỉ phát hiện nó thành vệt mờ sau khi đã lưu. */}
        {loai === "icon" && anh !== null && (
          <div className="flex flex-col items-center gap-1.5">
            <div className="border-line bg-paper-sunken flex size-8 items-center justify-center rounded-lg border">
              {/* eslint-disable-next-line @next/next/no-img-element */}
              <img src={anh} alt="" className="size-4 object-contain" />
            </div>
            <span className="text-ink-faint text-[0.7rem]">cỡ thật</span>
          </div>
        )}

        <div className="flex flex-wrap items-center gap-2.5">
          <input
            ref={oTep}
            type="file"
            accept={cauHinh.accept}
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
            {anh === null ? "Chọn ảnh" : "Đổi ảnh"}
          </Button>

          {anh !== null && (
            <Button
              variant="ghost"
              loading={xoa.isPending}
              onClick={() => xoa.mutate()}
            >
              Xoá {cauHinh.tieuDe.toLowerCase()}
            </Button>
          )}
        </div>
      </div>

      {tai.error && (
        <p role="alert" className="text-danger mt-3 text-[0.84rem]">
          {tai.error.fieldError(loai) ?? tai.error.message}
        </p>
      )}
    </section>
  );
}

/**
 * Ô xem trước.
 *
 * Nền lõm và kích thước cố định: ảnh nền trắng đặt trên thẻ trắng thì không
 * thấy đâu là mép ảnh.
 */
function OXemTruoc({ anh, vuong }: { anh: string | null; vuong: boolean }) {
  return (
    <div className="border-line bg-paper-sunken flex size-20 shrink-0 items-center justify-center overflow-hidden rounded-2xl border">
      {anh === null ? (
        <ExplusMark className="text-accent-ink size-8" />
      ) : (
        // `object-contain` kể cả với biểu tượng đã ép vuông ở backend: ràng
        // buộc đó có thể nới ra sau, và lúc đó `cover` sẽ âm thầm cắt ảnh.
        //
        // Ảnh hiển thị ở 16–80px và tối đa 1MB. Đưa qua `next/image` để tối ưu
        // một ảnh nhỏ như vậy là không đáng, và nó buộc server Next phải với
        // được host API — thêm `images.remotePatterns`, thêm một điểm hỏng lúc
        // chạy. Directive phải nằm NGAY dòng trên thẻ img.
        // eslint-disable-next-line @next/next/no-img-element
        <img
          src={anh}
          alt={vuong ? "Biểu tượng của trang" : "Logo công ty"}
          className="size-full object-contain p-1.5"
        />
      )}
    </div>
  );
}
