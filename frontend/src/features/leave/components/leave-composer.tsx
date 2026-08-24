"use client";

import { useState } from "react";

import { Button } from "@/components/ui/button";
import { Field, SelectInput, TextArea, TextInput } from "@/components/ui/field";

import { useSubmitLeave } from "../api/leave-api";
import type { LeaveTypeOption } from "../types/leave";

/**
 * Ô nộp đơn xin nghỉ.
 *
 * Ô chọn ngày bị chặn bằng `min`/`max` **lấy từ server**, không tự tính từ
 * `new Date()`: đồng hồ máy người dùng có thể lệch, và nhân viên đi công tác có
 * thể đang ở múi giờ khác. Tự tính thì form mở cho một ngày mà API sẽ từ chối.
 */
export function LeaveComposer({
  types,
  window: khoang,
}: {
  types: LeaveTypeOption[];
  window: { earliest: string; latest: string; max_days: number };
}) {
  const nop = useSubmitLeave();

  const [loai, setLoai] = useState(types[0]?.value ?? "annual");
  const [tu, setTu] = useState("");
  const [den, setDen] = useState("");
  const [lyDo, setLyDo] = useState("");

  // Ngày kết thúc không được trước ngày bắt đầu. Chặn ngay ở ô nhập thay vì
  // để người ta điền xong mới báo lỗi — và backend vẫn kiểm lại, vì đơn ngược
  // ngày làm mọi phép so sánh khoảng trả về rỗng mà không có gì báo.
  const denToiThieu = tu !== "" ? tu : khoang.earliest;

  const soNgay =
    tu !== "" && den !== "" && den >= tu
      ? Math.round(
          (new Date(den).getTime() - new Date(tu).getTime()) / 86_400_000,
        ) + 1
      : 0;

  const quaDai = soNgay > khoang.max_days;
  const duLieuDu =
    tu !== "" && den >= tu && lyDo.trim().length >= 10 && !quaDai;

  function gui() {
    nop.mutate(
      { type: loai, start_date: tu, end_date: den, reason: lyDo.trim() },
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
        <Field label="Loại nghỉ" required>
          {(id) => (
            <SelectInput
              id={id}
              value={loai}
              onChange={(e) => setLoai(e.target.value)}
            >
              {types.map((t) => (
                <option key={t.value} value={t.value}>
                  {t.label}
                </option>
              ))}
            </SelectInput>
          )}
        </Field>

        <Field
          label="Từ ngày"
          required
          error={nop.error?.fieldError("start_date")}
        >
          {(id) => (
            <TextInput
              id={id}
              type="date"
              value={tu}
              min={khoang.earliest}
              max={khoang.latest}
              onChange={(e) => setTu(e.target.value)}
            />
          )}
        </Field>

        <Field
          label="Đến ngày"
          required
          error={nop.error?.fieldError("end_date")}
        >
          {(id) => (
            <TextInput
              id={id}
              type="date"
              value={den}
              min={denToiThieu}
              max={khoang.latest}
              onChange={(e) => setDen(e.target.value)}
            />
          )}
        </Field>
      </div>

      {soNgay > 0 && (
        <p
          className={
            quaDai
              ? "text-danger text-[0.84rem]"
              : "text-ink-soft text-[0.84rem]"
          }
        >
          {quaDai
            ? `Đơn dài ${soNgay} ngày — tối đa ${khoang.max_days} ngày một đơn.`
            : `Nghỉ ${soNgay} ngày.`}{" "}
          {!quaDai && (
            <span className="text-ink-faint">
              Tính theo ngày trên lịch, chưa trừ cuối tuần và ngày lễ.
            </span>
          )}
        </p>
      )}

      <Field
        label="Lý do"
        required
        hint="Quản lý cần đủ thông tin để quyết định — viết rõ hơn một dòng “bận việc”."
        error={nop.error?.fieldError("reason")}
      >
        {(id, describedBy) => (
          <TextArea
            id={id}
            rows={3}
            aria-describedby={describedBy}
            value={lyDo}
            placeholder="Về quê giỗ ông, đã bàn giao việc đang làm cho anh Tuấn."
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
        Nộp đơn
      </Button>
    </div>
  );
}
