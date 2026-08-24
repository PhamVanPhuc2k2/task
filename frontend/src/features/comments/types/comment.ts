/** Khớp với App\Http\Resources\TaskCommentResource phía backend. */

export interface Attachment {
  id: string;
  /** Tên gốc người dùng đặt, dùng để hiển thị. Tên trên đĩa đã bị đổi. */
  name: string;
  file_name: string;
  mime_type: string | null;
  size: number;
  is_image: boolean;
  url: string;
  /** null khi ảnh xem trước chưa sinh xong ở hàng đợi nền. */
  thumb_url: string | null;
}

export interface Comment {
  id: string;
  /** Dạng thô, còn nguyên dấu nhắc `@[Tên](uuid)`. */
  body: string;
  created_at: string | null;
  edited_at: string | null;
  parent_id?: string | null;
  author?: { id: string; name: string; email: string } | null;
  mentions?: { id: string; name: string }[];
  attachments?: Attachment[];
  replies?: Comment[];
  reply_count?: number;
}

/** Dấu nhắc do ô soạn thảo chèn khi người dùng chọn trong danh sách gợi ý. */
export const MENTION_PATTERN =
  /@\[([^\]]{1,255})\]\(([0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12})\)/g;

export type BodyPart =
  | { kind: "text"; value: string }
  | { kind: "mention"; name: string; id: string };

/**
 * Cắt nội dung thô thành đoạn chữ và chip nhắc tên.
 *
 * Làm ở frontend chứ không để server trả về HTML dựng sẵn: server sinh HTML từ
 * nội dung người dùng là mở đúng một đường cho XSS lưu trữ. Ở đây mỗi phần trở
 * thành một node React, không bao giờ đi qua `dangerouslySetInnerHTML`.
 */
export function parseBody(body: string): BodyPart[] {
  const parts: BodyPart[] = [];
  let viTri = 0;

  // Regex có cờ `g` giữ trạng thái giữa các lần gọi — tạo bản mới mỗi lần để
  // hai bình luận liên tiếp không dùng chung con trỏ.
  const khuon = new RegExp(MENTION_PATTERN.source, "g");
  let khop: RegExpExecArray | null;

  while ((khop = khuon.exec(body)) !== null) {
    if (khop.index > viTri) {
      parts.push({ kind: "text", value: body.slice(viTri, khop.index) });
    }

    parts.push({ kind: "mention", name: khop[1] ?? "", id: khop[2] ?? "" });
    viTri = khop.index + khop[0].length;
  }

  if (viTri < body.length) {
    parts.push({ kind: "text", value: body.slice(viTri) });
  }

  return parts;
}

/** Dung lượng tệp về dạng đọc được. */
export function formatBytes(bytes: number): string {
  if (bytes < 1024) return `${bytes} B`;
  if (bytes < 1024 * 1024) return `${Math.round(bytes / 1024)} KB`;

  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

/**
 * Luật đính kèm — phải khớp `App\Domain\Task\Data\AttachmentRules`.
 *
 * Lệch nhau thì người dùng chọn được tệp rồi mới bị server từ chối, và họ đọc
 * thành lỗi hệ thống chứ không phải lỗi thao tác.
 */
export const ATTACHMENT_ACCEPT = [
  "image/jpeg",
  "image/png",
  "image/gif",
  "image/webp",
  "application/pdf",
  "application/msword",
  "application/vnd.openxmlformats-officedocument.wordprocessingml.document",
  "application/vnd.ms-excel",
  "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
  "text/plain",
  "text/csv",
  "application/zip",
].join(",");

export const ATTACHMENT_MAX_BYTES = 10 * 1024 * 1024;
export const ATTACHMENT_MAX_PER_REQUEST = 5;
