"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { useState } from "react";

import { Avatar } from "@/components/ui/pill";
import { Button } from "@/components/ui/button";
import { Dialog } from "@/components/ui/dialog";
import { Field, SelectInput, TextArea, TextInput } from "@/components/ui/field";
import { ErrorState, ListSkeleton } from "@/components/ui/states";
import { CommentSection } from "@/features/comments/components/comment-section";
import { useAssignableUsers } from "@/features/users/api/directory-api";
import {
  formatDateTime,
  formatDueDistance,
  formatHours,
  toDatetimeLocalValue,
} from "@/lib/format";

import {
  useAssignTask,
  useChangeDueDate,
  useChangeTaskStatus,
  useDeleteTask,
  useTask,
  useUpdateTask,
} from "../api/tasks-api";
import {
  ALLOWED_TRANSITIONS,
  statusMeta,
  TASK_PRIORITIES,
  type Task,
  type TaskPriorityValue,
  type TaskStatusValue,
} from "../types/task";
import { ActivityTimeline } from "./activity-timeline";
import { OverdueBadge, PriorityBadge, StatusBadge } from "./task-badges";

export function TaskDetail({ id }: { id: string }) {
  const { data: task, isPending, isError, error, refetch } = useTask(id);

  if (isPending) return <ListSkeleton rows={5} />;
  if (isError)
    return <ErrorState error={error} onRetry={() => void refetch()} />;

  return <NoiDung task={task} />;
}

