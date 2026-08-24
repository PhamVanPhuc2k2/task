import { Pill } from "@/components/ui/pill";

import { projectStatusMeta, type ProjectStatusValue } from "../types/project";

/** Nhãn trạng thái dự án. Xem chú thích ở `features/tasks/components/task-badges`. */
export function ProjectStatusBadge({
  status,
  label,
}: {
  status: ProjectStatusValue;
  label?: string;
}) {
  const meta = projectStatusMeta(status);

  return <Pill dotClass={meta.tone}>{label ?? meta.label}</Pill>;
}
