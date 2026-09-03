"use client";

import { Fragment, useState } from "react";

import { Button } from "@/components/ui/button";
import { Field, TextInput } from "@/components/ui/field";
import { EmptyState, ErrorState, Skeleton } from "@/components/ui/states";
import { useCurrentUser } from "@/features/auth/api/auth-api";
import {
  useSiteSettings,
  useUpdateSiteSettings,
} from "@/features/settings/api/site-api";
import { BrandingImageUploader } from "@/features/settings/components/branding-image-uploader";
import { WorkWeekEditor } from "@/features/settings/components/work-week-editor";
import {
  GROUP_HINTS,
  GROUP_LABELS,
  type SettingField,
  type SettingGroup,
  type SettingValue,
} from "@/features/settings/types/site";
import { cn } from "@/lib/cn";

/**
 * Khoá trỏ tới một TỆP — có ô tải riêng ở trên, nên không dựng thành ô nhập
 * chữ trong form. Backend cũng từ chối nhận chúng qua `PUT /settings`.
 */
const KHOA_ANH = ["logo_path", "icon_path"];

/**
 * Khoá có giao diện RIÊNG, không dựng thành ô nhập tự động.
 *
 * Hai danh sách thứ trong tuần lưu dạng chuỗi `"1,2,3,4,5"`. Để form tự dựng
 * thì giám đốc nhận hai ô chữ và phải tự biết 1 là thứ hai, 0 là chủ nhật —
 * xem WorkWeekEditor để biết vì sao đó là ô nhập mời lỗi.
 */
const KHOA_LICH_TUAN = ["work_days_full", "work_days_half"];

/**
 * Cài đặt trang.
 *
 * ## Vì sao màn này tồn tại
 *
 * Mười hai giá trị chính sách — ca làm, ân hạn đi muộn, giờ nhắc báo cáo, cửa
 * sổ nộp đơn — trước đây chỉ nằm trong `.env` trên máy chủ. Đổi bất kỳ cái nào
 * cũng cần lập trình viên. Đây là thứ giám đốc sở hữu, không phải thứ kỹ thuật.
 *
 * ## Form dựng từ mô tả của server
 *
 * Danh sách trường, nhãn, kiểu và nhóm đều do API trả về. Thêm một cài đặt mới ở
 * backend thì form tự có — không phải sửa hai chỗ rồi quên một chỗ.
 *
 * ## Chỉ gửi những trường đã đổi
 *
 * Gửi hết mọi giá trị thì mỗi lần bấm Lưu là ghi cứng cả mười hai dòng vào
 * database, kể cả những dòng người dùng chưa từng chạm. Sau đó đổi mặc định
 * trong config sẽ **không còn tác dụng**, và không ai hiểu vì sao.
 */
