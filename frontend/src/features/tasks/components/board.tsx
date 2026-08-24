"use client";

import {
  DndContext,
  DragOverlay,
  KeyboardSensor,
  PointerSensor,
  closestCorners,
  useDraggable,
  useDroppable,
  useSensor,
  useSensors,
  type Announcements,
  type DragEndEvent,
  type DragStartEvent,
} from "@dnd-kit/core";
import { useMemo, useState } from "react";

import { SelectInput } from "@/components/ui/field";
import { cn } from "@/lib/cn";

import { useChangeTaskStatus } from "../api/tasks-api";
import {
  ALLOWED_TRANSITIONS,
  BOARD_COLUMNS,
  statusMeta,
  type Task,
  type TaskStatusValue,
} from "../types/task";
import { TaskCard } from "./task-card";

/**
 * Bảng Kanban kéo thả theo trạng thái.
 *
 * Kéo thả KHÔNG phải cách duy nhất để đổi trạng thái: mỗi thẻ còn có ô chọn
 * trạng thái riêng. Thao tác kéo trên màn hình cảm ứng nhỏ rất khó trúng, và
 * người dùng bàn phím hoặc trình đọc màn hình cần một đường đi thẳng. Kéo thả
 * là lối tắt cho chuột, không phải điều kiện để dùng được tính năng.
 *
 * Luồng chuyển trạng thái hợp lệ kiểm ở cả hai đầu: ở đây để không cho thả vào
 * cột chắc chắn bị từ chối, và ở backend vì đó mới là chỗ có thẩm quyền.
 */
