"use client";

import { useState } from "react";

import { Button } from "@/components/ui/button";
import { Pill } from "@/components/ui/pill";
import { EmptyState, ErrorState, ListSkeleton } from "@/components/ui/states";
import { useCurrentUser } from "@/features/auth/api/auth-api";
import {
  useAllDepartments,
  useDeleteDepartment,
} from "@/features/organization/api/departments-api";
import { DepartmentFormDialog } from "@/features/organization/components/department-form-dialog";
import { duoiCay } from "@/features/organization/types/tree";
import type { Department } from "@/features/users/types/employee";

/**
 * Cơ cấu tổ chức.
 *
 * ## Vì sao màn này tồn tại
 *
 * Trước đây cây phòng ban chỉ sửa được bằng cách sửa `OrganizationSeeder.php`
 * rồi deploy lại — cơ cấu tổ chức của công ty nằm trong mã nguồn, và mỗi lần
 * đổi tên một phòng là một lần đụng vào production.
 *
 * ## Vì sao nó nằm trong Cài đặt chứ không phải một mục điều hướng
 *
 * Cây phòng ban dựng một lần rồi vài tháng mới sửa. Một mục thường trực trên
 * thanh bên cho thứ mở mỗi quý sẽ đẩy những mục dùng hằng ngày xuống dưới.
 *
 * ## Vì sao hiện số nhân sự ngay trên từng hàng
 *
 * Xoá phòng ban còn người là chuyện backend chặn, nhưng biết TRƯỚC khi bấm thì
 * khác hẳn với biết SAU khi ăn thông báo lỗi — con số ngay trên hàng trả lời
 * luôn câu "chuyển ai đi đâu trước đã".
 */
export default function DepartmentsPage() {
  const { data: user } = useCurrentUser();

  const duocPhep = user?.permissions.includes("organization.manage") === true;

  const phongBan = useAllDepartments();
  const xoa = useDeleteDepartment();

  const [dangThem, setDangThem] = useState(false);
  const [dangSua, setDangSua] = useState<Department | null>(null);

  if (user && !duocPhep) {
    return (
      <div data-tone="people" className="enter">
        <EmptyState
          title="Bạn không có quyền vào màn này"
          description="Sửa cơ cấu phòng ban chỉ dành cho quản trị viên và giám đốc — cây phòng ban quyết định ai xem được dữ liệu của ai."
        />
      </div>
    );
  }

  const danhSach = phongBan.data ?? [];
  const hang = duoiCay(danhSach);

  return (
    <div data-tone="people" className="enter space-y-8">
      <header className="flex flex-wrap items-start justify-between gap-4">
        <div>
          <span
            aria-hidden="true"
            className="bg-tone mb-3 block h-[3px] w-9 rounded-full"
          />
          <h1 className="text-[1.65rem] leading-tight font-semibold tracking-[-0.035em]">
            Cơ cấu tổ chức
          </h1>
          <p className="text-ink-soft mt-1.5 max-w-2xl text-[0.9rem]">
            Cây phòng ban quyết định phạm vi nhìn của người quản lý: trưởng
            phòng xem được phòng mình và mọi phòng trực thuộc bên dưới.
          </p>
        </div>

        <Button variant="primary" onClick={() => setDangThem(true)}>
          Thêm phòng ban
        </Button>
      </header>

      {xoa.isError && (
        <p
          role="alert"
          className="border-danger-line bg-danger-surface text-danger rounded-xl border px-4 py-3 text-[0.87rem] leading-relaxed"
        >
          {xoa.error.message}
        </p>
      )}

      {phongBan.isPending && <ListSkeleton rows={5} />}

      {phongBan.isError && (
        <ErrorState
          error={phongBan.error}
          onRetry={() => void phongBan.refetch()}
        />
      )}

      {phongBan.data && hang.length === 0 && (
        <EmptyState
          title="Chưa có phòng ban nào"
          description="Thêm phòng ban đầu tiên để bắt đầu xếp nhân sự và phân phạm vi quản lý."
        />
      )}

      {hang.length > 0 && (
        <ul className="space-y-2">
          {hang.map(({ phongBan: p, cap }) => (
            <li
              key={p.id}
              className="tone-card flex flex-wrap items-center gap-x-4 gap-y-2 rounded-2xl px-4 py-3"
              // Thụt lề theo cấp. Giới hạn ở cấp 5 để cây sâu bất thường không
              // đẩy nội dung tràn ra khỏi màn hình hẹp.
              style={{ marginLeft: `${Math.min(cap, 5) * 1.5}rem` }}
            >
              <div className="min-w-0 flex-1">
                <div className="flex flex-wrap items-center gap-2">
                  <span className="font-medium">{p.name}</span>

                  {p.code && (
                    <span className="text-ink-faint font-mono text-[0.78rem]">
                      {p.code}
                    </span>
                  )}

                  {!p.is_active && (
                    <Pill tone="border-line bg-paper-sunken text-ink-faint">
                      Đã ngừng
                    </Pill>
                  )}
                </div>

                {p.description && (
                  <p className="text-ink-faint mt-1 text-[0.82rem] leading-relaxed">
                    {p.description}
                  </p>
                )}
              </div>

              <div className="text-ink-soft flex items-center gap-4 text-[0.82rem]">
                <span>
                  {p.user_count} nhân sự
                  {p.child_count > 0 && ` · ${p.child_count} phòng con`}
                </span>

                <Button variant="ghost" onClick={() => setDangSua(p)}>
                  Sửa
                </Button>

                {/* Chỉ hiện nút Xoá khi thật sự xoá được.
                    Bày một nút chắc chắn báo lỗi là mời người ta bấm vào để
                    biết mình không được bấm. Backend vẫn chặn độc lập — đây
                    chỉ là phép lịch sự với người dùng. */}
                {p.child_count === 0 && p.user_count === 0 && (
                  <Button
                    variant="ghost"
                    disabled={xoa.isPending}
                    onClick={() => {
                      if (
                        window.confirm(
                          `Xoá phòng ban "${p.name}"? Dữ liệu cũ vẫn tra ngược được, nhưng phòng ban sẽ biến mất khỏi mọi ô chọn.`,
                        )
                      ) {
                        xoa.mutate(p.id);
                      }
                    }}
                  >
                    Xoá
                  </Button>
                )}
              </div>
            </li>
          ))}
        </ul>
      )}

      {/* Chỉ gắn vào cây khi mở, để lần mở sau bắt đầu bằng form trắng thay vì
          giữ lại thứ đã gõ dở lần trước. */}
      {dangThem && (
        <DepartmentFormDialog
          open
          onClose={() => setDangThem(false)}
          tatCa={danhSach}
        />
      )}

      {dangSua && (
        // `key` bắt buộc: không có nó thì mở sửa phòng A rồi mở sửa phòng B sẽ
        // hiện lại dữ liệu của A và lưu đè lên B.
        <DepartmentFormDialog
          key={dangSua.id}
          open
          onClose={() => setDangSua(null)}
          department={dangSua}
          tatCa={danhSach}
        />
      )}
    </div>
  );
}
