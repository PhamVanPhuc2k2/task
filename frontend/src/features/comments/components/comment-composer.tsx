"use client";

import { useRef, useState } from "react";

import { Button } from "@/components/ui/button";
import { Avatar } from "@/components/ui/pill";

import { useCreateComment, useUploadAttachments } from "../api/comments-api";
import {
  ATTACHMENT_ACCEPT,
  ATTACHMENT_MAX_BYTES,
  ATTACHMENT_MAX_PER_REQUEST,
  formatBytes,
} from "../types/comment";
import { MentionTextarea } from "./mention-textarea";

/**
 * Ô viết bình luận, kèm chọn tệp đính kèm.
 *
 * Gửi làm hai bước: tạo bình luận trước, rồi tải tệp lên bình luận vừa tạo.
 * Nếu bước tải tệp hỏng thì bình luận vẫn còn — người dùng không mất công viết
 * lại, chỉ cần đính kèm lại.
 */
export function CommentComposer({
  taskId,
  parentId = null,
  authorName,
  placeholder = "Viết bình luận… gõ @ để nhắc tên đồng nghiệp",
  autoFocus,
  onDone,
}: {
  taskId: string;
  parentId?: string | null;
  authorName?: string;
  placeholder?: string;
  autoFocus?: boolean;
  onDone?: () => void;
}) {
  const viet = useCreateComment(taskId);
  const taiLen = useUploadAttachments(taskId);

  const [noiDung, setNoiDung] = useState("");
  const [tep, setTep] = useState<File[]>([]);
  const [loi, setLoi] = useState<string | null>(null);
  const inputRef = useRef<HTMLInputElement>(null);

  const dangGui = viet.isPending || taiLen.isPending;

  function chonTep(files: FileList | null) {
    if (files === null) return;

    const moi = [...tep, ...Array.from(files)];

    if (moi.length > ATTACHMENT_MAX_PER_REQUEST) {
      setLoi(
        `Mỗi bình luận chỉ đính kèm tối đa ${ATTACHMENT_MAX_PER_REQUEST} tệp.`,
      );
      return;
    }

    // Chặn ngay ở trình duyệt để người dùng không phải chờ tải lên 11 MB rồi
    // mới bị từ chối. Server vẫn kiểm lại — đây chỉ là phép lịch sự.
    const quaTo = moi.find((f) => f.size > ATTACHMENT_MAX_BYTES);
    if (quaTo) {
      setLoi(
        `"${quaTo.name}" nặng ${formatBytes(quaTo.size)}, vượt mức 10 MB.`,
      );
      return;
    }

    setLoi(null);
    setTep(moi);
  }

  async function gui() {
    if (noiDung.trim() === "") return;

    setLoi(null);

    try {
      const comment = await viet.mutateAsync({
        body: noiDung.trim(),
        parent_id: parentId,
      });

      if (tep.length > 0) {
        await taiLen.mutateAsync({ commentId: comment.id, files: tep });
      }

      setNoiDung("");
      setTep([]);
      if (inputRef.current) inputRef.current.value = "";
      onDone?.();
    } catch (err) {
      setLoi(err instanceof Error ? err.message : "Không gửi được bình luận.");
    }
  }

  return (
    <div className="flex gap-3">
      {authorName && <Avatar name={authorName} size="sm" />}

      <div className="min-w-0 flex-1">
        <MentionTextarea
          value={noiDung}
          onChange={setNoiDung}
          placeholder={placeholder}
          autoFocus={autoFocus}
          rows={parentId === null ? 3 : 2}
        />

        {tep.length > 0 && (
          <ul className="mt-2 space-y-1">
            {tep.map((f, i) => (
              <li
                key={`${f.name}-${i}`}
                className="text-ink-soft flex items-center gap-2 text-[0.82rem]"
              >
                <span className="truncate">{f.name}</span>
                <span className="text-ink-faint shrink-0 text-[0.75rem]">
                  {formatBytes(f.size)}
                </span>
                <button
                  type="button"
                  onClick={() => setTep(tep.filter((_, j) => j !== i))}
                  aria-label={`Bỏ tệp ${f.name}`}
                  className="text-ink-faint hover:text-danger focus-frame ml-auto shrink-0 rounded px-1"
                >
                  Bỏ
                </button>
              </li>
            ))}
          </ul>
        )}

        {loi && (
          <p role="alert" className="text-danger mt-2 text-[0.82rem]">
            {loi}
          </p>
        )}

        <div className="mt-2.5 flex flex-wrap items-center gap-2.5">
          <Button
            size="sm"
            variant="primary"
            loading={dangGui}
            disabled={noiDung.trim() === ""}
            onClick={() => void gui()}
          >
            {parentId === null ? "Gửi" : "Trả lời"}
          </Button>

          <label className="border-line bg-paper-raised hover:border-line-strong inline-flex h-8 cursor-pointer items-center rounded-lg border px-3 text-[0.82rem] font-medium transition-colors">
            Đính kèm
            <input
              ref={inputRef}
              type="file"
              multiple
              accept={ATTACHMENT_ACCEPT}
              onChange={(e) => chonTep(e.target.files)}
              className="sr-only"
            />
          </label>

          {onDone && (
            <Button size="sm" variant="ghost" onClick={onDone}>
              Huỷ
            </Button>
          )}
        </div>
      </div>
    </div>
  );
}
