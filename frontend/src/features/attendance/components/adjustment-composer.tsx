"use client";

import { useState } from "react";

import { Button } from "@/components/ui/button";
import { Field, TextArea, TextInput } from "@/components/ui/field";

import { useSubmitAdjustment } from "../api/adjustment-api";

/**
 * Ô nộp đơn giải trình cho một ngày công.
 *
 * Cận trên của ô ngày **lấy từ server** (`latest_date`), không tự tính từ
 * `new Date()`: đồng hồ máy người dùng có thể lệch, và múi giờ trình duyệt có
 * thể không phải giờ Việt Nam khi nhân viên đi công tác. Tự tính thì form mở
 * cho một ngày mà API sẽ từ chối.
 *
 * Cận dưới thì KHÔNG có ở đây: nó là ngày vào làm và ranh giới kỳ đã chốt, hai
 * thứ giao diện không biết. Server trả câu lỗi gọi tên kỳ, và câu đó rõ hơn bất
 * kỳ ô ngày bị chặn nào.
 */
export function AdjustmentComposer({
  latestDate,
  initialDate = "",
}: {
  latestDate: string;
  /** Điền sẵn khi người dùng bấm "Giải trình ngày này" từ bảng công. */
  initialDate?: string;
}) {
  const nop = useSubmitAdjustment();

  const [ngay, setNgay] = useState(initialDate);
  const [gio, setGio] = useState("");
  const [lyDo, setLyDo] = useState("");

  const duLieuDu = ngay !== "" && lyDo.trim().length >= 10;

  function gui() {
    nop.mutate(
      {
        work_date: ngay,
        reason: lyDo.trim(),
        // Để trống nghĩa là "xin bỏ qua, tôi không khẳng định con số nào" —
        // gửi 0 sẽ bị đọc thành một lời khai, và là lời khai sai.
        requested_minutes: gio === "" ? null : Math.round(Number(gio) * 60),
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
      <div className="grid gap-3 sm:grid-cols-2">
        <Field
          label="Ngày công"
          required
          error={nop.error?.fieldError("work_date")}
        >
          {(id) => (
            <TextInput
              id={id}
              type="date"
              value={ngay}
              max={latestDate}
              onChange={(e) => setNgay(e.target.value)}
            />
          )}
        </Field>

        <Field
          label="Số giờ đề nghị"
          hint="Để trống nếu bạn không đếm được — quản lý sẽ tự quyết định con số."
          error={nop.error?.fieldError("requested_minutes")}
        >
          {(id, describedBy) => (
            <TextInput
              id={id}
              type="number"
              min={0}
              max={24}
              step={0.5}
              value={gio}
              placeholder="8"
              aria-describedby={describedBy}
              onChange={(e) => setGio(e.target.value)}
            />
          )}
        </Field>
      </div>

      <Field
        label="Lý do"
        required
        hint="Viết rõ hơn một dòng “đi công tác” — quản lý cần đủ thông tin để quyết định, và sáu tháng sau sẽ có người đọc lại."
        error={nop.error?.fieldError("reason")}
      >
        {(id, describedBy) => (
          <TextArea
            id={id}
            rows={3}
            aria-describedby={describedBy}
            value={lyDo}
            placeholder="Cả ngày ở chỗ khách hàng hướng dẫn vận hành website, không mở máy công ty."
            onChange={(e) => setLyDo(e.target.value)}
          />
        )}
      </Field>

      {/*
        Lỗi không gắn với ô nào — kỳ đã chốt, hoặc đã có đơn cho ngày này. Cả
        hai đều là câu người dùng phải đọc, không phải một ô viền đỏ.
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
        Gửi đơn
      </Button>
    </div>
  );
}
