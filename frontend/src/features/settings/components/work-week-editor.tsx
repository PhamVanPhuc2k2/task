"use client";

import { cn } from "@/lib/cn";

/**
 * Sửa lịch làm việc trong tuần.
 *
 * ## Vì sao không để form tự dựng như các cài đặt khác
 *
 * Màn Cài đặt trang dựng ô nhập từ mô tả server trả về, và đó là thiết kế đúng
 * cho những giá trị vô hướng. Nhưng hai cài đặt này là **danh sách thứ trong
 * tuần lưu dạng chuỗi** — `"1,2,3,4,5"` — nên form tự dựng sẽ cho ra hai ô chữ
 * mà giám đốc phải tự biết rằng 1 là thứ hai và 0 là chủ nhật.
 *
 * Một ô chữ như vậy còn mời hai lỗi mà validate phải bắt sau: gõ trùng một ngày
 * vào cả hai ô, hoặc xoá sạch cả hai. Bảng bấm này làm cả hai trở nên bất khả.
 *
 * ## Ba trạng thái, không phải hai ô
 *
 * Backend giữ hai danh sách, nhưng người dùng nghĩ theo từng ngày: hôm đó nghỉ,
 * làm cả ngày, hay làm nửa buổi. Component quy đổi giữa hai cách nhìn, nên
 * không có trạng thái nào ở giữa để mà lệch — một ngày nằm ở đúng một danh
 * sách, hoặc không nằm ở đâu cả.
 */

type Kieu = "nghi" | "ca-ngay" | "nua-buoi";

/**
 * Thứ tự hiển thị theo cách người Việt đọc lịch: thứ hai trước, chủ nhật cuối.
 *
 * Số là cách đánh số của Carbon (0 = chủ nhật) — cùng hệ với backend, nên không
 * có phép quy đổi nào ở giữa để mà sai.
 */
const NGAY: Array<{ so: number; ten: string }> = [
  { so: 1, ten: "Thứ hai" },
  { so: 2, ten: "Thứ ba" },
  { so: 3, ten: "Thứ tư" },
  { so: 4, ten: "Thứ năm" },
  { so: 5, ten: "Thứ sáu" },
  { so: 6, ten: "Thứ bảy" },
  { so: 0, ten: "Chủ nhật" },
];

const NHAN: Array<{ kieu: Kieu; nhan: string }> = [
  { kieu: "nghi", nhan: "Nghỉ" },
  { kieu: "ca-ngay", nhan: "Cả ngày" },
  { kieu: "nua-buoi", nhan: "Nửa buổi" },
];

/** `"1,2,3"` thành `[1, 2, 3]`. Bỏ qua rác, giống hệt WorkWeek ở backend. */
function doc(tho: string): number[] {
  return tho
    .split(",")
    .map((x) => x.trim())
    .filter((x) => /^[0-6]$/.test(x))
    .map(Number);
}

/** Giữ thứ tự tăng dần để chuỗi lưu xuống không đổi chỉ vì người ta bấm khác thứ tự. */
function ghi(ds: number[]): string {
  return [...new Set(ds)].sort((a, b) => a - b).join(",");
}

export function WorkWeekEditor({
  caNgay,
  nuaBuoi,
  gioTanNuaBuoi,
  onChange,
  loi,
}: {
  caNgay: string;
  nuaBuoi: string;
  /** Chỉ để hiện lên nhãn, giúp người dùng thấy "nửa buổi" nghĩa là tan lúc mấy giờ. */
  gioTanNuaBuoi: string;
  onChange: (caNgay: string, nuaBuoi: string) => void;
  loi: string | undefined;
}) {
  const dsCaNgay = doc(caNgay);
  const dsNuaBuoi = doc(nuaBuoi);

  const kieuCua = (so: number): Kieu => {
    if (dsCaNgay.includes(so)) return "ca-ngay";
    if (dsNuaBuoi.includes(so)) return "nua-buoi";

    return "nghi";
  };

  function dat(so: number, kieu: Kieu) {
    // Gỡ khỏi CẢ HAI danh sách trước rồi mới thêm lại. Chỉ thêm mà không gỡ thì
    // một ngày nằm ở hai danh sách cùng lúc — cấu hình mà backend từ chối, và
    // người dùng không hiểu vì sao vì trên màn hình họ chỉ bấm một nút.
    const conCaNgay = dsCaNgay.filter((x) => x !== so);
    const conNuaBuoi = dsNuaBuoi.filter((x) => x !== so);

    onChange(
      ghi(kieu === "ca-ngay" ? [...conCaNgay, so] : conCaNgay),
      ghi(kieu === "nua-buoi" ? [...conNuaBuoi, so] : conNuaBuoi),
    );
  }

  const soNgay = dsCaNgay.length + dsNuaBuoi.length;

  return (
    <section className="tone-card rounded-2xl p-5">
      <h2 className="text-[0.95rem] font-semibold tracking-tight">
        Lịch làm việc trong tuần
      </h2>
      <p className="text-ink-faint mt-1 mb-4 max-w-2xl text-[0.84rem] leading-relaxed">
        Quyết định ngày nào tính đi muộn và ngày nào nhắc nộp báo cáo. Ngày nghỉ{" "}
        <strong className="font-medium">không cắt giờ của ai</strong> — ai làm
        thêm chủ nhật vẫn được tính đủ số phút, chỉ là không có mốc nào để tính
        muộn.
      </p>

      <div className="border-line divide-line divide-y overflow-hidden rounded-xl border">
        {NGAY.map(({ so, ten }) => {
          const kieu = kieuCua(so);

          return (
            <div
              key={so}
              className="flex flex-wrap items-center justify-between gap-3 px-3.5 py-2.5"
            >
              <span
                className={cn(
                  "text-[0.88rem]",
                  kieu === "nghi" && "text-ink-faint",
                )}
              >
                {ten}
                {kieu === "nua-buoi" && (
                  <span className="text-ink-faint ml-2 text-[0.78rem]">
                    tan {gioTanNuaBuoi}
                  </span>
                )}
              </span>

              <div
                role="radiogroup"
                aria-label={ten}
                className="border-line bg-paper-sunken flex overflow-hidden rounded-lg border"
              >
                {NHAN.map((n) => (
                  <button
                    key={n.kieu}
                    type="button"
                    role="radio"
                    aria-checked={kieu === n.kieu}
                    onClick={() => dat(so, n.kieu)}
                    className={cn(
                      "px-2.5 py-1 text-[0.78rem] transition-colors",
                      kieu === n.kieu
                        ? "bg-tone text-tone-ink font-medium"
                        : "text-ink-soft hover:text-ink",
                    )}
                  >
                    {n.nhan}
                  </button>
                ))}
              </div>
            </div>
          );
        })}
      </div>

      {/* Nói ra tổng số ngày làm việc: bấm bảy nút rồi tự đếm lại là việc không
          ai làm, và một tuần lỡ tay còn ba ngày làm việc trông vẫn hợp lý. */}
      <p className="text-ink-faint mt-3 text-[0.82rem]">
        {soNgay === 0
          ? "Chưa có ngày làm việc nào — phải có ít nhất một ngày."
          : `${soNgay} ngày làm việc mỗi tuần.`}
      </p>

      {loi !== undefined && (
        <p role="alert" className="text-danger mt-2 text-[0.84rem]">
          {loi}
        </p>
      )}
    </section>
  );
}
