"use client";

import { useState } from "react";

import { Button } from "@/components/ui/button";
import { Field, TextArea, TextInput } from "@/components/ui/field";
import { cn } from "@/lib/cn";

import { useOvertimePreview, useSubmitOvertime } from "../api/overtime-api";
import {
  DAY_KIND_TONE,
  formatMinutes,
  type MyOvertime,
} from "../types/overtime";

/**
 * Ô đăng ký làm thêm giờ.
 *
 * ## Hệ số hỏi server theo từng ngày, không tự tính
 *
 * Hệ số phụ thuộc lịch tuần và bảng ngày lễ. Chép cả hai xuống trình duyệt là
 * hai nơi cùng định nghĩa "ngày lễ", và chúng sẽ lệch nhau ở lần nhân sự nhập
 * thêm một ngày — lúc đó màn hình hứa 150% cho một ngày mà hệ thống trả 300%.
 *
 * Nhưng người dùng vẫn phải biết TRƯỚC khi đăng ký: *"tối nay là chủ nhật,
 * 200%"* là thông tin quyết định họ có nhận làm hay không. Nên đổi ngày là hỏi
 * server một lượt, và kết quả nhớ theo ngày nên đổi qua đổi lại không tốn thêm.
 */
export function OvertimeComposer({ data }: { data: MyOvertime }) {
  const nop = useSubmitOvertime();

  const [ngay, setNgay] = useState("");
  const [tu, setTu] = useState("");
  const [den, setDen] = useState("");
  const [lyDo, setLyDo] = useState("");

  const truoc = useOvertimePreview(ngay);

  const soPhut = phutGiua(tu, den);
  const nguocGio = tu !== "" && den !== "" && soPhut <= 0;

  const duLieuDu =
    ngay !== "" &&
    tu !== "" &&
    den !== "" &&
    !nguocGio &&
    lyDo.trim().length >= 10;

  function gui() {
    nop.mutate(
      { work_date: ngay, start_time: tu, end_time: den, reason: lyDo.trim() },
      {
        onSuccess: () => {
          setTu("");
          setDen("");
          setLyDo("");
        },
      },
    );
  }

  return (
    <div className="space-y-4">
      <div className="grid gap-3 sm:grid-cols-3">
        <Field label="Ngày" required error={nop.error?.fieldError("work_date")}>
          {(id) => (
            <TextInput
              id={id}
              type="date"
              value={ngay}
              min={data.window.earliest}
              max={data.window.latest}
              onChange={(e) => setNgay(e.target.value)}
            />
          )}
        </Field>

        <Field label="Từ" required error={nop.error?.fieldError("start_time")}>
          {(id) => (
            <TextInput
              id={id}
              type="time"
              value={tu}
              onChange={(e) => setTu(e.target.value)}
            />
          )}
        </Field>

        <Field label="Đến" required error={nop.error?.fieldError("end_time")}>
          {(id) => (
            <TextInput
              id={id}
              type="time"
              value={den}
              onChange={(e) => setDen(e.target.value)}
            />
          )}
        </Field>
      </div>

      {/* Loại ngày và hệ số — thứ quyết định người ta có nhận làm hay không. */}
      {truoc.data && (
        <div className="flex flex-wrap items-center gap-x-3 gap-y-2 text-[0.84rem]">
          <span
            className={cn(
              "rounded-full border px-2 py-0.5 text-[0.74rem] font-medium",
              DAY_KIND_TONE[truoc.data.day_kind],
            )}
          >
            {truoc.data.day_kind_label}
          </span>

          <span className="text-ink-soft">
            Hệ số dự kiến{" "}
            <strong className="text-ink font-semibold tabular-nums">
              {truoc.data.rate_percent}%
            </strong>
            {/* Nói rõ "dự kiến": hệ số chỉ đóng băng lúc duyệt. */}
            <span className="text-ink-faint"> — chốt lại khi được duyệt</span>
          </span>

          {truoc.data.shift && (
            <span className="text-ink-faint">
              Ca hôm đó {truoc.data.shift.start}–{truoc.data.shift.end}, giờ làm
              thêm phải nằm ngoài khoảng này.
            </span>
          )}
        </div>
      )}

      {soPhut > 0 && (
        <p className="text-ink-soft text-[0.84rem]">
          Đăng ký{" "}
          <strong className="text-ink font-semibold">
            {formatMinutes(soPhut)}
          </strong>
          {truoc.data && (
            <span className="text-ink-faint">
              {" "}
              ở hệ số {truoc.data.rate_percent}%
            </span>
          )}
        </p>
      )}

      {nguocGio && (
        <p role="alert" className="text-danger text-[0.84rem]">
          Giờ kết thúc phải sau giờ bắt đầu. Ca làm thêm vắt qua nửa đêm chưa hỗ
          trợ.
        </p>
      )}

      <Field
        label="Lý do"
        required
        hint="Quản lý cần biết vì sao việc này không đợi được tới giờ làm hôm sau."
        error={nop.error?.fieldError("reason")}
      >
        {(id, describedBy) => (
          <TextArea
            id={id}
            rows={3}
            aria-describedby={describedBy}
            value={lyDo}
            placeholder="Chốt bản demo cho khách sáng mai, phần báo cáo còn thiếu số liệu tháng 8."
            onChange={(e) => setLyDo(e.target.value)}
          />
        )}
      </Field>

      {/*
        Lỗi không gắn ô nào: chồng lấn, vượt trần, giờ nằm trong ca, kỳ đã chốt.
        Cả bốn đều là câu người dùng phải đọc, không phải một ô viền đỏ — và câu
        của server đã nói ra con số cụ thể.
      */}
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
        Gửi đăng ký
      </Button>
    </div>
  );
}

/**
 * Số phút giữa hai mốc `HH:MM`.
 *
 * Cộng trừ trên chuỗi giờ chứ không dựng `Date`: dựng Date từ "20:00" phải ghép
 * thêm một ngày và một múi giờ, và đó là chỗ sinh ra lệch bảy tiếng. Cùng lý do
 * đã ghi ở ô xin đi muộn.
 */
function phutGiua(tu: string, den: string): number {
  if (tu === "" || den === "") return 0;

  return phutTrongNgay(den) - phutTrongNgay(tu);
}

function phutTrongNgay(gio: string): number {
  const [h, p] = gio.split(":").map(Number);

  return (h ?? 0) * 60 + (p ?? 0);
}