export default function SiteSettingsPage() {
  const { data: user } = useCurrentUser();

  const duocPhep = user?.permissions.includes("setting.manage") === true;

  const cai = useSiteSettings(duocPhep);
  const luu = useUpdateSiteSettings();

  /** Chỉ chứa trường người dùng đã sửa. Rỗng = chưa có gì để lưu. */
  const [nhap, setNhap] = useState<Record<string, SettingValue>>({});

  const soThayDoi = Object.keys(nhap).length;

  function gui() {
    luu.mutate(nhap, { onSuccess: () => setNhap({}) });
  }

  if (user && !duocPhep) {
    return (
      <div data-tone="all" className="enter">
        <EmptyState
          title="Bạn không có quyền vào màn này"
          description="Cài đặt trang chỉ dành cho quản trị viên và giám đốc — nó đổi cách tính công của cả công ty."
        />
      </div>
    );
  }

  return (
    <div data-tone="all" className="enter space-y-8">
      <header>
        <span
          aria-hidden="true"
          className="bg-tone mb-3 block h-[3px] w-9 rounded-full"
        />
        <h1 className="text-[1.65rem] leading-tight font-semibold tracking-[-0.035em]">
          Cài đặt trang
        </h1>
        <p className="text-ink-soft mt-1.5 max-w-2xl text-[0.9rem]">
          Tên công ty, logo, biểu tượng, và các mốc chính sách. Đổi ở đây có tác
          dụng ngay — không cần ai vào máy chủ.
        </p>
      </header>

      {cai.isPending && <Skeleton className="h-64" />}

      {cai.isError && (
        <ErrorState error={cai.error} onRetry={() => void cai.refetch()} />
      )}

      {cai.data && (
        <>
          <BrandingImageUploader loai="logo" />
          <BrandingImageUploader loai="icon" />

          {(
            ["branding", "attendance", "report", "leave"] as SettingGroup[]
          ).map((nhom) => {
            const truong = cai.data.fields.filter(
              (f) =>
                f.group === nhom &&
                !KHOA_ANH.includes(f.key) &&
                !KHOA_LICH_TUAN.includes(f.key),
            );

            const chuoi = (khoa: string, mac: string): string => {
              const v = nhap[khoa] ?? cai.data.values[khoa];

              return v === null || v === undefined ? mac : String(v);
            };

            return (
              <Fragment key={nhom}>
                {/* Lịch tuần đứng TRƯỚC các mốc giờ: đọc từ trên xuống là
                    "ngày nào làm" rồi mới tới "làm từ mấy giờ". Ngược lại thì
                    người ta chỉnh giờ ca xong mới phát hiện thứ bảy đang nghỉ. */}
                {nhom === "attendance" && (
                  <WorkWeekEditor
                    caNgay={chuoi("work_days_full", "")}
                    nuaBuoi={chuoi("work_days_half", "")}
                    gioTanNuaBuoi={chuoi("shift_half_end", "12:00")}
                    loi={
                      luu.error?.fieldError("values.work_days_full") ??
                      luu.error?.fieldError("values.work_days_half") ??
                      undefined
                    }
                    onChange={(caNgayMoi, nuaBuoiMoi) =>
                      setNhap((truoc) => ({
                        ...truoc,
                        work_days_full: caNgayMoi,
                        work_days_half: nuaBuoiMoi,
                      }))
                    }
                  />
                )}

                {truong.length > 0 && (
                  <section className="tone-card rounded-2xl p-5">
                    <h2 className="text-[0.95rem] font-semibold tracking-tight">
                      {GROUP_LABELS[nhom]}
                    </h2>
                    <p className="text-ink-faint mt-1 mb-4 max-w-2xl text-[0.84rem] leading-relaxed">
                      {GROUP_HINTS[nhom]}
                    </p>

                    <div className="grid gap-4 sm:grid-cols-2">
                      {truong.map((f) => (
                        <OCaiDat
                          key={f.key}
                          field={f}
                          giaTri={nhap[f.key] ?? cai.data.values[f.key] ?? null}
                          daSua={f.key in nhap}
                          loi={
                            luu.error?.fieldError(`values.${f.key}`) ??
                            undefined
                          }
                          onChange={(v) =>
                            setNhap((truoc) => ({ ...truoc, [f.key]: v }))
                          }
                        />
                      ))}
                    </div>
                  </section>
                )}
              </Fragment>
            );
          })}

          {/* Thanh lưu dính đáy: form dài hơn một màn hình, và nút Lưu ở tận
              cuối thì người ta sửa xong rồi bỏ đi mà không biết mình chưa lưu. */}
          <div className="bg-paper/85 border-line sticky bottom-0 -mx-4 flex flex-wrap items-center gap-3 border-t px-4 py-3 backdrop-blur-xl sm:-mx-6 sm:px-6">
            <Button
              variant="primary"
              loading={luu.isPending}
              disabled={soThayDoi === 0}
              onClick={gui}
            >
              {soThayDoi === 0
                ? "Chưa có thay đổi"
                : `Lưu ${soThayDoi} thay đổi`}
            </Button>

            {soThayDoi > 0 && (
              <Button variant="ghost" onClick={() => setNhap({})}>
                Bỏ thay đổi
              </Button>
            )}

            {luu.isSuccess && soThayDoi === 0 && (
              <span className="text-ink-soft text-[0.84rem]">
                Đã lưu. Các mốc mới có tác dụng ngay.
              </span>
            )}

            {luu.error && !luu.error.errors && (
              <span role="alert" className="text-danger text-[0.84rem]">
                {luu.error.message}
              </span>
            )}
          </div>
        </>
      )}
    </div>
  );
}

/**
 * Một ô cài đặt, kiểu do server khai.
 *
 * Hiện nhãn "đã sửa" cho trường đang có thay đổi chưa lưu: form mười hai ô mà
 * không đánh dấu thì người dùng cuộn lên cuộn xuống không biết mình đã đụng vào
 * những đâu.
 */
function OCaiDat({
  field,
  giaTri,
  daSua,
  loi,
  onChange,
}: {
  field: SettingField;
  giaTri: SettingValue;
  daSua: boolean;
  loi: string | undefined;
  onChange: (v: SettingValue) => void;
}) {
  if (field.type === "boolean") {
    return (
      <div className="sm:col-span-2">
        <label className="flex cursor-pointer items-start gap-2.5">
          <input
            type="checkbox"
            checked={giaTri === true}
            onChange={(e) => onChange(e.target.checked)}
            className="accent-accent mt-0.5 size-4"
          />
          <span className="text-[0.88rem]">
            {field.label}
            {daSua && <DauDaSua />}
          </span>
        </label>
        {loi !== undefined && (
          <p role="alert" className="text-danger mt-1 text-[0.82rem]">
            {loi}
          </p>
        )}
      </div>
    );
  }

  // Giờ trên đồng hồ dùng ô `time`; số dùng ô `number`. Để `text` cho cả hai
  // thì trên điện thoại bàn phím hiện sai loại, và người ta gõ được chữ vào ô
  // đáng ra chỉ nhận số.
  const laGio = /_(start|end|at)$/.test(field.key) && field.type === "text";

  return (
    <Field
      label={
        <>
          {field.label}
          {daSua && <DauDaSua />}
        </>
      }
      error={loi}
    >
      {(id) => (
        <TextInput
          id={id}
          type={laGio ? "time" : field.type === "integer" ? "number" : "text"}
          value={giaTri === null ? "" : String(giaTri)}
          onChange={(e) =>
            onChange(
              field.type === "integer"
                ? // Ô rỗng thành null chứ không thành 0: 0 là một giá trị có
                  // nghĩa (ân hạn 0 phút), còn "chưa điền" là chuyện khác.
                  e.target.value === ""
                  ? null
                  : Number(e.target.value)
                : e.target.value,
            )
          }
        />
      )}
    </Field>
  );
}

function DauDaSua() {
  return (
    <span
      className={cn(
        "border-notice-line bg-notice-surface text-notice ml-2",
        "rounded-full border px-1.5 py-0.5 text-[0.66rem] font-medium",
      )}
    >
      đã sửa
    </span>
  );
}