export function TaskBoard({ tasks }: { tasks: Task[] }) {
  const doiTrangThai = useChangeTaskStatus();

  // Ghi đè tạm thời trong lúc chờ server trả lời. Không có nó thì thẻ bật
  // ngược về cột cũ rồi mới nhảy sang cột mới — nhìn như thao tác hỏng.
  const [tamThoi, setTamThoi] = useState<Record<string, TaskStatusValue>>({});
  const [dangKeo, setDangKeo] = useState<Task | null>(null);
  const [loi, setLoi] = useState<string | null>(null);

  /*
  | Sensor PHẢI đi qua `useSensor`/`useSensors`.
  |
  | Bản trước dựng tay một mảng đối tượng `{ sensor, options }` — đúng hình
  | dạng, nên TypeScript không kêu và kéo thả vẫn chạy được. Nhưng hai hook đó
  | tồn tại **chỉ để ghi nhớ tham chiếu** (đọc thẳng mã nguồn của @dnd-kit: cả
  | hai chỉ là một `useMemo`).
  |
  | Không ghi nhớ thì mảng mang danh tính mới ở MỖI lần render. `DndContext`
  | tính lại toàn bộ danh sách activator theo danh tính đó, sinh ra listener
  | mới cho từng thẻ — và nó xảy ra ngay giữa lúc kéo, vì `setDangKeo` làm
  | component render lại. Lãng phí, và đúng loại thứ hỏng lúc bảng có nhiều
  | thẻ chứ không hỏng lúc thử với ba thẻ.
  */
  const sensors = useSensors(
    // Phải kéo 6px mới tính là kéo, nếu không mỗi cú bấm vào thẻ đều bị hiểu
    // thành bắt đầu kéo và liên kết mở task không bao giờ chạy.
    useSensor(PointerSensor, { activationConstraint: { distance: 6 } }),
    useSensor(KeyboardSensor),
  );

  const trangThaiCua = (task: Task): TaskStatusValue =>
    tamThoi[task.id] ?? task.status.value;

  const cot = useMemo(() => {
    const gom: Record<string, Task[]> = Object.fromEntries(
      BOARD_COLUMNS.map((c) => [c, []]),
    );

    for (const task of tasks) {
      const tt = trangThaiCua(task);
      gom[tt]?.push(task);
    }

    return gom;
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [tasks, tamThoi]);

  function chuyen(task: Task, dich: TaskStatusValue) {
    const nguon = trangThaiCua(task);
    if (nguon === dich) return;

    if (!ALLOWED_TRANSITIONS[nguon].includes(dich)) {
      setLoi(
        `Không chuyển thẳng từ "${statusMeta(nguon).label}" sang "${statusMeta(dich).label}" được.`,
      );
      return;
    }

    setLoi(null);
    setTamThoi((truoc) => ({ ...truoc, [task.id]: dich }));

    doiTrangThai.mutate(
      { id: task.id, status: dich },
      {
        onError: (err) => {
          setTamThoi(({ [task.id]: _bo, ...con }) => con);
          setLoi(err.message);
        },
        onSuccess: () => {
          setTamThoi(({ [task.id]: _bo, ...con }) => con);
        },
      },
    );
  }

  function onDragStart(event: DragStartEvent) {
    setDangKeo(tasks.find((t) => t.id === event.active.id) ?? null);
  }

  function onDragEnd(event: DragEndEvent) {
    setDangKeo(null);

    const dich = event.over?.id;
    const task = tasks.find((t) => t.id === event.active.id);

    if (task && typeof dich === "string") {
      chuyen(task, dich as TaskStatusValue);
    }
  }

  return (
    <div className="space-y-3">
      {loi && (
        <p
          role="alert"
          className="border-danger-line bg-danger-surface text-danger rounded-xl border px-4 py-2.5 text-[0.86rem]"
        >
          {loi}
        </p>
      )}

      <DndContext
        sensors={sensors}
        collisionDetection={closestCorners}
        accessibility={{ announcements: THONG_BAO }}
        onDragStart={onDragStart}
        onDragEnd={onDragEnd}
        onDragCancel={() => setDangKeo(null)}
      >
        <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-5">
          {BOARD_COLUMNS.map((trangThai) => (
            <Cot
              key={trangThai}
              trangThai={trangThai}
              tasks={cot[trangThai] ?? []}
              onChuyen={chuyen}
            />
          ))}
        </div>

        {/* Thẻ bay theo con trỏ trong lúc kéo. */}
        <DragOverlay>
          {dangKeo && (
            <div className="rotate-1 opacity-90">
              <TaskCard task={dangKeo} />
            </div>
          )}
        </DragOverlay>
      </DndContext>
    </div>
  );
}

function Cot({
  trangThai,
  tasks,
  onChuyen,
}: {
  trangThai: TaskStatusValue;
  tasks: Task[];
  onChuyen: (task: Task, dich: TaskStatusValue) => void;
}) {
  const meta = statusMeta(trangThai);
  const { setNodeRef, isOver } = useDroppable({ id: trangThai });

  return (
    <section
      ref={setNodeRef}
      aria-labelledby={`cot-${trangThai}`}
      className={cn(
        // Cột là mặt LÕM, thẻ bên trong là mặt nổi. Nhờ chênh lệch đó, thẻ
        // trông như đang nằm trong cột chứ không phải dán đè lên.
        "rounded-2xl border p-3 transition-colors",
        isOver
          ? "border-accent bg-accent-surface"
          : "border-line bg-paper-sunken/60",
      )}
    >
      <h2
        id={`cot-${trangThai}`}
        className="mb-3 flex items-center gap-2 px-1 text-[0.85rem] font-semibold"
      >
        <span
          aria-hidden="true"
          className={cn("size-2 rounded-full", meta.tone)}
        />
        {meta.label}
        <span className="bg-paper-raised text-ink-faint ml-auto inline-flex h-5 min-w-5 items-center justify-center rounded-full px-1.5 text-[0.72rem] font-semibold">
          {tasks.length}
        </span>
      </h2>

      <ul className="space-y-2.5">
        {tasks.map((task) => (
          <li key={task.id}>
            <TheKeoDuoc task={task} trangThai={trangThai} onChuyen={onChuyen} />
          </li>
        ))}
      </ul>

      {tasks.length === 0 && (
        <p className="text-ink-faint py-6 text-center text-[0.8rem]">Trống</p>
      )}
    </section>
  );
}

function TheKeoDuoc({
  task,
  trangThai,
  onChuyen,
}: {
  task: Task;
  trangThai: TaskStatusValue;
  onChuyen: (task: Task, dich: TaskStatusValue) => void;
}) {
  const { attributes, listeners, setNodeRef, isDragging } = useDraggable({
    id: task.id,
  });

  const dichHopLe = ALLOWED_TRANSITIONS[trangThai];

  return (
    <div className={cn(isDragging && "opacity-40")}>
      <div ref={setNodeRef} {...listeners} {...attributes}>
        <TaskCard task={task} className="cursor-grab active:cursor-grabbing" />
      </div>

      {/* Đường đi không cần kéo thả — cho cảm ứng, bàn phím, trình đọc màn hình. */}
      {dichHopLe.length > 0 && (
        <div className="mt-1.5">
          <label htmlFor={`chuyen-${task.id}`} className="sr-only">
            Chuyển “{task.title}” sang trạng thái khác
          </label>
          <SelectInput
            id={`chuyen-${task.id}`}
            value=""
            onChange={(e) => {
              if (e.target.value) {
                onChuyen(task, e.target.value as TaskStatusValue);
              }
            }}
            className="h-8 py-0 text-[0.78rem]"
          >
            <option value="">Chuyển sang…</option>
            {dichHopLe.map((dich) => (
              <option key={dich} value={dich}>
                {statusMeta(dich).label}
              </option>
            ))}
          </SelectInput>
        </div>
      )}
    </div>
  );
}

/** Thông báo cho trình đọc màn hình. Mặc định của thư viện là tiếng Anh. */
const THONG_BAO: Announcements = {
  onDragStart: ({ active }) => `Bắt đầu kéo công việc ${String(active.id)}.`,
  onDragOver: ({ over }) =>
    over
      ? `Đang ở trên cột ${statusMeta(over.id as TaskStatusValue).label}.`
      : "",
  onDragEnd: ({ over }) =>
    over
      ? `Đã thả vào cột ${statusMeta(over.id as TaskStatusValue).label}.`
      : "Đã huỷ kéo.",
  onDragCancel: () => "Đã huỷ kéo.",
};
