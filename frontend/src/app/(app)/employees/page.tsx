"use client";

import { useState } from "react";

import { Button } from "@/components/ui/button";
import { IconPlus } from "@/components/ui/icon";
import { SelectInput, TextInput } from "@/components/ui/field";
import { Pagination } from "@/components/ui/pagination";
import { Avatar, Pill } from "@/components/ui/pill";
import { EmptyState, ErrorState, ListSkeleton } from "@/components/ui/states";
import { useCurrentUser } from "@/features/auth/api/auth-api";
import {
  useDepartments,
  useEmployees,
} from "@/features/users/api/employees-api";
import { EmployeeActions } from "@/features/users/components/employee-actions";
import { EmployeeFormDialog } from "@/features/users/components/employee-form-dialog";
import {
  ROLES,
  roleLabel,
  type Employee,
  type RoleValue,
} from "@/features/users/types/employee";
import { formatDate } from "@/lib/format";

/**
 * Quản trị nhân sự.
 *
 * Chỉ người có quyền `user.manage` mở được — mục điều hướng cũng ẩn với người
 * khác. Backend vẫn chặn độc lập; việc ẩn ở đây chỉ để họ không bấm vào rồi ăn
 * 403 mà không hiểu vì sao.
 */
export default function EmployeesPage() {
  const { data: user } = useCurrentUser();

  const [tuKhoa, setTuKhoa] = useState("");
  const [phong, setPhong] = useState("");
  const [vaiTro, setVaiTro] = useState<RoleValue | "">("");
  const [gomDaNghi, setGomDaNghi] = useState(false);
  const [trang, setTrang] = useState(1);
  const [dangThem, setDangThem] = useState(false);

  const { data: phongBan } = useDepartments();
  const { data, isPending, isError, error, refetch } = useEmployees({
    search: tuKhoa || undefined,
    department_id: phong || undefined,
    role: vaiTro || undefined,
    include_inactive: gomDaNghi,
    page: trang,
  });

  const duocQuanTri = user?.permissions.includes("user.manage") === true;
  const coLoc = Boolean(tuKhoa || phong || vaiTro || gomDaNghi);

  function doiLoc(dat: () => void) {
    dat();
    setTrang(1);
  }

  return (
    <div data-tone="people" className="enter space-y-6">
      <header className="flex flex-wrap items-end justify-between gap-4">
        <div>
          <span
            aria-hidden="true"
            className="bg-tone mb-3 block h-[3px] w-9 rounded-full"
          />
          <h1 className="text-[1.65rem] leading-tight font-semibold tracking-[-0.035em]">
            Nhân sự
          </h1>
          <p className="text-ink-soft mt-1.5 text-[0.9rem]">
            Tài khoản nhân viên, vai trò và quyền truy cập hệ thống.
          </p>
        </div>

        {duocQuanTri && (
          <Button variant="primary" onClick={() => setDangThem(true)}>
            <IconPlus className="size-4" />
            Thêm nhân viên
          </Button>
        )}
      </header>

      <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <div className="sm:col-span-2 lg:col-span-1">
          <label htmlFor="tim-nhan-su" className="sr-only">
            Tìm theo tên, email hoặc mã nhân viên
          </label>
          <TextInput
            id="tim-nhan-su"
            type="search"
            value={tuKhoa}
            placeholder="Tên, email, mã nhân viên…"
            onChange={(e) => doiLoc(() => setTuKhoa(e.target.value))}
          />
        </div>

        <div>
          <label htmlFor="loc-phong-ban" className="sr-only">
            Phòng ban
          </label>
          <SelectInput
            id="loc-phong-ban"
            value={phong}
            onChange={(e) => doiLoc(() => setPhong(e.target.value))}
          >
            <option value="">Mọi phòng ban</option>
            {phongBan?.map((p) => (
              <option key={p.id} value={p.id}>
                {p.parent_name ? `${p.parent_name} › ${p.name}` : p.name}
              </option>
            ))}
          </SelectInput>
        </div>

        <div>
          <label htmlFor="loc-vai-tro" className="sr-only">
            Vai trò
          </label>
          <SelectInput
            id="loc-vai-tro"
            value={vaiTro}
            onChange={(e) =>
              doiLoc(() => setVaiTro(e.target.value as RoleValue | ""))
            }
          >
            <option value="">Mọi vai trò</option>
            {ROLES.map((r) => (
              <option key={r.value} value={r.value}>
                {r.label}
              </option>
            ))}
          </SelectInput>
        </div>

        <label className="border-line bg-paper-raised hover:bg-paper-sunken flex cursor-pointer items-center gap-2.5 rounded-xl border px-3.5 py-2 text-[0.86rem] transition-colors">
          <input
            type="checkbox"
            className="accent-accent size-4 cursor-pointer"
            checked={gomDaNghi}
            onChange={(e) => doiLoc(() => setGomDaNghi(e.target.checked))}
          />
          Gồm cả người đã nghỉ
        </label>
      </div>

      {isPending && <ListSkeleton rows={6} />}

      {isError && <ErrorState error={error} onRetry={() => void refetch()} />}

      {data && data.data.length === 0 && (
        <EmptyState
          title={coLoc ? "Không có ai khớp" : "Chưa có nhân viên nào"}
          description={
            coLoc
              ? "Thử nới bộ lọc hoặc xoá từ khoá tìm kiếm."
              : "Thêm nhân viên để họ đăng nhập được vào hệ thống."
          }
        />
      )}

      {data && data.data.length > 0 && (
        <>
          <ul className="border-line divide-line bg-paper-raised shadow-card divide-y overflow-hidden rounded-2xl border">
            {data.data.map((nv) => (
              <li key={nv.id} className="p-4 sm:px-5">
                <Dong
                  nhanVien={nv}
                  duocQuanTri={duocQuanTri}
                  laChinhMinh={nv.id === user?.id}
                />
              </li>
            ))}
          </ul>

          <Pagination
            page={data.meta.current_page}
            lastPage={data.meta.last_page}
            total={data.meta.total}
            from={data.meta.from}
            to={data.meta.to}
            onChange={setTrang}
          />
        </>
      )}

      {/* Chỉ gắn vào cây khi mở, để lần mở sau bắt đầu bằng form trắng thay vì
          giữ lại những gì gõ dở lần trước. */}
      {dangThem && (
        <EmployeeFormDialog open onClose={() => setDangThem(false)} />
      )}
    </div>
  );
}

