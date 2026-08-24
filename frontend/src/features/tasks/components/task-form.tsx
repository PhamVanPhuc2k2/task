"use client";

import { zodResolver } from "@hookform/resolvers/zod";
import { useRouter } from "next/navigation";
import { useForm } from "react-hook-form";

import { Button } from "@/components/ui/button";
import { Field, SelectInput, TextArea, TextInput } from "@/components/ui/field";
import { useProjects } from "@/features/projects/api/projects-api";
import { useAssignableUsers } from "@/features/users/api/directory-api";
import { DirectoryHint } from "@/features/users/components/directory-hint";
import { ApiError } from "@/lib/api-client";

import { useCreateTask } from "../api/tasks-api";
import {
  taskSchema,
  toTaskPayload,
  type TaskFormValues,
} from "../schemas/task-schema";
import { TASK_PRIORITIES } from "../types/task";

/**
 * Form tạo công việc.
 *
 * Lỗi validate của backend được gắn ngược vào đúng ô nhập, không đổ hết vào
 * một dòng đỏ ở đầu form: người dùng cần biết ô NÀO sai, không phải biết rằng
 * "có gì đó sai".
 */
export function TaskForm({ projectId }: { projectId?: string }) {
  const router = useRouter();
  const taoTask = useCreateTask();
  const { data: duAn } = useProjects({ open: true, per_page: 100 });
  const { data: danhBa } = useAssignableUsers();
  const dsNguoi = danhBa?.people;

  const {
    register,
    handleSubmit,
    setError,
    formState: { errors, isSubmitting },
  } = useForm<TaskFormValues>({
    resolver: zodResolver(taskSchema),
    defaultValues: {
      title: "",
      description: "",
      priority: "normal",
      due_date: "",
      estimate_hours: "",
      project_id: projectId ?? "",
      assignee_id: "",
      reviewer_id: "",
    },
  });

  async function onSubmit(values: TaskFormValues) {
    try {
      const task = await taoTask.mutateAsync(toTaskPayload(values));
      router.push(`/tasks/${task.id}`);
    } catch (err) {
      if (err instanceof ApiError && err.errors) {
        for (const [truong, thongBao] of Object.entries(err.errors)) {
          setError(truong as keyof TaskFormValues, {
            message: thongBao[0],
          });
        }
        return;
      }

      // Không phải lỗi theo từng ô (403, mất mạng…) — gắn lên ô đầu tiên để
      // người dùng vẫn đọc được, thay vì lỗi biến mất không dấu vết.
      setError("title", {
        message: err instanceof Error ? err.message : "Không lưu được.",
      });
    }
  }

  return (
    <form
      onSubmit={(e) => void handleSubmit(onSubmit)(e)}
      className="space-y-5"
    >
      <Field label="Tiêu đề" required error={errors.title?.message}>
        {(id, describedBy) => (
          <TextInput
            id={id}
            aria-describedby={describedBy}
            autoFocus
            placeholder="Việc cần làm là gì?"
            {...register("title")}
          />
        )}
      </Field>

      <Field label="Mô tả" error={errors.description?.message}>
        {(id, describedBy) => (
          <TextArea
            id={id}
            aria-describedby={describedBy}
            rows={4}
            placeholder="Yêu cầu cụ thể, tiêu chí nghiệm thu…"
            {...register("description")}
          />
        )}
      </Field>

      <div className="grid gap-5 sm:grid-cols-2">
        <Field label="Mức ưu tiên" error={errors.priority?.message}>
          {(id) => (
            <SelectInput id={id} {...register("priority")}>
              {TASK_PRIORITIES.map((p) => (
                <option key={p.value} value={p.value}>
                  {p.label}
                </option>
              ))}
            </SelectInput>
          )}
        </Field>

        <Field
          label="Hạn hoàn thành"
          error={errors.due_date?.message}
          hint="Để trống nếu việc không có hạn cụ thể."
        >
          {(id, describedBy) => (
            <TextInput
              id={id}
              aria-describedby={describedBy}
              type="datetime-local"
              {...register("due_date")}
            />
          )}
        </Field>

        <Field label="Dự án" error={errors.project_id?.message}>
          {(id) => (
            <SelectInput id={id} {...register("project_id")}>
              <option value="">Không thuộc dự án nào</option>
              {duAn?.data.map((p) => (
                <option key={p.id} value={p.id}>
                  {p.name}
                </option>
              ))}
            </SelectInput>
          )}
        </Field>

        <Field
          label="Số giờ ước lượng"
          error={errors.estimate_hours?.message}
          hint="Ví dụ 7,5 giờ thì nhập 7.5"
        >
          {(id, describedBy) => (
            <TextInput
              id={id}
              aria-describedby={describedBy}
              inputMode="decimal"
              placeholder="0"
              {...register("estimate_hours")}
            />
          )}
        </Field>

        <Field
          label="Người thực hiện"
          error={errors.assignee_id?.message}
          hint="Để trống thì việc chưa có người làm."
        >
          {(id, describedBy) => (
            <SelectInput
              id={id}
              aria-describedby={describedBy}
              {...register("assignee_id")}
            >
              <option value="">Chưa giao</option>
              {dsNguoi?.map((n) => (
                <option key={n.id} value={n.id}>
                  {n.name}
                  {n.department ? ` — ${n.department}` : ""}
                </option>
              ))}
            </SelectInput>
          )}
        </Field>

        <div className="-mt-3">
          <DirectoryHint directory={danhBa} />
        </div>

        <Field label="Người duyệt" error={errors.reviewer_id?.message}>
          {(id) => (
            <SelectInput id={id} {...register("reviewer_id")}>
              <option value="">Không cần duyệt</option>
              {dsNguoi?.map((n) => (
                <option key={n.id} value={n.id}>
                  {n.name}
                </option>
              ))}
            </SelectInput>
          )}
        </Field>
      </div>

      <div className="border-line flex gap-3 border-t pt-5">
        <Button type="submit" variant="primary" loading={isSubmitting}>
          Tạo việc
        </Button>
        <Button type="button" variant="ghost" onClick={() => router.back()}>
          Huỷ
        </Button>
      </div>
    </form>
  );
}
