import { z } from "zod";

/**
 * Kiểm tra ở trình duyệt để người dùng biết sai ngay, KHÔNG phải để tin tưởng.
 * Luật thật nằm ở Form Request phía backend và vẫn chạy dù ai gửi gì.
 *
 * Giữ giới hạn khớp với `App\Http\Requests\Task\StoreTaskRequest` để không có
 * trường hợp form cho qua rồi server mới từ chối — người dùng đọc thành lỗi hệ
 * thống chứ không phải lỗi nhập liệu.
 */
export const taskSchema = z.object({
  title: z
    .string()
    .trim()
    .min(1, "Vui lòng nhập tiêu đề công việc.")
    .max(255, "Tiêu đề tối đa 255 ký tự."),

  description: z.string().max(20000, "Mô tả quá dài.").optional(),

  priority: z.enum(["low", "normal", "high", "urgent"]),

  due_date: z.string().optional(),

  estimate_hours: z
    .string()
    .optional()
    .refine(
      (v) => !v || (Number(v) >= 0 && Number(v) <= 9999.99),
      "Số giờ ước lượng phải từ 0 đến 9999,99.",
    ),

  project_id: z.string().optional(),
  assignee_id: z.string().optional(),
  reviewer_id: z.string().optional(),
});

export type TaskFormValues = z.infer<typeof taskSchema>;

export const editTaskSchema = taskSchema.pick({
  title: true,
  description: true,
  priority: true,
});

export type EditTaskFormValues = z.infer<typeof editTaskSchema>;

/**
 * Đổi giá trị form sang thân request.
 *
 * Chuỗi rỗng phải thành `null`, không gửi nguyên: backend kiểm `uuid` trên
 * `project_id`, và chuỗi rỗng sẽ trượt luật đó thành lỗi 422 khó hiểu.
 */
export function toTaskPayload(values: TaskFormValues) {
  const rong = (v: string | undefined) => (v && v.trim() !== "" ? v : null);

  return {
    title: values.title.trim(),
    description: rong(values.description),
    priority: values.priority,
    due_date: rong(values.due_date),
    estimate_hours: rong(values.estimate_hours),
    project_id: rong(values.project_id),
    assignee_id: rong(values.assignee_id),
    reviewer_id: rong(values.reviewer_id),
  };
}
