"use client";

import Link from "next/link";
import { useState } from "react";

import { Avatar } from "@/components/ui/pill";
import { Button, buttonClass } from "@/components/ui/button";
import { SelectInput } from "@/components/ui/field";
import { EmptyState, ErrorState, ListSkeleton } from "@/components/ui/states";
import { useTasks } from "@/features/tasks/api/tasks-api";
import { TaskTable } from "@/features/tasks/components/task-table";
import { useCurrentUser } from "@/features/auth/api/auth-api";
import { useProjectBonus } from "@/features/bonus/api/bonus-api";
import { formatMoney } from "@/features/payroll/types/payroll";
import { useAssignableUsers } from "@/features/users/api/directory-api";
import { formatDate } from "@/lib/format";

import {
  useProject,
  useProjectMembers,
  useRemoveProjectMember,
  useSetProjectMember,
} from "../api/projects-api";
import {
  PROJECT_ROLES,
  type Project,
  type ProjectRoleValue,
} from "../types/project";
import { ProjectStatusBadge } from "./project-badges";
import { ProjectFormDialog } from "./project-form-dialog";

export function ProjectDetail({ id }: { id: string }) {
  const { data: duAn, isPending, isError, error, refetch } = useProject(id);

  if (isPending) return <ListSkeleton rows={4} />;
  if (isError)
    return <ErrorState error={error} onRetry={() => void refetch()} />;

  return <NoiDung duAn={duAn} />;
}

function NoiDung({ duAn }: { duAn: Project }) {
  const [dangSua, setDangSua] = useState(false);

  const { data: viec, isPending: dangTaiViec } = useTasks({
    project_id: duAn.id,
    per_page: 50,
  });

  return (
    <div data-tone="task" className="enter space-y-8">
      <header className="space-y-3">
        <Link
          href="/projects"
          className="text-ink-faint hover:text-ink focus-frame inline-block rounded text-[0.82rem]"
        >
          ← Dự án
        </Link>

        <div className="flex flex-wrap items-start justify-between gap-4">
          <div>
            <h1 className="text-[1.55rem] leading-tight font-semibold tracking-[-0.035em] sm:text-[1.8rem]">
              {duAn.name}
            </h1>

            <div className="mt-2.5 flex flex-wrap items-center gap-2.5">
              <ProjectStatusBadge
                status={duAn.status.value}
                label={duAn.status.label}
              />
              {duAn.code && (
                <span className="text-ink-faint font-mono text-[0.72rem]">
                  {duAn.code}
                </span>
              )}
              {duAn.owner && (
                <span className="text-ink-faint text-[0.8rem]">
                  Phụ trách: {duAn.owner.name}
                </span>
              )}
            </div>
          </div>

          <div className="flex gap-2.5">
            <Button onClick={() => setDangSua(true)}>Sửa</Button>
            {duAn.status.is_open && (
              <Link
                href={`/tasks/new?project_id=${duAn.id}`}
                className={buttonClass("primary")}
              >
                Tạo việc
              </Link>
            )}
          </div>
        </div>

        {duAn.description && (
          <p className="text-ink-soft max-w-2xl leading-relaxed">
            {duAn.description}
          </p>
        )}

        {!duAn.status.is_open && (
          <p className="border-notice-line bg-notice-surface text-notice rounded-xl border px-4 py-2.5 text-[0.85rem]">
            Dự án đã đóng — không nhận thêm việc mới. Đổi trạng thái nếu cần mở
            lại.
          </p>
        )}
      </header>

      <div className="grid gap-8 lg:grid-cols-[1fr_18rem]">
        <section aria-labelledby="viec-du-an" className="space-y-4">
          <h2 id="viec-du-an" className={NHAN_MUC}>
            Công việc trong dự án
          </h2>

          {dangTaiViec && <ListSkeleton rows={4} />}

          {viec &&
            (viec.data.length === 0 ? (
              <EmptyState
                title="Chưa có việc nào"
                description="Việc thuộc dự án này sẽ hiện ở đây."
              />
            ) : (
              <>
                <TaskTable tasks={viec.data} />
                {viec.meta.total > viec.data.length && (
                  <p className="text-ink-faint text-[0.82rem]">
                    Đang hiện {viec.data.length} trên {viec.meta.total} việc.{" "}
                    <Link
                      href={`/tasks?project_id=${duAn.id}`}
                      className="underline underline-offset-4"
                    >
                      Xem tất cả
                    </Link>
                  </p>
                )}
              </>
            ))}
        </section>

        <div className="space-y-8">
          <QuyThuong projectId={duAn.id} />

          <aside className="tone-card rounded-2xl p-5">
            <h2 className={NHAN_MUC}>Thông tin</h2>

            <dl className="mt-4 space-y-3.5 text-[0.88rem]">
              <div>
                <dt className="text-ink-faint text-[0.76rem]">Phòng ban</dt>
                <dd className="mt-0.5">{duAn.department?.name ?? "—"}</dd>
              </div>
              <div>
                <dt className="text-ink-faint text-[0.76rem]">Bắt đầu</dt>
                <dd className="mt-0.5">{formatDate(duAn.start_date)}</dd>
              </div>
              <div>
                <dt className="text-ink-faint text-[0.76rem]">Kết thúc</dt>
                <dd className="mt-0.5">{formatDate(duAn.end_date)}</dd>
              </div>
            </dl>
          </aside>

          <ThanhVien projectId={duAn.id} />
        </div>
      </div>

      <ProjectFormDialog
        open={dangSua}
        onClose={() => setDangSua(false)}
        project={duAn}
      />
    </div>
  );
}

