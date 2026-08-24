import type { ReactNode } from "react";

import { cn } from "@/lib/cn";

import { Button } from "./button";

/**
 * Ba trạng thái mà mọi màn hình đọc dữ liệu đều phải có: đang tải, rỗng, lỗi.
 *
 * Gom vào một chỗ để không màn hình nào "quên" mất một trạng thái rồi để lại
 * khoảng trắng không giải thích gì — thứ người dùng đọc thành "hỏng rồi".
 */

export function Skeleton({ className }: { className?: string }) {
  return (
    <div
      aria-hidden="true"
      className={cn("bg-line/70 animate-pulse rounded-xl", className)}
    />
  );
}

/** Khung xương cho danh sách. */
export function ListSkeleton({ rows = 5 }: { rows?: number }) {
  return (
    <div className="space-y-2.5" role="status" aria-label="Đang tải">
      {Array.from({ length: rows }, (_, i) => (
        <Skeleton key={i} className="h-18 w-full" />
      ))}
    </div>
  );
}

/**
 * Màn hình rỗng.
 *
 * Có dấu cộng mờ ở giữa — cùng motif thương hiệu, và quan trọng hơn: một ô
 * trống hoàn toàn đọc thành "hỏng", còn một ô trống có hình ở giữa đọc thành
 * "chưa có gì, thêm đi".
 */
export function EmptyState({
  title,
  description,
  action,
}: {
  title: string;
  description?: string;
  action?: ReactNode;
}) {
  return (
    <div className="border-line bg-paper-raised/60 rounded-2xl border border-dashed px-6 py-14 text-center">
      <span
        aria-hidden="true"
        className="border-line bg-paper-raised text-ink-faint mx-auto mb-4 flex size-11 items-center justify-center rounded-xl border"
      >
        <svg
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          strokeWidth="1.75"
          strokeLinecap="round"
          className="size-5"
        >
          <path d="M12 6v12M6 12h12" />
        </svg>
      </span>

      <p className="text-[0.98rem] font-medium">{title}</p>
      {description && (
        <p className="text-ink-faint mx-auto mt-1.5 max-w-sm text-[0.86rem] leading-relaxed">
          {description}
        </p>
      )}
      {action && <div className="mt-5">{action}</div>}
    </div>
  );
}

/**
 * Lỗi khi tải dữ liệu.
 *
 * Hiện đúng câu backend trả về — đã là tiếng Việt và nói rõ vấn đề. Không thay
 * bằng "Đã có lỗi xảy ra": câu đó không giúp người dùng làm được gì tiếp.
 *
 * Nhận `Error` chứ không nhận `ApiError`: `components/ui` không được import
 * `lib/api-client`. `ApiError` kế thừa `Error` nên vẫn truyền vào được.
 */
export function ErrorState({
  error,
  onRetry,
}: {
  error: Error | null;
  onRetry?: () => void;
}) {
  return (
    <div
      role="alert"
      className="border-danger-line bg-danger-surface rounded-2xl border px-6 py-10 text-center"
    >
      <p className="text-danger text-[0.95rem] font-medium">
        {error?.message ?? "Không tải được dữ liệu."}
      </p>

      {onRetry && (
        <Button size="sm" onClick={onRetry} className="mt-4">
          Thử lại
        </Button>
      )}
    </div>
  );
}
