import type { Directory } from "../api/directory-api";

/**
 * Nói rõ khi danh bạ bị cắt bớt.
 *
 * Backend trả tối đa 100 người mỗi lượt. Trước đây nó cắt im lặng: công ty quá
 * 100 người thì một số nhân viên không bao giờ xuất hiện trong ô chọn, và người
 * dùng chỉ thấy đồng nghiệp "không có trong danh sách" mà không hiểu vì sao —
 * rồi kết luận hệ thống hỏng.
 *
 * Không hiện gì khi danh sách đủ: một dòng chú thích luôn xuất hiện là một dòng
 * người ta thôi đọc sau ba ngày.
 */
export function DirectoryHint({ directory }: { directory?: Directory }) {
  if (!directory?.truncated) return null;

  return (
    <p className="text-notice mt-1.5 text-[0.78rem]">
      Đang hiện {directory.people.length} trên {directory.total} người. Gõ tên
      để tìm người không có trong danh sách.
    </p>
  );
}