function ThanhVien({ projectId }: { projectId: string }) {
  const { data: thanhVien, isPending } = useProjectMembers(projectId);
  const { data: danhBa } = useAssignableUsers();
  const dsNguoi = danhBa?.people;
  const them = useSetProjectMember(projectId);
  const go = useRemoveProjectMember(projectId);

  const [nguoi, setNguoi] = useState("");
  const [vaiTro, setVaiTro] = useState<ProjectRoleValue>("member");

  return (
    <aside className="tone-card rounded-2xl p-5">
      <h2 className={NHAN_MUC}>Thành viên</h2>

      {isPending && <ListSkeleton rows={2} />}

      {thanhVien && thanhVien.length === 0 && (
        <p className="text-ink-faint mt-3 text-[0.85rem]">
          Chưa có thành viên nào.
        </p>
      )}

      {thanhVien && thanhVien.length > 0 && (
        <ul className="mt-4 space-y-3">
          {thanhVien.map((tv) => (
            <li key={tv.id} className="flex items-center gap-2.5">
              <Avatar name={tv.name} size="sm" />

              <div className="min-w-0 flex-1">
                <p className="truncate text-[0.86rem]">{tv.name}</p>
                <p className="text-ink-faint text-[0.74rem]">
                  {PROJECT_ROLES.find((r) => r.value === tv.role)?.label ??
                    tv.role}
                </p>
              </div>

              <button
                type="button"
                onClick={() => go.mutate(tv.id)}
                aria-label={`Gỡ ${tv.name} khỏi dự án`}
                className="text-ink-faint hover:text-danger focus-frame shrink-0 rounded p-1 text-[0.78rem]"
              >
                Gỡ
              </button>
            </li>
          ))}
        </ul>
      )}

      {/* Người không có quyền quản lý dự án vẫn thấy ô này, nhưng backend sẽ
          trả 403 — hiện lỗi đó ra thay vì đoán trước quyền ở frontend. */}
      <div className="border-line mt-5 space-y-2.5 border-t pt-4">
        <label htmlFor="them-thanh-vien" className="sr-only">
          Thêm thành viên
        </label>
        <SelectInput
          id="them-thanh-vien"
          value={nguoi}
          onChange={(e) => setNguoi(e.target.value)}
        >
          <option value="">Chọn người…</option>
          {dsNguoi?.map((n) => (
            <option key={n.id} value={n.id}>
              {n.name}
            </option>
          ))}
        </SelectInput>

        <label htmlFor="vai-tro-thanh-vien" className="sr-only">
          Vai trò trong dự án
        </label>
        <SelectInput
          id="vai-tro-thanh-vien"
          value={vaiTro}
          onChange={(e) => setVaiTro(e.target.value as ProjectRoleValue)}
        >
          {PROJECT_ROLES.map((r) => (
            <option key={r.value} value={r.value}>
              {r.label}
            </option>
          ))}
        </SelectInput>

        <Button
          size="sm"
          className="w-full"
          disabled={!nguoi}
          loading={them.isPending}
          onClick={() =>
            them.mutate(
              { user_id: nguoi, role: vaiTro },
              { onSuccess: () => setNguoi("") },
            )
          }
        >
          Thêm vào dự án
        </Button>

        {(them.error ?? go.error) && (
          <p role="alert" className="text-danger text-[0.8rem]">
            {(them.error ?? go.error)?.message}
          </p>
        )}
      </div>
    </aside>
  );
}

// Tiêu đề của từng khối trong trang chi tiết. Trước đây là chữ hoa monospace
// giãn chữ — kiểu chữ đó đọc chậm hơn hẳn với tiếng Việt, vì dấu thanh nằm
// trên chữ hoa trông rất chật.
/**
 * Lối vào quỹ thưởng, ngay tại trang dự án.
 *
 * Trước đây muốn mở quỹ của một dự án phải sang `/bonus` rồi chọn lại dự án đó
 * từ danh sách thả xuống — thừa hai bước, và với công ty nhiều dự án thì dự án
 * cần tìm có khi không có trong danh sách.
 *
 * Chỉ hiện với người có `bonus.view.all`; nhân viên thường không thấy gì.
 */
function QuyThuong({ projectId }: { projectId: string }) {
  const { data: user } = useCurrentUser();
  const { data, isPending } = useProjectBonus(
    projectId,
    user?.permissions.includes("bonus.view.all") === true,
  );

  if (isPending || !data) return null;

  return (
    <aside className="tone-card rounded-2xl p-5">
      <h2 className={NHAN_MUC}>Quỹ thưởng</h2>

      {data.data === null ? (
        <p className="text-ink-faint mt-3 text-[0.86rem]">
          Chưa lập quỹ thưởng cho dự án này.
        </p>
      ) : (
        <div className="mt-3 space-y-1">
          <p className="text-[1.15rem] leading-none font-semibold tabular-nums">
            {formatMoney(data.data.total_amount)}
          </p>
          <p className="text-ink-soft text-[0.8rem]">
            {data.data.status_label} · đã chia{" "}
            {formatMoney(data.data.allocated_total)}
          </p>
        </div>
      )}

      <Link
        href="/bonus"
        className={buttonClass("secondary", "sm", "mt-4 w-full")}
      >
        {data.data === null ? "Lập quỹ thưởng" : "Mở quỹ thưởng"}
      </Link>
    </aside>
  );
}

const NHAN_MUC = "text-ink text-[0.95rem] font-semibold tracking-tight";
