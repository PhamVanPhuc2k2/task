/** Khớp với App\Http\Resources\UserResource phía backend. */
export interface AuthUser {
  id: string;
  name: string;
  email: string;
  employee_code: string | null;
  phone: string | null;
  is_active: boolean;
  joined_at: string | null;
  /** Ngày nghỉ việc. Có giá trị khi `is_active` là false. */
  terminated_at: string | null;
  department?: { id: string; name: string; code: string | null } | null;
  position?: { id: string; name: string; level: number } | null;
  /** Quản lý trực tiếp. Chỉ có id và tên — không lồng cả hồ sơ. */
  manager?: { id: string; name: string } | null;
  roles: string[];
  permissions: string[];
}

export interface AuthUserResponse {
  data: AuthUser;
}