function NoiDung({ task }: { task: Task }) {
  const router = useRouter();
  const doiTrangThai = useChangeTaskStatus();
  const xoa = useDeleteTask();

  const [dangMo, setDangMo] = useState<
    "assign" | "due-date" | "edit" | "delete" | null
  >(null);
  const [loi, setLoi] = useState<string | null>(null);

  const dichHopLe = ALLOWED_TRANSITIONS[task.status.value];

  return (
    <div data-tone="task" className="enter space-y-8">
      <header className="space-y-3">
        <Link
          href="/tasks"
          className="text-ink-faint hover:text-ink focus-frame inline-block rounded text-[0.82rem]"
        >
          ← Công việc
        </Link>

        <h1 className="text-[1.55rem] leading-tight font-semibold tracking-[-0.035em] sm:text-[1.8rem]">
          {task.title}
        </h1>

        <div className="flex flex-wrap items-center gap-2.5">
          <StatusBadge status={task.status.value} label={task.status.label} />
          <PriorityBadge
            priority={task.priority.value}
            label={task.priority.label}
          />
          {task.is_overdue && <OverdueBadge />}
          {task.project && (
            <Link
              href={`/projects/${task.project.id}`}
              className="text-ink-faint hover:text-ink focus-frame rounded text-[0.8rem] underline underline-offset-4"
            >
              {task.project.name}
            </Link>
          )}
        </div>
      </header>

      {loi && (
        <p
          role="alert"
          className="border-danger-line bg-danger-surface text-danger rounded-xl border px-4 py-2.5 text-[0.86rem]"
        >
          {loi}
        </p>
      )}

      {/* Hành động. Mỗi thứ một luật riêng nên là nút riêng, không gộp vào một
          form "sửa mọi thứ" — dời hạn bắt buộc kèm lý do, giao lại cần quyền. */}
      <div className="flex flex-wrap items-center gap-2.5">
        {dichHopLe.length > 0 ? (
          <div className="w-48">
            <label htmlFor="doi-trang-thai" className="sr-only">
              Chuyển trạng thái
            </label>
            <SelectInput
              id="doi-trang-thai"
              value=""
              disabled={doiTrangThai.isPending}
              onChange={(e) => {
                if (!e.target.value) return;
                setLoi(null);
                doiTrangThai.mutate(
                  { id: task.id, status: e.target.value as TaskStatusValue },
                  { onError: (err) => setLoi(err.message) },
                );
              }}
              className="h-10"
            >
              <option value="">Chuyển sang…</option>
              {dichHopLe.map((dich) => (
                <option key={dich} value={dich}>
                  {statusMeta(dich).label}
                </option>
              ))}
            </SelectInput>
          </div>
        ) : (
          <p className="text-ink-faint text-[0.84rem]">
            Việc đã đóng — không chuyển trạng thái được nữa.
          </p>
        )}

        <Button onClick={() => setDangMo("assign")}>Giao lại</Button>
        <Button onClick={() => setDangMo("due-date")}>Dời hạn</Button>
        <Button onClick={() => setDangMo("edit")}>Sửa</Button>
        <Button variant="danger" onClick={() => setDangMo("delete")}>
          Xoá
        </Button>
      </div>

      <div className="grid gap-8 lg:grid-cols-[1fr_18rem]">
        {/* Mỗi khối một thẻ riêng. Trước đây cột chính đổ thẳng lên nền trang
            trong khi cột phải là thẻ, nên trang trông như dựng dở một nửa. */}
        <div className="space-y-5">
          <section
            aria-labelledby="mo-ta"
            className="tone-card rounded-2xl p-5"
          >
            <h2 id="mo-ta" className={NHAN_MUC}>
              Mô tả
            </h2>
            {task.description ? (
              <p className="mt-3 leading-relaxed whitespace-pre-wrap">
                {task.description}
              </p>
            ) : (
              <p className="text-ink-faint mt-3 text-[0.88rem]">
                Chưa có mô tả.
              </p>
            )}
          </section>

          <section
            aria-labelledby="hoat-dong"
            className="tone-card rounded-2xl p-5"
          >
            <h2 id="hoat-dong" className={NHAN_MUC}>
              Hoạt động
            </h2>
            <div className="mt-4">
              <ActivityTimeline taskId={task.id} />
            </div>
          </section>

          <section
            aria-labelledby="binh-luan"
            className="tone-card rounded-2xl p-5"
          >
            <h2 id="binh-luan" className={NHAN_MUC}>
              Trao đổi
            </h2>
            <div className="mt-4">
              <CommentSection taskId={task.id} />
            </div>
          </section>
        </div>

        <aside className="tone-card h-fit rounded-2xl p-5">
          <h2 className={NHAN_MUC}>Chi tiết</h2>

          <dl className="mt-4 space-y-3.5 text-[0.88rem]">
            <Dong nhan="Người thực hiện">
              {task.assignee ? (
                <span className="flex items-center gap-2">
                  <Avatar name={task.assignee.name} size="sm" />
                  {task.assignee.name}
                </span>
              ) : (
                <span className="text-ink-faint">Chưa giao</span>
              )}
            </Dong>

            <Dong nhan="Người giao việc">
              {task.assigner?.name ?? <span className="text-ink-faint">—</span>}
            </Dong>

            <Dong nhan="Người duyệt">
              {task.reviewer?.name ?? <span className="text-ink-faint">—</span>}
            </Dong>

            <Dong nhan="Hạn hoàn thành">
              {task.due_date ? (
                <span title={formatDateTime(task.due_date)}>
                  {formatDueDistance(task.due_date)}
                </span>
              ) : (
                <span className="text-ink-faint">Không hạn</span>
              )}
            </Dong>

            {/* Hiện công khai để việc dời hạn không diễn ra trong im lặng. */}
            {task.due_date_change_count > 0 && (
              <Dong nhan="Đã dời hạn">{task.due_date_change_count} lần</Dong>
            )}

            <Dong nhan="Ước lượng">{formatHours(task.estimate_hours)}</Dong>

            <Dong nhan="Tiến độ">
              <span className="flex items-center gap-2">
                <span className="bg-line h-1.5 w-20 overflow-hidden rounded-full">
                  <span
                    className="bg-accent block h-full rounded-full"
                    style={{ width: `${task.progress_percent}%` }}
                  />
                </span>
                {task.progress_percent}%
              </span>
            </Dong>

            <Dong nhan="Tạo lúc">{formatDateTime(task.created_at)}</Dong>
          </dl>
        </aside>
      </div>

      <AssignDialog
        task={task}
        open={dangMo === "assign"}
        onClose={() => setDangMo(null)}
      />
      <DueDateDialog
        task={task}
        open={dangMo === "due-date"}
        onClose={() => setDangMo(null)}
      />
      <EditDialog
        task={task}
        open={dangMo === "edit"}
        onClose={() => setDangMo(null)}
      />

      <Dialog
        open={dangMo === "delete"}
        onClose={() => setDangMo(null)}
        title="Xoá công việc này?"
        description="Việc bị xoá mềm — vẫn còn trong cơ sở dữ liệu như một phần lịch sử làm việc, nhưng không hiện ở đâu nữa."
      >
        <div className="flex gap-3">
          <Button
            variant="danger"
            loading={xoa.isPending}
            onClick={() =>
              xoa.mutate(task.id, {
                onSuccess: () => router.push("/tasks"),
                onError: (err) => {
                  setDangMo(null);
                  setLoi(err.message);
                },
              })
            }
          >
            Xoá
          </Button>
          <Button variant="ghost" onClick={() => setDangMo(null)}>
            Giữ lại
          </Button>
        </div>
      </Dialog>
    </div>
  );
}

// Tiêu đề của từng khối trong trang chi tiết. Trước đây là chữ hoa monospace
// giãn chữ — kiểu chữ đó đọc chậm hơn hẳn với tiếng Việt, vì dấu thanh nằm
// trên chữ hoa trông rất chật.
const NHAN_MUC = "text-ink text-[0.95rem] font-semibold tracking-tight";

function Dong({ nhan, children }: { nhan: string; children: React.ReactNode }) {
  return (
    <div>
      <dt className="text-ink-faint text-[0.76rem]">{nhan}</dt>
      <dd className="mt-0.5">{children}</dd>
    </div>
  );
}

