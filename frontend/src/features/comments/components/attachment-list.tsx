"use client";

import { formatBytes, type Attachment } from "../types/comment";

/**
 * Danh sách tệp đính kèm của một bình luận.
 *
 * Ảnh hiện thumbnail, còn lại hiện thẻ tên tệp. Thumbnail sinh ở hàng đợi nền
 * nên vừa tải lên xong có thể chưa có — lúc đó dùng tạm ảnh gốc thay vì để một
 * ô trống, ảnh gốc chỉ nặng hơn chứ không sai.
 */
export function AttachmentList({
  attachments,
  onRemove,
}: {
  attachments: Attachment[];
  onRemove?: (mediaId: string) => void;
}) {
  if (attachments.length === 0) return null;

  return (
    <ul className="mt-2.5 flex flex-wrap gap-2">
      {attachments.map((tep) => (
        <li key={tep.id} className="relative">
          <a
            href={tep.url}
            target="_blank"
            rel="noopener noreferrer"
            title={`${tep.name} — ${formatBytes(tep.size)}`}
            className="focus-frame block rounded-lg"
          >
            {tep.is_image ? (
              // eslint-disable-next-line @next/next/no-img-element
              <img
                src={tep.thumb_url ?? tep.url}
                alt={tep.name}
                loading="lazy"
                className="border-line size-20 rounded-lg border object-cover"
              />
            ) : (
              <span className="border-line bg-paper-raised hover:border-line-strong flex max-w-[14rem] items-center gap-2 rounded-lg border px-3 py-2 text-[0.82rem] transition-colors">
                <FileIcon />
                <span className="min-w-0">
                  <span className="block truncate">{tep.name}</span>
                  <span className="text-ink-faint text-[0.72rem]">
                    {formatBytes(tep.size)}
                  </span>
                </span>
              </span>
            )}
          </a>

          {onRemove && (
            <button
              type="button"
              onClick={() => onRemove(tep.id)}
              aria-label={`Gỡ tệp ${tep.name}`}
              className="border-line bg-paper-raised text-ink-faint hover:text-danger shadow-card focus-frame absolute -top-1.5 -right-1.5 flex size-5 items-center justify-center rounded-full border text-[0.7rem] leading-none"
            >
              ×
            </button>
          )}
        </li>
      ))}
    </ul>
  );
}

function FileIcon() {
  return (
    <svg
      viewBox="0 0 24 24"
      aria-hidden="true"
      className="text-ink-faint size-4 shrink-0"
      fill="none"
      stroke="currentColor"
      strokeWidth="1.75"
      strokeLinecap="round"
      strokeLinejoin="round"
    >
      <path d="M14 3v5h5M14 3H6a1 1 0 0 0-1 1v16a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V8l-5-5Z" />
    </svg>
  );
}
