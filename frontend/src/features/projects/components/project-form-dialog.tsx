"use client";

import { useState } from "react";

import { Button } from "@/components/ui/button";
import { Dialog } from "@/components/ui/dialog";
import { Field, SelectInput, TextArea, TextInput } from "@/components/ui/field";
import type { ApiError } from "@/lib/api-client";

import { useCreateProject, useUpdateProject } from "../api/projects-api";
import {
  PROJECT_STATUSES,
  type Project,
  type ProjectStatusValue,
} from "../types/project";

/**
 * Tạo hoặc sửa dự án.
 *
 * Một hộp thoại cho cả hai việc vì các trường giống hệt nhau; khác duy nhất ở
 * chỗ gọi POST hay PATCH. Tách hai component sẽ là hai chỗ phải sửa mỗi lần
 * thêm một trường.
 */
export function ProjectFormDialog({
  open,
  onClose,
  project,
}: {
  open: boolean;
  onClose: () => void;
  /** Có thì là sửa, không có thì là tạo mới. */
  project?: Project;
}) {
  const tao = useCreateProject();
  const sua = useUpdateProject(project?.id ?? "");
  const mutation = project ? sua : tao;

  const [ten, setTen] = useState(project?.name ?? "");
  const [ma, setMa] = useState(project?.code ?? "");
  const [moTa, setMoTa] = useState(project?.description ?? "");
  const [trangThai, setTrangThai] = useState<ProjectStatusValue>(
    project?.status.value ?? "planning",
  );
  const [batDau, setBatDau] = useState(project?.start_date ?? "");
  const [ketThuc, setKetThuc] = useState(project?.end_date ?? "");

  const loi = mutation.error as ApiError | null;

  return (
    <Dialog
      open={open}
      onClose={onClose}
      title={project ? "Sửa dự án" : "Tạo dự án"}
      description={
        project
          ? undefined
          : "Người tạo mặc định là chủ dự án — dự án không có chủ là dự án không ai chịu trách nhiệm."
      }
    >
      <div className="space-y-4">
        <Field label="Tên dự án" required error={loi?.fieldError("name")}>
          {(id) => (
            <TextInput
              id={id}
              value={ten}
              onChange={(e) => setTen(e.target.value)}
              placeholder="Website bán hàng 2026"
            />
          )}
        </Field>

        <div className="grid gap-4 sm:grid-cols-2">
          <Field
            label="Mã dự án"
            error={loi?.fieldError("code")}
            hint="Dùng trong báo cáo và tên nhánh git."
          >
            {(id, describedBy) => (
              <TextInput
                id={id}
                aria-describedby={describedBy}
                value={ma}
                onChange={(e) => setMa(e.target.value)}
                placeholder="WEB2026"
              />
            )}
          </Field>

          <Field label="Trạng thái" error={loi?.fieldError("status")}>
            {(id) => (
              <SelectInput
                id={id}
                value={trangThai}
                onChange={(e) =>
                  setTrangThai(e.target.value as ProjectStatusValue)
                }
              >
                {PROJECT_STATUSES.map((s) => (
                  <option key={s.value} value={s.value}>
                    {s.label}
                  </option>
                ))}
              </SelectInput>
            )}
          </Field>

          <Field label="Ngày bắt đầu" error={loi?.fieldError("start_date")}>
            {(id) => (
              <TextInput
                id={id}
                type="date"
                value={batDau ?? ""}
                onChange={(e) => setBatDau(e.target.value)}
              />
            )}
          </Field>

          <Field label="Ngày kết thúc" error={loi?.fieldError("end_date")}>
            {(id) => (
              <TextInput
                id={id}
                type="date"
                value={ketThuc ?? ""}
                onChange={(e) => setKetThuc(e.target.value)}
              />
            )}
          </Field>
        </div>

        <Field label="Mô tả" error={loi?.fieldError("description")}>
          {(id) => (
            <TextArea
              id={id}
              rows={3}
              value={moTa ?? ""}
              onChange={(e) => setMoTa(e.target.value)}
            />
          )}
        </Field>

        {loi && !loi.errors && (
          <p role="alert" className="text-danger text-[0.85rem]">
            {loi.message}
          </p>
        )}
      </div>

      <div className="mt-5 flex gap-3">
        <Button
          variant="primary"
          loading={mutation.isPending}
          onClick={() =>
            mutation.mutate(
              {
                name: ten,
                code: ma || null,
                description: moTa || null,
                status: trangThai,
                start_date: batDau || null,
                end_date: ketThuc || null,
              },
              { onSuccess: onClose },
            )
          }
        >
          {project ? "Lưu" : "Tạo dự án"}
        </Button>
        <Button variant="ghost" onClick={onClose}>
          Huỷ
        </Button>
      </div>
    </Dialog>
  );
}