function Dong({
  nhanVien,
  duocQuanTri,
  laChinhMinh,
}: {
  nhanVien: Employee;
  duocQuanTri: boolean;
  laChinhMinh: boolean;
}) {
  return (
    <div className="flex flex-wrap items-start gap-4">
      <Avatar name={nhanVien.name} />

      <div className="min-w-0 flex-1">
        <p className="flex flex-wrap items-center gap-2">
          <span className="font-medium">{nhanVien.name}</span>

          {!nhanVien.is_active && (
            // `tone` chứ không `className`: màu của Pill phải đi qua đúng một
            // lớp, xem components/ui/pill.tsx.
            <Pill tone="border-danger-line bg-danger-surface text-danger">
              {nhanVien.terminated_at
                ? `Đã nghỉ từ ${formatDate(nhanVien.terminated_at)}`
                : "Đã nghỉ"}
            </Pill>
          )}

          {laChinhMinh && (
            <span className="text-ink-faint text-[0.74rem]">(bạn)</span>
          )}
        </p>

        <p className="text-ink-soft mt-0.5 text-[0.86rem]">{nhanVien.email}</p>

        <div className="text-ink-faint mt-1.5 flex flex-wrap gap-x-3 gap-y-1 text-[0.78rem]">
          {nhanVien.employee_code && <span>{nhanVien.employee_code}</span>}
          <span>
            {nhanVien.roles.map(roleLabel).join(", ") || "Chưa có vai trò"}
          </span>
          {nhanVien.department?.name && <span>{nhanVien.department.name}</span>}
          {nhanVien.position?.name && <span>{nhanVien.position.name}</span>}
          {nhanVien.joined_at && (
            <span>Vào làm {formatDate(nhanVien.joined_at)}</span>
          )}
        </div>
      </div>

      {duocQuanTri && (
        <div className="w-full sm:w-auto">
          <EmployeeActions employee={nhanVien} laChinhMinh={laChinhMinh} />
        </div>
      )}
    </div>
  );
}
