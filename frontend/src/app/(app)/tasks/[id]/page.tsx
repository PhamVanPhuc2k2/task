import { TaskDetail } from "@/features/tasks/components/task-detail";

/**
 * Chi tiết công việc.
 *
 * Từ Next.js 15, `params` là Promise nên trang phải là server component để
 * await nó, rồi truyền id xuống component client lo phần dữ liệu và tương tác.
 */
export default async function TaskDetailPage({
  params,
}: {
  params: Promise<{ id: string }>;
}) {
  const { id } = await params;

  return <TaskDetail id={id} />;
}
