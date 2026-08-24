"use client";

import { useState } from "react";

import { Button } from "@/components/ui/button";
import { Dialog } from "@/components/ui/dialog";

import {
  useActivateEmployee,
  useDeactivateEmployee,
  useResetEmployeePassword,
  useResetEmployeeTwoFactor,
} from "../api/employees-api";
import type { Employee } from "../types/employee";
import { EmployeeFormDialog } from "./employee-form-dialog";
import { TemporaryPassword } from "./temporary-password";

/**
 * Các thao tác quản trị trên một nhân viên.
 *
 * Đều có hậu quả thấy được ngay với người bị tác động, nên đều hỏi lại trước
 * khi làm — kể cả đặt lại mật khẩu, vì nó đá người đó ra khỏi mọi phiên đang
 * mở.
 *
 * Ngoại lệ là **kích hoạt lại**: hỏi lại trước khi mở cửa cho ai đó vào là
 * thừa, và bản thân nó đã là thao tác sửa sai cho một lần vô hiệu hoá nhầm.
 */
export function EmployeeActions({
  employee,
  laChinhMinh,
}: {
  employee: Employee;
  laChinhMinh: boolean;
}) {
  const voHieu = useDeactivateEmployee();
  const kichHoat = useActivateEmployee();
  const datLaiMK = useResetEmployeePassword();
  const go2fa = useResetEmployeeTwoFactor();

  const [dangMo, setDangMo] = useState<
    "edit" | "deactivate" | "password" | "two-factor" | null
  >(null);
  const [matKhauTam, setMatKhauTam] = useState<string | null>(null);

  function dong() {
    setDangMo(null);
    setMatKhauTam(null);
    datLaiMK.reset();
    voHieu.reset();
    go2fa.reset();
    kichHoat.reset();
  }

  return (
    <>
      <div className="flex flex-wrap gap-1.5">
        {employee.is_active ? (
          <>
            <Button size="sm" onClick={() => setDangMo("edit")}>
              Sửa hồ sơ
            </Button>

            <Button
              size="sm"
              variant="ghost"
              onClick={() => setDangMo("password")}
            >
              Đặt lại mật khẩu
            </Button>

            <Button
              size="sm"
              variant="ghost"
              onClick={() => setDangMo("two-factor")}
            >
              Gỡ 2FA
            </Button>

            {/* Không cho tự vô hiệu hoá chính mình — backend cũng chặn, nhưng
                ẩn nút đi thì người dùng không phải bấm rồi mới biết. */}
            {!laChinhMinh && (
              <Button
                size="sm"
                variant="ghost"
                className="text-danger"
                onClick={() => setDangMo("deactivate")}
              >
                Vô hiệu hoá
              </Button>
            )}
          </>
        ) : (
          <Button
            size="sm"
            loading={kichHoat.isPending}
            onClick={() => kichHoat.mutate(employee.id)}
          >
            Kích hoạt lại
          </Button>
        )}
      </div>

      {kichHoat.error && (
        <p role="alert" className="text-danger mt-1.5 text-[0.8rem]">
          {kichHoat.error.message}
        </p>
      )}

      {/* ── Sửa hồ sơ ──────────────────────────────── */}
      {/* `key` bắt buộc: form lấy giá trị ban đầu đúng một lần lúc gắn vào cây,
          không có key thì mở sửa người thứ hai vẫn hiện dữ liệu người thứ nhất. */}
      {dangMo === "edit" && (
        <EmployeeFormDialog
          key={employee.id}
          open
          onClose={dong}
          employee={employee}
        />
      )}

      {/* ── Đặt lại mật khẩu ───────────────────────── */}
      <Dialog
        open={dangMo === "password"}
        onClose={dong}
        title={
          matKhauTam
            ? "Đã đặt lại mật khẩu"
            : `Đặt lại mật khẩu cho ${employee.name}?`
        }
        description={
          matKhauTam
            ? undefined
            : "Mọi phiên đang mở của người này sẽ bị đăng xuất ngay. Họ cần mật khẩu mới để vào lại."
        }
      >
        {matKhauTam ? (
          <div className="space-y-5">
            <TemporaryPassword password={matKhauTam} hoTen={employee.name} />
            <div className="border-line border-t pt-4">
              <Button variant="primary" onClick={dong}>
                Xong
              </Button>
            </div>
          </div>
        ) : (
          <>
            {datLaiMK.error && (
              <p role="alert" className="text-danger mb-3 text-[0.85rem]">
                {datLaiMK.error.message}
              </p>
            )}

            <div className="flex gap-3">
              <Button
                variant="primary"
                loading={datLaiMK.isPending}
                onClick={() =>
                  datLaiMK.mutate(employee.id, {
                    onSuccess: (mk) => setMatKhauTam(mk),
                  })
                }
              >
                Đặt lại mật khẩu
              </Button>
              <Button variant="ghost" onClick={dong}>
                Huỷ
              </Button>
            </div>
          </>
        )}
      </Dialog>

      {/* ── Gỡ xác thực hai lớp ────────────────────── */}
      <Dialog
        open={dangMo === "two-factor"}
        onClose={dong}
        title={`Gỡ xác thực hai lớp của ${employee.name}?`}
        description="Dùng khi họ mất quyền truy cập hộp thư và hết mã khôi phục. Lần đăng nhập sau, hệ thống sẽ bắt họ thiết lập lại từ đầu."
      >
        {go2fa.error && (
          <p role="alert" className="text-danger mb-3 text-[0.85rem]">
            {go2fa.error.message}
          </p>
        )}

        <div className="flex gap-3">
          <Button
            variant="danger"
            loading={go2fa.isPending}
            onClick={() => go2fa.mutate(employee.id, { onSuccess: dong })}
          >
            Gỡ xác thực hai lớp
          </Button>
          <Button variant="ghost" onClick={dong}>
            Huỷ
          </Button>
        </div>
      </Dialog>

      {/* ── Vô hiệu hoá ────────────────────────────── */}
      <Dialog
        open={dangMo === "deactivate"}
        onClose={dong}
        title={`Vô hiệu hoá tài khoản ${employee.name}?`}
        description="Tài khoản mất quyền truy cập ngay lập tức, mọi phiên và token bị thu hồi. Công việc, bình luận và lịch sử làm việc của họ vẫn còn nguyên."
      >
        {voHieu.error && (
          <p role="alert" className="text-danger mb-3 text-[0.85rem]">
            {voHieu.error.message}
          </p>
        )}

        <div className="flex gap-3">
          <Button
            variant="danger"
            loading={voHieu.isPending}
            onClick={() => voHieu.mutate(employee.id, { onSuccess: dong })}
          >
            Vô hiệu hoá
          </Button>
          <Button variant="ghost" onClick={dong}>
            Giữ lại
          </Button>
        </div>
      </Dialog>
    </>
  );
}
