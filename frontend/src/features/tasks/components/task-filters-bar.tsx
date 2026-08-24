"use client";

import { useEffect, useState } from "react";

import { SelectInput, TextInput } from "@/components/ui/field";
import { useProjects } from "@/features/projects/api/projects-api";

import type { TaskFilters } from "../api/tasks-api";
import { TASK_PRIORITIES, TASK_STATUSES } from "../types/task";

/**
 * Thanh lọc của danh sách công việc.
 *
 * Ô tìm kiếm có độ trễ 350ms trước khi gọi API. Không có nó thì mỗi phím gõ là
 * một request, và kết quả về không đúng thứ tự sẽ nhấp nháy ngược.
 */
export function TaskFiltersBar({
  filters,
  onChange,
  showProject = true,
}: {
  filters: TaskFilters;
  onChange: (patch: Partial<TaskFilters>) => void;
  showProject?: boolean;
}) {
  const tuKhoaNgoai = filters.search ?? "";
  const [tuKhoa, setTuKhoa] = useState(tuKhoaNgoai);
  const [tuKhoaTruoc, setTuKhoaTruoc] = useState(tuKhoaNgoai);
  const { data: duAn } = useProjects({ per_page: 100 });

  // Đồng bộ khi bộ lọc bị đổi từ bên ngoài — bấm "Xoá bộ lọc", hoặc bấm Back.
  //
  // Chỉnh state ngay trong thân render, không dùng useEffect: effect chạy SAU
  // khi đã vẽ xong, nên ô nhập sẽ nháy một khung hình với giá trị cũ rồi mới
  // đổi. Đây là cách React khuyến nghị cho "sửa state khi prop đổi".
  if (tuKhoaTruoc !== tuKhoaNgoai) {
    setTuKhoaTruoc(tuKhoaNgoai);
    setTuKhoa(tuKhoaNgoai);
  }

  useEffect(() => {
    if (tuKhoa === (filters.search ?? "")) return;

    const hen = setTimeout(() => onChange({ search: tuKhoa, page: 1 }), 350);

    return () => clearTimeout(hen);
    // onChange đổi mỗi lần render ở component cha, đưa vào deps sẽ hẹn lại
    // liên tục và ô tìm kiếm không bao giờ bắn đi.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [tuKhoa]);

  return (
    <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
      <div className="sm:col-span-2 lg:col-span-1">
        <label htmlFor="loc-tim" className="sr-only">
          Tìm theo tiêu đề hoặc mô tả
        </label>
        <TextInput
          id="loc-tim"
          type="search"
          value={tuKhoa}
          placeholder="Tìm việc…"
          onChange={(e) => setTuKhoa(e.target.value)}
        />
      </div>

      <div>
        <label htmlFor="loc-trang-thai" className="sr-only">
          Trạng thái
        </label>
        <SelectInput
          id="loc-trang-thai"
          value={filters.status ?? ""}
          onChange={(e) =>
            onChange({
              status: e.target.value as TaskFilters["status"],
              page: 1,
            })
          }
        >
          <option value="">Mọi trạng thái</option>
          {TASK_STATUSES.map((s) => (
            <option key={s.value} value={s.value}>
              {s.label}
            </option>
          ))}
        </SelectInput>
      </div>

      <div>
        <label htmlFor="loc-uu-tien" className="sr-only">
          Mức ưu tiên
        </label>
        <SelectInput
          id="loc-uu-tien"
          value={filters.priority ?? ""}
          onChange={(e) =>
            onChange({
              priority: e.target.value as TaskFilters["priority"],
              page: 1,
            })
          }
        >
          <option value="">Mọi mức ưu tiên</option>
          {TASK_PRIORITIES.map((p) => (
            <option key={p.value} value={p.value}>
              {p.label}
            </option>
          ))}
        </SelectInput>
      </div>

      {showProject && (
        <div>
          <label htmlFor="loc-du-an" className="sr-only">
            Dự án
          </label>
          <SelectInput
            id="loc-du-an"
            value={filters.project_id ?? ""}
            onChange={(e) => onChange({ project_id: e.target.value, page: 1 })}
          >
            <option value="">Mọi dự án</option>
            {duAn?.data.map((p) => (
              <option key={p.id} value={p.id}>
                {p.name}
              </option>
            ))}
          </SelectInput>
        </div>
      )}

      <label className="border-line bg-paper-raised focus-within:border-line-strong hover:bg-paper-sunken flex cursor-pointer items-center gap-2.5 rounded-xl border px-3.5 py-2 text-[0.86rem] transition-colors">
        <input
          type="checkbox"
          className="accent-accent size-4 cursor-pointer"
          checked={filters.overdue === true}
          onChange={(e) => onChange({ overdue: e.target.checked, page: 1 })}
        />
        Chỉ việc quá hạn
      </label>

      <LocDenTuTongQuan filters={filters} onChange={onChange} />
    </div>
  );
}

/**
 * Chip cho các bộ lọc đến từ trang Tổng quan.
 *
 * Bốn cờ này không có ô nhập nào trên thanh lọc — chúng chỉ được đặt bằng cách
 * bấm vào một con số ở trang Tổng quan. Không hiện ra thì người dùng thấy một
 * danh sách bị cắt bớt mà **không có gì giải thích vì sao**, và cách duy nhất
 * để thoát là sửa địa chỉ trang bằng tay.
 *
 * Mỗi chip nói rõ đang lọc gì và có nút gỡ ngay tại chỗ.
 */
const CHIP: { khoa: keyof TaskFilters; nhan: string }[] = [
  { khoa: "open", nhan: "Việc đang mở" },
  { khoa: "unassigned", nhan: "Chưa giao ai" },
  { khoa: "due_today", nhan: "Hạn hôm nay" },
  { khoa: "completed_this_week", nhan: "Xong tuần này" },
];

function LocDenTuTongQuan({
  filters,
  onChange,
}: {
  filters: TaskFilters;
  onChange: (patch: Partial<TaskFilters>) => void;
}) {
  const dangBat = CHIP.filter((c) => filters[c.khoa] === true);

  if (dangBat.length === 0) return null;

  return (
    <div className="flex flex-wrap items-center gap-2 sm:col-span-2 lg:col-span-4">
      {dangBat.map((c) => (
        <span
          key={c.khoa}
          className="border-tone-line bg-tone-surface text-tone-ink inline-flex items-center gap-1.5 rounded-full border py-1 pr-1 pl-3 text-[0.8rem] font-medium"
        >
          {c.nhan}
          <button
            type="button"
            onClick={() => onChange({ [c.khoa]: false, page: 1 })}
            aria-label={`Bỏ lọc ${c.nhan}`}
            className="focus-frame hover:bg-tone-line grid size-5 place-items-center rounded-full transition-colors"
          >
            <svg
              viewBox="0 0 24 24"
              aria-hidden="true"
              fill="none"
              stroke="currentColor"
              strokeWidth="2.5"
              strokeLinecap="round"
              className="size-3"
            >
              <path d="M6 6l12 12M18 6L6 18" />
            </svg>
          </button>
        </span>
      ))}
    </div>
  );
}
