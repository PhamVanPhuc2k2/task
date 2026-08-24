"use client";

import { Button } from "./button";

/**
 * Điều hướng trang.
 *
 * Chỉ có lùi/tiến chứ không vẽ dãy số trang: danh sách công việc người ta lọc
 * cho hẹp lại rồi đọc, chứ không nhảy tới "trang 27". Vẽ dãy số chỉ thêm chỗ
 * bấm nhầm trên điện thoại.
 */
export function Pagination({
  page,
  lastPage,
  total,
  from,
  to,
  onChange,
}: {
  page: number;
  lastPage: number;
  total: number;
  from: number | null;
  to: number | null;
  onChange: (page: number) => void;
}) {
  if (total === 0) return null;

  return (
    <div className="flex flex-wrap items-center justify-between gap-3 pt-1">
      <p className="text-ink-faint text-[0.82rem]" aria-live="polite">
        {from ?? 0}–{to ?? 0} trên {total}
      </p>

      <div className="flex gap-2">
        <Button
          size="sm"
          disabled={page <= 1}
          onClick={() => onChange(page - 1)}
        >
          Trước
        </Button>
        <Button
          size="sm"
          disabled={page >= lastPage}
          onClick={() => onChange(page + 1)}
        >
          Sau
        </Button>
      </div>
    </div>
  );
}
