"use client";

import Link from "next/link";
import { useState } from "react";

import { Button } from "@/components/ui/button";
import { SelectInput, TextInput } from "@/components/ui/field";
import { IconPlus } from "@/components/ui/icon";
import { Pagination } from "@/components/ui/pagination";
import { EmptyState, ErrorState, ListSkeleton } from "@/components/ui/states";
import { useProjects } from "@/features/projects/api/projects-api";
import { ProjectStatusBadge } from "@/features/projects/components/project-badges";
import { ProjectFormDialog } from "@/features/projects/components/project-form-dialog";
import {
  PROJECT_STATUSES,
  type ProjectStatusValue,
} from "@/features/projects/types/project";
import { useCurrentUser } from "@/features/auth/api/auth-api";
import { formatDate } from "@/lib/format";

/**
 * Danh sách dự án.
 *
 * Bộ lọc giữ trong state chứ không trong địa chỉ như trang Công việc: dự án
 * thường chỉ vài chục cái và người ta không gửi cho nhau đường dẫn "dự án đang
 * chạy". Không phải chỗ nào cũng cần đến mức đó.
 */
export default function ProjectsPage() {
  const { data: user } = useCurrentUser();
  const [trangThai, setTrangThai] = useState<ProjectStatusValue | "">("");
  const [tuKhoa, setTuKhoa] = useState("");
  const [trang, setTrang] = useState(1);
  const [dangTao, setDangTao] = useState(false);

  const { data, isPending, isError, error, refetch } = useProjects({
    status: trangThai,
    search: tuKhoa || undefined,
    page: trang,
  });

  const duocTao = user?.permissions.includes("project.manage") === true;

  return (
    <div data-tone="task" className="enter space-y-6">
      <header className="flex flex-wrap items-end justify-between gap-4">
        <div>
          <span
            aria-hidden="true"
            className="bg-tone mb-3 block h-[3px] w-9 rounded-full"
          />
          <h1 className="text-[1.65rem] leading-tight font-semibold tracking-[-0.035em]">
            Dự án
          </h1>
          <p className="text-ink-soft mt-1.5 text-[0.9rem]">
            Dự án bạn phụ trách, tham gia, hoặc đang có việc được giao.
          </p>
        </div>

        {duocTao && (
          <Button variant="primary" onClick={() => setDangTao(true)}>
            <IconPlus className="size-4" />
            Tạo dự án
          </Button>
        )}
      </header>

      <div className="grid gap-3 sm:grid-cols-[1fr_12rem]">
        <div>
          <label htmlFor="tim-du-an" className="sr-only">
            Tìm dự án
          </label>
          <TextInput
            id="tim-du-an"
            type="search"
            value={tuKhoa}
            placeholder="Tìm theo tên hoặc mã…"
            onChange={(e) => {
              setTuKhoa(e.target.value);
              setTrang(1);
            }}
          />
        </div>

        <div>
          <label htmlFor="loc-trang-thai-du-an" className="sr-only">
            Trạng thái dự án
          </label>
          <SelectInput
            id="loc-trang-thai-du-an"
            value={trangThai}
            onChange={(e) => {
              setTrangThai(e.target.value as ProjectStatusValue | "");
              setTrang(1);
            }}
          >
            <option value="">Mọi trạng thái</option>
            {PROJECT_STATUSES.map((s) => (
              <option key={s.value} value={s.value}>
                {s.label}
              </option>
            ))}
          </SelectInput>
        </div>
      </div>

      {isPending && <ListSkeleton rows={4} />}

      {isError && <ErrorState error={error} onRetry={() => void refetch()} />}

      {data && data.data.length === 0 && (
        <EmptyState
          title="Chưa có dự án nào"
          description="Dự án bạn phụ trách hoặc tham gia sẽ hiện ở đây."
          action={
            duocTao ? (
              <Button size="sm" onClick={() => setDangTao(true)}>
                Tạo dự án
              </Button>
            ) : undefined
          }
        />
      )}

      {data && data.data.length > 0 && (
        <>
          <ul className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
            {data.data.map((duAn) => (
              <li key={duAn.id}>
                <Link
                  href={`/projects/${duAn.id}`}
                  className="border-line bg-paper-raised focus-frame lift shadow-card block h-full rounded-2xl border p-4"
                >
                  <div className="flex items-start justify-between gap-3">
                    <p className="leading-snug font-medium">{duAn.name}</p>
                    {duAn.code && (
                      <span className="bg-paper-sunken text-ink-faint shrink-0 rounded-md px-1.5 py-0.5 font-mono text-[0.68rem]">
                        {duAn.code}
                      </span>
                    )}
                  </div>

                  <div className="mt-3 flex flex-wrap items-center gap-2">
                    <ProjectStatusBadge
                      status={duAn.status.value}
                      label={duAn.status.label}
                    />
                    <span className="text-ink-faint text-[0.78rem]">
                      {duAn.task_count ?? 0} việc · {duAn.member_count ?? 0}{" "}
                      thành viên
                    </span>
                  </div>

                  {duAn.end_date && (
                    <p className="text-ink-faint mt-2 text-[0.78rem]">
                      Kết thúc {formatDate(duAn.end_date)}
                    </p>
                  )}
                </Link>
              </li>
            ))}
          </ul>

          <Pagination
            page={data.meta.current_page}
            lastPage={data.meta.last_page}
            total={data.meta.total}
            from={data.meta.from}
            to={data.meta.to}
            onChange={setTrang}
          />
        </>
      )}

      <ProjectFormDialog open={dangTao} onClose={() => setDangTao(false)} />
    </div>
  );
}
