"use client";

import { useState } from "react";

import { Button } from "@/components/ui/button";
import { Field, TextArea, TextInput } from "@/components/ui/field";

import { useSubmitLateArrival } from "../api/late-arrival-api";
import type { AttendanceExceptionValue } from "../types/late-arrival";

/**
 * Ô nộp đơn xin đi làm muộn.
 *
 * Ô chọn ngày và mốc giờ ca làm đều **lấy từ server**, không tự tính từ
 * `new Date()` hay hardcode "08:15": đồng hồ máy người dùng có thể lệch, nhân
 * viên đi công tác có thể đang ở múi giờ khác, và công ty có thể đổi ca. Tự
 * tính thì form mở cho một giá trị mà API sẽ từ chối.
 */
export function LateArrivalComposer({
  window: khoang,
  shift: ca,
}: {
  window: { earliest: string; latest: string };
  shift: { morning_start: string; end: string };
}) {
  const nop = useSubmitLateArrival();

  const [loai, setLoai] = useState<AttendanceExceptionValue>("late");
  const [ngay, setNgay] = useState("");
  const [gio, setGio] = useState("");
  const [lyDo, setLyDo] = useState("");

  const veSom = loai === "early";

  /*
  | Đi muộn đo TỪ giờ vào ca; về sớm đo TỚI giờ tan ca. Hai chiều ngược nhau
  | nên phép trừ đảo thứ tự, còn lại mọi thứ giống hệt.
  |
  | Giờ tan lấy từ `shift.end` mà server trả về — đó là ca ngày làm CẢ NGÀY.
  | Ngày nửa buổi (thứ bảy tan 12:00) thì mốc này rộng hơn thực tế, và server
  | mới là nơi chặn theo đúng ngày. Chấp nhận: form không biết trước người dùng
  | sẽ chọn ngày nào, và hỏi server mỗi lần đổi ngày là một vòng mạng cho một
  | dòng chữ gợi ý.
  */
  const soPhutLech = veSom
    ? phutGiua(gio, ca.end)
    : phutGiua(ca.morning_start, gio);

  // Xin "đi muộn" tới trước giờ vào làm — hoặc "về sớm" sau giờ tan — là không
  // có nghĩa. Chặn ngay ở đây thay vì để người ta điền xong mới báo lỗi.
  const gioKhongHopLe = gio !== "" && soPhutLech <= 0;

  const duLieuDu =
    ngay !== "" && gio !== "" && !gioKhongHopLe && lyDo.trim().length >= 10;

  function gui() {
    nop.mutate(
      {
        type: loai,
        date: ngay,
        ...(veSom ? { expected_departure: gio } : { expected_arrival: gio }),
        reason: lyDo.trim(),
      },
      {
        onSuccess: () => {
          setNgay("");
          setGio("");
          setLyDo("");
        },
      },
    );
  }

  return (
    <div className="space-y-4">
      {/* Đổi loại thì xoá giờ đã nhập: "9h30" hợp lệ cho đi muộn nhưng vô nghĩa
          cho về sớm, và để nguyên thì người dùng gửi nhầm mà không nhận ra. */}
      <div
        role="radiogroup"
        aria-label="Loại đơn"
        className="border-line bg-paper-sunken inline-flex gap-0.5 rounded-xl border p-0.5"
      >
        {(
          [
            { v: "late", nhan: "Đi muộn" },
            { v: "early", nhan: "Về sớm" },
          ] as { v: AttendanceExceptionValue; nhan: string }[]
        ).map((m) => (
          <button
            key={m.v}
            type="button"
            role="radio"
            aria-checked={loai === m.v}
            onClick={() => {
              setLoai(m.v);
              setGio("");
            }}
            className={
              loai === m.v
                ? "bg-tone text-tone-ink rounded-lg px-3 py-1 text-[0.82rem] font-medium"
                : "text-ink-soft hover:text-ink rounded-lg px-3 py-1 text-[0.82rem]"
            }
          >
            {m.nhan}
          </button>
        ))}
      </div>

      <div className="grid gap-3 sm:grid-cols-2">
        <Field label="Ngày" required error={nop.error?.fieldError("date")}>
          {(id) => (
            <TextInput
              id={id}
              type="date"
              value={ngay}
              min={khoang.earliest}
              max={khoang.latest}
              onChange={(e) => setNgay(e.target.value)}
            />
          )}
        </Field>

        <Field
          label={veSom ? "Dự kiến rời lúc" : "Dự kiến đến lúc"}
          required
          hint={
            veSom
              ? `Ca làm tan ${ca.end}. Về sớm dưới 5 phút thì không cần xin.`
              : `Ca làm bắt đầu ${ca.morning_start}.`
          }
          error={nop.error?.fieldError(
            veSom ? "expected_departure" : "expected_arrival",
          )}
        >
          {(id, describedBy) => (
            <TextInput
              id={id}
              type="time"
              value={gio}
              aria-describedby={describedBy}
              onChange={(e) => setGio(e.target.value)}
            />
          )}
        </Field>
      </div>

      {gio !== "" && (
        <p
          className={
            gioKhongHopLe
              ? "text-danger text-[0.84rem]"
              : "text-ink-soft text-[0.84rem]"
          }
        >
          {gioKhongHopLe ? (
            veSom ? (
              `${gio} không sớm hơn giờ tan ca ${ca.end} — không cần xin phép.`
            ) : (
              `${gio} không muộn hơn giờ vào làm ${ca.morning_start} — không cần xin phép.`
            )
          ) : veSom ? (
            <>
              Sớm{" "}
              <strong className="text-ink">{dienGiaiPhut(soPhutLech)}</strong>{" "}
              so với giờ tan {ca.end}.{" "}
              <span className="text-ink-faint">
                Đơn được duyệt chỉ bao từ đúng {gio} — về trước mốc đó vẫn tính
                là về sớm.
              </span>
            </>
          ) : (
            <>
              Muộn{" "}
              <strong className="text-ink">{dienGiaiPhut(soPhutLech)}</strong>{" "}
              so với ca {ca.morning_start}.{" "}
              <span className="text-ink-faint">
                Đơn được duyệt chỉ bao tới đúng {gio} — đến sau mốc đó vẫn tính
                là đi muộn.
              </span>
            </>
          )}
        </p>
      )}

      <Field
        label="Lý do"
        required
        hint="Quản lý cần đủ thông tin để quyết định — viết rõ hơn một dòng “có việc”."
        error={nop.error?.fieldError("reason")}
      >
        {(id, describedBy) => (
          <TextArea
            id={id}
            rows={3}
            aria-describedby={describedBy}
            value={lyDo}
            placeholder="Đưa con đi khám buổi sáng, đã báo anh Tuấn trực thay phần trả lời khách."
            onChange={(e) => setLyDo(e.target.value)}
          />
        )}
      </Field>

      {nop.error && !nop.error.errors && (
        <p role="alert" className="text-danger text-[0.84rem]">
          {nop.error.message}
        </p>
      )}

      <Button
        variant="primary"
        loading={nop.isPending}
        disabled={!duLieuDu}
        onClick={gui}
      >
        Gửi đơn
      </Button>
    </div>
  );
}

/**
 * Số phút từ `HH:MM` này tới `HH:MM` kia.
 *
 * Cộng trừ trên chuỗi giờ chứ không dựng `Date`: dựng Date từ "09:30" phải ghép
 * thêm một ngày và một múi giờ, và đó là chỗ sinh ra lệch bảy tiếng. Ở đây chỉ
 * cần hiệu hai mốc trong cùng một ngày.
 */
function phutGiua(tu: string, den: string): number {
  if (tu === "" || den === "") return 0;

  return phutTrongNgay(den) - phutTrongNgay(tu);
}

function phutTrongNgay(gio: string): number {
  const [h, p] = gio.split(":").map(Number);

  return (h ?? 0) * 60 + (p ?? 0);
}

function dienGiaiPhut(phut: number): string {
  if (phut < 60) return `${phut} phút`;

  const gio = Math.floor(phut / 60);
  const du = phut % 60;

  return du === 0 ? `${gio} tiếng` : `${gio} tiếng ${du} phút`;
}
