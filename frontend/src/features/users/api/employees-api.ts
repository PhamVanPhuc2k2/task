import {
  useMutation,
  useQuery,
  useQueryClient,
  type UseMutationResult,
  type UseQueryResult,
} from "@tanstack/react-query";

import { api, type ApiError } from "@/lib/api-client";
import type { Paginated, Wrapped } from "@/lib/pagination";

import type {
  CreateEmployeeResult,
  Department,
  Employee,
  EmployeeFilters,
  Position,
  RoleValue,
  UpdateEmployeeResult,
} from "../types/employee";

export const employeeKeys = {
  all: ["employees"] as const,
  list: (filters: EmployeeFilters) => ["employees", "list", filters] as const,
  departments: ["organization", "departments"] as const,
  positions: ["organization", "positions"] as const,
};

export function useEmployees(
  filters: EmployeeFilters,
): UseQueryResult<Paginated<Employee>, ApiError> {
  return useQuery({
    queryKey: employeeKeys.list(filters),
    queryFn: () =>
      api.get<Paginated<Employee>>("/users", {
        query: {
          ...filters,
          include_inactive: filters.include_inactive ? 1 : undefined,
        },
      }),
    placeholderData: (previous) => previous,
  });
}

/** Cơ cấu tổ chức đổi rất chậm — giữ lâu để mở form nhiều lần không gọi lại. */
export function useDepartments(): UseQueryResult<Department[], ApiError> {
  return useQuery({
    queryKey: employeeKeys.departments,
    queryFn: async () =>
      (await api.get<Wrapped<Department[]>>("/departments")).data,
    staleTime: 10 * 60_000,
  });
}

export function usePositions(): UseQueryResult<Position[], ApiError> {
  return useQuery({
    queryKey: employeeKeys.positions,
    queryFn: async () =>
      (await api.get<Wrapped<Position[]>>("/positions")).data,
    staleTime: 10 * 60_000,
  });
}

export interface CreateEmployeeInput {
  name: string;
  email: string;
  employee_code: string;
  role: RoleValue;
  phone?: string | null;
  department_id?: string | null;
  position_id?: string | null;
  manager_id?: string | null;
  joined_at?: string | null;
}

/**
 * Tạo nhân viên.
 *
 * Trả về NGUYÊN phản hồi chứ không chỉ `data`: mật khẩu tạm nằm ở `meta` và
 * chỉ xuất hiện đúng lần này.
 */
export function useCreateEmployee(): UseMutationResult<
  CreateEmployeeResult,
  ApiError,
  CreateEmployeeInput
> {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (input) => api.post<CreateEmployeeResult>("/users", input),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: employeeKeys.all });
    },
  });
}

/**
 * Sửa hồ sơ nhân viên.
 *
 * `PUT` chứ không `PATCH`, và form gửi đủ mọi trường mỗi lần lưu — backend
 * hiểu `null` là "bỏ trống", không phải "giữ nguyên". Xem
 * App\Domain\Identity\Data\UpdateUserData.
 */
export function useUpdateEmployee(): UseMutationResult<
  UpdateEmployeeResult,
  ApiError,
  { id: string; input: CreateEmployeeInput }
> {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: ({ id, input }) =>
      api.put<UpdateEmployeeResult>(`/users/${id}`, input),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: employeeKeys.all });
    },
  });
}

export function useDeactivateEmployee(): UseMutationResult<
  void,
  ApiError,
  string
> {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (id) => api.post<void>(`/users/${id}/deactivate`),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: employeeKeys.all });
    },
  });
}

/** Mở lại tài khoản đã vô hiệu hoá. Đường ngược của `useDeactivateEmployee`. */
export function useActivateEmployee(): UseMutationResult<
  void,
  ApiError,
  string
> {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (id) => api.post<void>(`/users/${id}/activate`),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: employeeKeys.all });
    },
  });
}

/** Trả về mật khẩu tạm, hiện đúng một lần. */
export function useResetEmployeePassword(): UseMutationResult<
  string,
  ApiError,
  string
> {
  return useMutation({
    mutationFn: async (id) =>
      (
        await api.post<Wrapped<{ temporary_password: string }>>(
          `/users/${id}/reset-password`,
        )
      ).data.temporary_password,
  });
}

export function useResetEmployeeTwoFactor(): UseMutationResult<
  unknown,
  ApiError,
  string
> {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (id) => api.post<unknown>(`/users/${id}/reset-two-factor`),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: employeeKeys.all });
    },
  });
}
