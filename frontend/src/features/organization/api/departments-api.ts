import {
  useMutation,
  useQuery,
  useQueryClient,
  type UseMutationResult,
  type UseQueryResult,
} from "@tanstack/react-query";

import { api, type ApiError } from "@/lib/api-client";
import { employeeKeys } from "@/features/users/api/employees-api";
import type { Department } from "@/features/users/types/employee";
import type { Wrapped } from "@/lib/pagination";

/**
 * Khoá riêng cho danh sách ĐẦY ĐỦ (gồm cả phòng ban đã tắt).
 *
 * Không dùng chung `employeeKeys.departments` với ô chọn trong form nhân sự:
 * hai bên gọi cùng một đường nhưng khác tham số, nên dùng chung một khoá thì
 * bên nào chạy sau sẽ ghi đè cache của bên kia. Hậu quả là ô chọn phòng ban
 * trong form nhân sự bỗng liệt kê cả những phòng đã ngừng dùng — và ngược lại,
 * trang cơ cấu tổ chức mất luôn những phòng đó nên không bật lại được.
 */
export const departmentKeys = {
  full: ["organization", "departments", "full"] as const,
};

export function useAllDepartments(): UseQueryResult<Department[], ApiError> {
  return useQuery({
    queryKey: departmentKeys.full,
    queryFn: async () =>
      (
        await api.get<Wrapped<Department[]>>("/departments", {
          query: { include_inactive: 1 },
        })
      ).data,
  });
}

export interface DepartmentInput {
  name: string;
  code: string | null;
  description: string | null;
  parent_id: string | null;
  is_active: boolean;
}

/**
 * Làm mới CẢ HAI danh sách sau mỗi lần ghi.
 *
 * Quên `employeeKeys.departments` thì form nhân sự vẫn dùng bản cũ trong cache
 * suốt mười phút (`staleTime`): vừa thêm một phòng ban xong, mở form thêm nhân
 * viên ra không thấy nó đâu, và không có gì báo là đang xem dữ liệu cũ.
 */
function useLamMoi(): () => Promise<void> {
  const queryClient = useQueryClient();

  return async () => {
    await Promise.all([
      queryClient.invalidateQueries({ queryKey: departmentKeys.full }),
      queryClient.invalidateQueries({ queryKey: employeeKeys.departments }),
    ]);
  };
}

export function useCreateDepartment(): UseMutationResult<
  Wrapped<Department>,
  ApiError,
  DepartmentInput
> {
  const lamMoi = useLamMoi();

  return useMutation({
    mutationFn: (input) => api.post<Wrapped<Department>>("/departments", input),
    onSuccess: lamMoi,
  });
}

export function useUpdateDepartment(): UseMutationResult<
  Wrapped<Department>,
  ApiError,
  { id: string; input: DepartmentInput }
> {
  const lamMoi = useLamMoi();

  return useMutation({
    mutationFn: ({ id, input }) =>
      api.put<Wrapped<Department>>(`/departments/${id}`, input),
    onSuccess: lamMoi,
  });
}

export function useDeleteDepartment(): UseMutationResult<
  void,
  ApiError,
  string
> {
  const lamMoi = useLamMoi();

  return useMutation({
    mutationFn: (id) => api.delete<void>(`/departments/${id}`),
    onSuccess: lamMoi,
  });
}
