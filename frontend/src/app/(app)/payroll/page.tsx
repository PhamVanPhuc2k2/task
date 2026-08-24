"use client";

import { useState } from "react";

import { Button } from "@/components/ui/button";
import { SelectInput, TextInput } from "@/components/ui/field";
import { Avatar } from "@/components/ui/pill";
import { Pagination } from "@/components/ui/pagination";
import { EmptyState, ErrorState, ListSkeleton } from "@/components/ui/states";
import { useCurrentUser } from "@/features/auth/api/auth-api";
import { usePayroll } from "@/features/payroll/api/payroll-api";
import { SalaryDialog } from "@/features/payroll/components/salary-dialog";
import { formatMoney } from "@/features/payroll/types/payroll";
import { useDepartments } from "@/features/users/api/employees-api";
import { formatDate } from "@/lib/format";

/**
 * Bảng lương.
 *
 * **Tách hẳn khỏi màn quản trị nhân sự, có chủ ý.** Hộp thoại sửa nhân viên
 * dùng bởi người có `user.manage`, mà người đó chưa chắc có quyền xem lương;
 * nhét thêm tab "Lương" vào đó thì component phải ẩn/hiện trường theo quyền, và
 * đó đúng là cách rò rỉ xảy ra. Đường dẫn riêng nghĩa là guard riêng.
 *
 * Người không có `payroll.view.all` vào đây chỉ thấy lương của chính mình.
 */
export default function PayrollPage() {
  const { data: user } = useCurrentUser();

  const [tuKhoa, setTuKhoa] = useState("");
  const [phong, setPhong] = useState("");
  const [trang, setTrang] = useState(1);
  const [dangMo, setDangMo] = useState<{ id: string; name: string } | null>(
    null,
  );

  const xemTatCa = user?.permissions.includes("payroll.view.all") === true;
  const datDuoc = user?.permissions.includes("payroll.manage") === true;

  const { data, isPending, isError, error, refetch } = usePayroll(
    {
      search: tuKhoa || undefined,
      department_id: phong || undefined,
      page: trang,
    },
    xemTatCa,
  );

  const { data: phongBan } = useDepartments();

  return (
    <div data-tone="pay" className="enter space-y-6">
      <header className="flex flex-wrap items-end justify-between gap-4">
        <div>
          <span
            aria-hidden="true"
            className="bg-tone mb-3 block h-[3px] w-9 rounded-full"
          />
          <h1 className="text-[1.65rem] leading-tight font-semibold tracking-[-0.035em]">
            Lương
          </h1>
          <p className="text-ink-soft mt-1.5 text-[0.9rem]">
            {xemTatCa
              ? "Mức lương đang hiệu lực. Mọi lượt xem và điều chỉnh đều được ghi nhật ký."
              : "Mức lương và lịch sử điều chỉnh của bạn."}
          </p>
        </div>

        {user && (
          <Button onClick={() => setDangMo({ id: user.id, name: user.name })}>
            Lương của tôi
          </Button>
        )}
      </header>

      {/* Người không có quyền xem toàn công ty dừng ở đây — chỉ có nút "Lương
          của tôi" phía trên. Không gọi API bảng lương để khỏi ăn 403 vô ích. */}
      {user && !xemTatCa && (
        <EmptyState
          title="Bạn chỉ xem được lương của chính mình"
          description="Bấm “Lương của tôi” ở trên để xem mức hiện tại và lịch sử điều chỉnh."
        />
      )}

      {xemTatCa && (
        <>
          <div className="grid gap-3 sm:grid-cols-[1fr_14rem]">
            <div>
              <label htmlFor="tim-luong" className="sr-only">
                Tìm nhân viên
              </label>
              <TextInput
                id="tim-luong"
                type="search"
                value={tuKhoa}
                placeholder="Tìm theo tên hoặc mã nhân viên…"
                onChange={(e) => {
                  setTuKhoa(e.target.value);
                  setTrang(1);
                }}
              />
            </div>

            <div>
              <label htmlFor="loc-phong-luong" className="sr-only">
                Phòng ban
              </label>
              <SelectInput
                id="loc-phong-luong"
                value={phong}
                onChange={(e) => {
                  setPhong(e.target.value);
                  setTrang(1);
                }}
              >
                <option value="">Mọi phòng ban</option>
                {phongBan?.map((p) => (
                  <option key={p.id} value={p.id}>
                    {p.name}
                  </option>
                ))}
              </SelectInput>
            </div>
          </div>

          {isPending && <ListSkeleton rows={6} />}

          {isError && (
            <ErrorState error={error} onRetry={() => void refetch()} />
          )}

          {data && data.data.length === 0 && (
            <EmptyState
              title="Không có ai khớp"
              description="Thử nới bộ lọc hoặc xoá từ khoá tìm kiếm."
            />
          )}

          {data && data.data.length > 0 && (
            <>
              <ul className="border-line divide-line bg-paper-raised shadow-card divide-y overflow-hidden rounded-2xl border">
                {data.data.map((dong) => (
                  <li key={dong.user.id}>
                    <button
                      type="button"
                      onClick={() =>
                        setDangMo({ id: dong.user.id, name: dong.user.name })
                      }
                      className="focus-frame hover:bg-paper-sunken flex w-full items-center gap-4 px-4 py-3.5 text-left transition-colors sm:px-5"
                    >
                      <Avatar name={dong.user.name} />

                      <span className="min-w-0 flex-1">
                        <span className="block truncate font-medium">
                          {dong.user.name}
                        </span>
                        <span className="text-ink-faint block truncate text-[0.78rem]">
                          {[dong.user.employee_code, dong.user.department]
                            .filter(Boolean)
                            .join(" · ") || "—"}
                        </span>
                      </span>

                      <span className="shrink-0 text-right">
                        {dong.salary ? (
                          <>
                            <span className="block font-semibold tabular-nums">
                              {formatMoney(dong.salary.total)}
                            </span>
                            <span className="text-ink-faint block text-[0.74rem]">
                              từ {formatDate(dong.salary.effective_from)}
                            </span>
                          </>
                        ) : (
                          // Trạng thái hợp lệ, không phải lỗi — và là thứ người
                          // quản lý cần thấy để biết còn ai chưa có lương.
                          <span className="text-notice text-[0.82rem] font-medium">
                            Chưa đặt
                          </span>
                        )}
                      </span>
                    </button>
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
        </>
      )}

      {dangMo && (
        <SalaryDialog
          key={dangMo.id}
          open
          onClose={() => setDangMo(null)}
          userId={dangMo.id}
          userName={dangMo.name}
          // Không tự đặt lương cho chính mình — backend cũng chặn bằng Policy,
          // ẩn ô nhập ở đây để không phải bấm rồi mới biết.
          canManage={datDuoc && dangMo.id !== user?.id}
        />
      )}
    </div>
  );
}