function AssignDialog({
  task,
  open,
  onClose,
}: {
  task: Task;
  open: boolean;
  onClose: () => void;
}) {
  const giaoLai = useAssignTask();
  const { data: danhBa } = useAssignableUsers();
  const dsNguoi = danhBa?.people;
  const [nguoi, setNguoi] = useState(task.assignee?.id ?? "");

  return (
    <Dialog
      open={open}
      onClose={onClose}
      title="Giao lại công việc"
      description="Người bấm nút trở thành người giao việc mới."
    >
      <Field label="Người thực hiện" error={giaoLai.error?.message}>
        {(id) => (
          <SelectInput
            id={id}
            value={nguoi}
            onChange={(e) => setNguoi(e.target.value)}
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

      <div className="mt-5 flex gap-3">
        <Button
          variant="primary"
          loading={giaoLai.isPending}
          onClick={() =>
            giaoLai.mutate(
              { id: task.id, assignee_id: nguoi || null },
              { onSuccess: onClose },
            )
          }
        >
          Lưu
        </Button>
        <Button variant="ghost" onClick={onClose}>
          Huỷ
        </Button>
      </div>
    </Dialog>
  );
}

function DueDateDialog({
  task,
  open,
  onClose,
}: {
  task: Task;
  open: boolean;
  onClose: () => void;
}) {
  const doiHan = useChangeDueDate();
  const [han, setHan] = useState(toDatetimeLocalValue(task.due_date));
  const [lyDo, setLyDo] = useState("");

  return (
    <Dialog
      open={open}
      onClose={onClose}
      title="Dời hạn hoàn thành"
      description="Lý do là bắt buộc và được lưu lại. Dời hạn trong im lặng làm hỏng mọi chỉ số đúng hạn của cả công ty."
    >
      <div className="space-y-4">
        <Field label="Hạn mới" error={doiHan.error?.fieldError("due_date")}>
          {(id) => (
            <TextInput
              id={id}
              type="datetime-local"
              value={han}
              onChange={(e) => setHan(e.target.value)}
            />
          )}
        </Field>

        <Field
          label="Lý do"
          required
          error={doiHan.error?.fieldError("reason")}
          hint="Ít nhất 5 ký tự. Người khác sẽ đọc được lý do này."
        >
          {(id, describedBy) => (
            <TextArea
              id={id}
              aria-describedby={describedBy}
              rows={3}
              value={lyDo}
              onChange={(e) => setLyDo(e.target.value)}
              placeholder="Vì sao cần dời hạn?"
            />
          )}
        </Field>
      </div>

      <div className="mt-5 flex gap-3">
        <Button
          variant="primary"
          loading={doiHan.isPending}
          onClick={() =>
            doiHan.mutate(
              { id: task.id, due_date: han || null, reason: lyDo },
              { onSuccess: onClose },
            )
          }
        >
          Dời hạn
        </Button>
        <Button variant="ghost" onClick={onClose}>
          Huỷ
        </Button>
      </div>
    </Dialog>
  );
}

function EditDialog({
  task,
  open,
  onClose,
}: {
  task: Task;
  open: boolean;
  onClose: () => void;
}) {
  const capNhat = useUpdateTask(task.id);
  const [tieuDe, setTieuDe] = useState(task.title);
  const [moTa, setMoTa] = useState(task.description ?? "");
  const [uuTien, setUuTien] = useState<TaskPriorityValue>(task.priority.value);
  const [tienDo, setTienDo] = useState(task.progress_percent);

  return (
    <Dialog open={open} onClose={onClose} title="Sửa công việc">
      <div className="space-y-4">
        <Field
          label="Tiêu đề"
          required
          error={capNhat.error?.fieldError("title")}
        >
          {(id) => (
            <TextInput
              id={id}
              value={tieuDe}
              onChange={(e) => setTieuDe(e.target.value)}
            />
          )}
        </Field>

        <Field label="Mô tả" error={capNhat.error?.fieldError("description")}>
          {(id) => (
            <TextArea
              id={id}
              rows={4}
              value={moTa}
              onChange={(e) => setMoTa(e.target.value)}
            />
          )}
        </Field>

        <div className="grid gap-4 sm:grid-cols-2">
          <Field label="Mức ưu tiên">
            {(id) => (
              <SelectInput
                id={id}
                value={uuTien}
                onChange={(e) => setUuTien(e.target.value as TaskPriorityValue)}
              >
                {TASK_PRIORITIES.map((p) => (
                  <option key={p.value} value={p.value}>
                    {p.label}
                  </option>
                ))}
              </SelectInput>
            )}
          </Field>

          <Field label={`Tiến độ — ${tienDo}%`}>
            {(id) => (
              <input
                id={id}
                type="range"
                min={0}
                max={100}
                step={5}
                value={tienDo}
                onChange={(e) => setTienDo(Number(e.target.value))}
                className="accent-accent focus-frame h-10 w-full cursor-pointer"
              />
            )}
          </Field>
        </div>
      </div>

      <div className="mt-5 flex gap-3">
        <Button
          variant="primary"
          loading={capNhat.isPending}
          onClick={() =>
            capNhat.mutate(
              {
                title: tieuDe,
                description: moTa || null,
                priority: uuTien,
                progress_percent: tienDo,
              },
              { onSuccess: onClose },
            )
          }
        >
          Lưu
        </Button>
        <Button variant="ghost" onClick={onClose}>
          Huỷ
        </Button>
      </div>
    </Dialog>
  );
}
