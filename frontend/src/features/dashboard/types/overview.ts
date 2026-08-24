/** Khớp với App\Http\Controllers\Api\V1\Dashboard\OverviewController. */

export interface OverviewSummary {
  open_tasks: number;
  overdue_tasks: number;
  due_today: number;
  unassigned_tasks: number;
  completed_this_week: number;
  active_projects: number;
  active_employees: number;
}

export interface WorkloadRow {
  id: string;
  name: string;
  department: string | null;
  open: number;
  overdue: number;
}

export interface ProjectProgressRow {
  id: string;
  name: string;
  status: { value: string; label: string };
  total: number;
  done: number;
  overdue: number;
  progress_percent: number;
}

export interface OverdueTask {
  id: string;
  title: string;
  assignee: string | null;
  project: string | null;
  due_date: string | null;
  days_overdue: number;
}

/**
 * `total` luôn đi kèm `rows`.
 *
 * Backend cắt bảng ở 12 dòng. Không có `total` thì giao diện hiện 12 dòng trông
 * như toàn bộ công ty, trong khi còn người nữa đang ôm việc trễ — cắt im lặng
 * là kiểu nói dối khó chịu nhất.
 */
export interface Capped<T> {
  rows: T[];
  total: number;
}

export interface Overview {
  summary: OverviewSummary;
  workload: Capped<WorkloadRow>;
  projects: Capped<ProjectProgressRow>;
  most_overdue: OverdueTask[];
}
