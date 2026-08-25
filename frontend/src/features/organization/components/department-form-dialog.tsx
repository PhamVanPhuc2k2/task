"use client";

import { useState } from "react";

import { Button } from "@/components/ui/button";
import { Dialog } from "@/components/ui/dialog";
import { Field, SelectInput, TextArea, TextInput } from "@/components/ui/field";
import type { Department } from "@/features/users/types/employee";
import type { ApiError } from "@/lib/api-client";

import {
  useCreateDepartment,
  useUpdateDepartment,
} from "../api/departments-api";
import { khongChonDuoc } from "../types/tree";

/**
 * Thêm phòng ban, hoặc sửa phòng ban đã có.
 *
 * Một hộp thoại cho cả hai, cùng lý do với `EmployeeFormDialog`: các trường
 * giống hệt nhau, tách ra thì mỗi lần thêm một trường lại phải nhớ sửa hai chỗ.
 *
 * **Chỗ gọi phải truyền `key` khác nhau cho từng phòng ban.** Giá trị ban đầu
 * lấy từ `department` đúng một lần lúc component gắn vào cây; mở sửa phòng A
 * rồi đóng, mở sửa phòng B mà không đổi `key` thì form vẫn hiện dữ liệu của A
 * và lưu đè lên B.
 */
export function DepartmentFormDialog({
  open,
  onClose,
  department,
  tatCa,
}: {
  open: boolean;
  onClose: () => void;
  /** Có = sửa phòng ban này. Không có = thêm mới. */
  department?: Department;
  /** Toàn bộ phòng ban, để dựng ô chọn cấp trên. */
  tatCa: Department[];
}) {
  const dangSua = department !== undefined;

  const tao = useCreateDepartment();
  const sua = useUpdateDepartment();

  const [ten, setTen] = useState(department?.name ?? "");
  const [ma, setMa] = useState(department?.code ?? "");
  const [moTa, setMoTa] = useState(department?.description ?? "");
  const [cha, setCha] = useState(department?.parent_id ?? "");
  const [dangHoatDong, setDangHoatDong] = useState(
    department?.is_active ?? true,
  );

  const dangGui = tao.isPending || sua.isPending;
  const loi = (dangSua ? sua.error : tao.error) as ApiError | null;

  // Chính nó và mọi cấp dưới không được làm cấp trên — chọn vào là tạo vòng.
  // Đây chỉ để ô chọn không bày ra thứ chắc chắn bị từ chối; chặn thật ở
  // backend, và thông báo lỗi của backend vẫn hiện đúng dưới ô này.
  const cam = khongChonDuoc(tatCa, department?.id ?? null);

  function rong(giaTri: string): string | null {
    const sach = giaTri.trim();

    return sach === "" ? null : sach;
  }

  function luu(su: React.FormEvent): void {
    su.preventDefault();

    const duLieu = {
      name: ten.trim(),
      code: rong(ma),
      description: rong(moTa),
      parent_id: rong(cha),
      is_active: dangHoatDong,
    };

    if (dangSua) {
      sua.mutate({ id: department.id, input: duLieu }, { onSuccess: onClose });

      return;
    }

    tao.mutate(duLieu, { onSuccess: onClose });
  }

  return (
    <Dialog
      open={open}
      onClose={onClose}
      title={dangSua ? `Sửa ${department.name}` : "Thêm phòng ban"}
      description={
        dangSua
          ? undefined
          : "Phòng ban quyết định ai xem được giờ công, đơn nghỉ và báo cáo của ai."
      }
    >
      <form onSubmit={luu} className="space-y-4">
        <Field label="Tên phòng ban" required error={loi?.fieldError("name")}>
          {(id, describedBy) => (
            <TextInput
              id={id}
              aria-describedby={describedBy}
              value={ten}
              onChange={(su) => setTen(su.target.value)}
              placeholder="Phòng Kinh doanh"
              autoFocus
            />
          )}
        </Field>

        <Field
          label="Mã phòng ban"
          error={loi?.fieldError("code")}
          hint="Để trống cũng được. Mã là thứ tệp CSV nhập nhân sự dùng để nối người vào phòng ban."
        >
          {(id, describedBy) => (
            <TextInput
              id={id}
              aria-describedby={describedBy}
              value={ma}
              onChange={(su) => setMa(su.target.value)}
              placeholder="KD"
            />
          )}
        </Field>

        <Field
          label="Trực thuộc"
          error={loi?.fieldError("parent_id")}
          hint="Trưởng phòng của phòng ban cấp trên sẽ xem được dữ liệu của phòng này."
        >
          {(id, describedBy) => (
            <SelectInput
              id={id}
              aria-describedby={describedBy}
              value={cha}
              onChange={(su) => setCha(su.target.value)}
            >
              <option value="">— Không trực thuộc phòng ban nào —</option>
              {tatCa
                .filter((p) => !cam.has(p.id))
                .map((p) => (
                  <option key={p.id} value={p.id}>
                    {p.name}
                    {p.is_active ? "" : " (đã ngừng)"}
                  </option>
                ))}
            </SelectInput>
          )}
        </Field>

        <Field label="Mô tả" error={loi?.fieldError("description")}>
          {(id, describedBy) => (
            <TextArea
              id={id}
              aria-describedby={describedBy}
              rows={2}
              value={moTa}
              onChange={(su) => setMoTa(su.target.value)}
              placeholder="Trực hotline và xử lý khiếu nại"
            />
          )}
        </Field>

        <label className="border-line bg-paper-sunken flex cursor-pointer items-start gap-3 rounded-xl border px-3.5 py-3">
          <input
            type="checkbox"
            checked={dangHoatDong}
            onChange={(su) => setDangHoatDong(su.target.checked)}
            className="accent-accent mt-0.5 size-4 cursor-pointer"
          />
          <span className="text-[0.85rem] leading-relaxed">
            <span className="font-medium">Đang hoạt động</span>
            <span className="text-ink-faint block">
              Tắt để ngừng nhận người mới. Phòng ban vẫn nằm trong cơ cấu và
              người đang ở trong đó vẫn hiện đủ trên bảng công, báo cáo của cấp
              trên.
            </span>
          </span>
        </label>

        {/* Lỗi không gắn được vào ô nào — vòng trong cây, mã trùng ở mức
            database. Không hiện ở đâu thì bấm Lưu xong không có gì xảy ra và
            người dùng không biết vì sao. */}
        {loi && loi.errors === null && (
          <p
            role="alert"
            className="border-danger-line bg-danger-surface text-danger rounded-xl border px-4 py-3 text-[0.85rem] leading-relaxed"
          >
            {loi.message}
          </p>
        )}

        <div className="border-line flex gap-3 border-t pt-4">
          <Button type="submit" variant="primary" disabled={dangGui}>
            {dangGui ? "Đang lưu…" : "Lưu"}
          </Button>
          <Button type="button" variant="ghost" onClick={onClose}>
            Huỷ
          </Button>
        </div>
      </form>
    </Dialog>
  );
}
