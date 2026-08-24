"use client";

import { useState } from "react";

import { Button } from "@/components/ui/button";
import { Avatar } from "@/components/ui/pill";
import { EmptyState, ErrorState, ListSkeleton } from "@/components/ui/states";
import { useCurrentUser } from "@/features/auth/api/auth-api";
import { cn } from "@/lib/cn";
import { formatDateTime, formatTimeAgo } from "@/lib/format";

import {
  useComments,
  useDeleteAttachment,
  useDeleteComment,
  useUpdateComment,
} from "../api/comments-api";
import { parseBody, type Comment } from "../types/comment";
import { AttachmentList } from "./attachment-list";
import { CommentComposer } from "./comment-composer";
import { MentionTextarea } from "./mention-textarea";

/**
 * Khu vực trao đổi của một task.
 *
 * Xếp cũ → mới: người ta đọc một cuộc trao đổi theo thứ tự nó diễn ra. Trả lời
 * chỉ lồng một cấp — sâu hơn thì trên điện thoại thụt lề tới mức không đọc nổi.
 */
export function CommentSection({ taskId }: { taskId: string }) {
  const { data: user } = useCurrentUser();
  const { data, isPending, isError, error, refetch } = useComments(taskId);

  return (
    <div className="space-y-6">
      {isPending && <ListSkeleton rows={3} />}

      {isError && <ErrorState error={error} onRetry={() => void refetch()} />}

      {data && data.length === 0 && (
        <EmptyState
          title="Chưa có trao đổi nào"
          description="Viết bình luận đầu tiên để bắt đầu."
        />
      )}

      {data && data.length > 0 && (
        <ul className="space-y-6">
          {data.map((comment) => (
            <li key={comment.id}>
              <CommentItem
                taskId={taskId}
                comment={comment}
                currentUserId={user?.id}
              />
            </li>
          ))}
        </ul>
      )}

      <div className="border-line border-t pt-5">
        <CommentComposer taskId={taskId} authorName={user?.name} />
      </div>
    </div>
  );
}

function CommentItem({
  taskId,
  comment,
  currentUserId,
  laTraLoi = false,
}: {
  taskId: string;
  comment: Comment;
  currentUserId?: string;
  laTraLoi?: boolean;
}) {
  const sua = useUpdateComment(taskId);
  const xoa = useDeleteComment(taskId);
  const goTep = useDeleteAttachment(taskId);

  const [dangSua, setDangSua] = useState(false);
  const [dangTraLoi, setDangTraLoi] = useState(false);
  const [ban, setBan] = useState(comment.body);

  const laCuaToi = comment.author?.id === currentUserId;

  return (
    <div className={cn(laTraLoi && "border-line ml-4 border-l pl-4 sm:ml-6")}>
      <div className="flex gap-3">
        <Avatar name={comment.author?.name ?? "Không rõ"} size="sm" />

        <div className="min-w-0 flex-1">
          <p className="flex flex-wrap items-baseline gap-x-2 text-[0.86rem]">
            <span className="font-medium">
              {comment.author?.name ?? "Người dùng đã xoá"}
            </span>
            <span
              className="text-ink-faint text-[0.76rem]"
              title={formatDateTime(comment.created_at)}
            >
              {formatTimeAgo(comment.created_at)}
            </span>
            {/* Hiện công khai: một dòng trao đổi sửa được trong im lặng thì
                không dùng làm bằng chứng được nữa. */}
            {comment.edited_at && (
              <span
                className="text-ink-faint text-[0.74rem] italic"
                title={formatDateTime(comment.edited_at)}
              >
                đã sửa
              </span>
            )}
          </p>

          {dangSua ? (
            <div className="mt-2 space-y-2.5">
              <MentionTextarea value={ban} onChange={setBan} rows={3} />

              {sua.error && (
                <p role="alert" className="text-danger text-[0.82rem]">
                  {sua.error.message}
                </p>
              )}

              <div className="flex gap-2">
                <Button
                  size="sm"
                  variant="primary"
                  loading={sua.isPending}
                  onClick={() =>
                    sua.mutate(
                      { id: comment.id, body: ban },
                      { onSuccess: () => setDangSua(false) },
                    )
                  }
                >
                  Lưu
                </Button>
                <Button
                  size="sm"
                  variant="ghost"
                  onClick={() => {
                    setBan(comment.body);
                    setDangSua(false);
                  }}
                >
                  Huỷ
                </Button>
              </div>
            </div>
          ) : (
            <NoiDung body={comment.body} />
          )}

          <AttachmentList
            attachments={comment.attachments ?? []}
            onRemove={
              laCuaToi
                ? (mediaId) => goTep.mutate({ commentId: comment.id, mediaId })
                : undefined
            }
          />

          {!dangSua && (
            <div className="mt-2 flex flex-wrap gap-3 text-[0.78rem]">
              {!laTraLoi && (
                <button
                  type="button"
                  onClick={() => setDangTraLoi((v) => !v)}
                  className="text-ink-faint hover:text-ink focus-frame rounded"
                >
                  Trả lời
                </button>
              )}

              {laCuaToi && (
                <button
                  type="button"
                  onClick={() => setDangSua(true)}
                  className="text-ink-faint hover:text-ink focus-frame rounded"
                >
                  Sửa
                </button>
              )}

              <button
                type="button"
                onClick={() => xoa.mutate(comment.id)}
                className="text-ink-faint hover:text-danger focus-frame rounded"
              >
                Xoá
              </button>

              {xoa.error && (
                <span role="alert" className="text-danger">
                  {xoa.error.message}
                </span>
              )}
            </div>
          )}

          {dangTraLoi && (
            <div className="mt-3">
              <CommentComposer
                taskId={taskId}
                parentId={comment.id}
                autoFocus
                placeholder="Viết câu trả lời…"
                onDone={() => setDangTraLoi(false)}
              />
            </div>
          )}
        </div>
      </div>

      {comment.replies && comment.replies.length > 0 && (
        <ul className="mt-4 space-y-4">
          {comment.replies.map((traLoi) => (
            <li key={traLoi.id}>
              <CommentItem
                taskId={taskId}
                comment={traLoi}
                currentUserId={currentUserId}
                laTraLoi
              />
            </li>
          ))}
        </ul>
      )}
    </div>
  );
}

/**
 * Nội dung bình luận: chữ thường và chip nhắc tên.
 *
 * Dựng bằng node React chứ không bao giờ qua `dangerouslySetInnerHTML` — nội
 * dung do người dùng viết mà đổ thẳng thành HTML là XSS lưu trữ.
 */
function NoiDung({ body }: { body: string }) {
  return (
    <p className="mt-1.5 leading-relaxed whitespace-pre-wrap">
      {parseBody(body).map((phan, i) =>
        phan.kind === "text" ? (
          <span key={i}>{phan.value}</span>
        ) : (
          <span
            key={i}
            className="bg-accent/15 text-ink rounded px-1 py-0.5 font-medium"
          >
            @{phan.name}
          </span>
        ),
      )}
    </p>
  );
}
