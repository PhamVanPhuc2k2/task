import { useQuery, type UseQueryResult } from "@tanstack/react-query";

import { api, type ApiError } from "@/lib/api-client";

/**
 * Danh bạ rút gọn để vẽ ô chọn người thực hiện / người duyệt.
 *
 * Khác `GET /users` (màn hình quản trị nhân sự, đòi quyền `user.manage`):
 * đường này ai đăng nhập cũng gọi được và chỉ trả về tên, email, phòng ban.
 */
export interface DirectoryPerson {
  id: string;
  name: string;
  email: string;
  employee_code: string | null;
  department: string | null;
}

/**
 * Danh bạ kèm thông tin **đã bị cắt bao nhiêu**.
 *
 * Backend trả tối đa 100 người mỗi lượt. Trước đây nó cắt im lặng: công ty quá
 * 100 người thì một số nhân viên không bao giờ xuất hiện trong ô chọn, và
 * người dùng chỉ thấy đồng nghiệp "không có trong danh sách" mà không hiểu vì
 * sao. Giờ `truncated` cho giao diện nói rõ và gợi ý gõ để tìm.
 */
export interface Directory {
  people: DirectoryPerson[];
  total: number;
  truncated: boolean;
}

export const directoryKeys = {
  assignable: (search: string) => ["users", "assignable", search] as const,
};

export function useAssignableUsers(
  search = "",
): UseQueryResult<Directory, ApiError> {
  return useQuery({
    queryKey: directoryKeys.assignable(search),
    queryFn: async () => {
      const kq = await api.get<{
        data: DirectoryPerson[];
        meta: { total: number; returned: number; truncated: boolean };
      }>("/users/assignable", { query: { search: search || undefined } });

      return {
        people: kq.data,
        total: kq.meta.total,
        truncated: kq.meta.truncated,
      };
    },
    // Danh bạ đổi rất chậm — giữ lâu để mở ô chọn nhiều lần không gọi lại.
    staleTime: 10 * 60_000,
  });
}
