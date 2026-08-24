"use client";

import { useState } from "react";

import { Button } from "@/components/ui/button";
import { Dialog } from "@/components/ui/dialog";
import { Field, SelectInput, TextInput } from "@/components/ui/field";
import { useCurrentUser } from "@/features/auth/api/auth-api";
import { useAssignableUsers } from "@/features/users/api/directory-api";
import type { ApiError } from "@/lib/api-client";

import {
  useCreateEmployee,
  useDepartments,
  usePositions,
  useUpdateEmployee,
} from "../api/employees-api";
import { ROLES, type Employee, type RoleValue } from "../types/employee";
import { TemporaryPassword } from "./temporary-password";

/**
 * Thêm nhân viên, hoặc sửa hồ sơ nhân viên đã có.
 *
 * Một hộp thoại cho cả hai việc chứ không phải hai: các trường giống hệt nhau,
 * và tách ra thì mỗi lần thêm một trường vào hồ sơ lại phải nhớ sửa hai chỗ —
 * kiểu thiếu sót không ai nhìn thấy cho tới khi có người hỏi "sao sửa không
 * đổi được số điện thoại".
 *
 * Có `employee` là chế độ sửa, không có là chế độ thêm.
 *
 * **Chỗ gọi phải truyền `key` khác nhau cho từng nhân viên.** Giá trị ban đầu
 * của các ô nhập lấy từ `employee` đúng một lần lúc component gắn vào cây; mở
 * sửa người A rồi đóng, mở sửa người B mà không thay `key` thì form vẫn hiện dữ
 * liệu của người A và lưu đè lên người B.
 *
 * Ở chế độ thêm, hộp thoại có hai giai đoạn: nhập thông tin, rồi hiện mật khẩu
 * tạm. Không tách thành hai màn vì mật khẩu tạm chỉ tồn tại một lần — chuyển
 * trang giữa chừng là mất, đúng lúc người dùng đang bối rối nhất.
 */
export function EmployeeFormDialog({
  open,
  onClose,
  employee,
}: {
  open: boolean;
  onClose: () => void;
  /** Có = sửa người này. Không có = thêm người mới. */
  employee?: Employee;
}) {
  const dangSua = employee !== undefined;

  const tao = useCreateEmployee();
  const sua = useUpdateEmployee();
  const { data: nguoiDangDangNhap } = useCurrentUser();
  const { data: phongBan } = useDepartments();
  const { data: chucVu } = usePositions();
  const { data: danhBa } = useAssignableUsers();
  const nguoi = danhBa?.people;

  const [hoTen, setHoTen] = useState(employee?.name ?? "");
  const [email, setEmail] = useState(employee?.email ?? "");
  const [maNV, setMaNV] = useState(employee?.employee_code ?? "");
  const [vaiTro, setVaiTro] = useState<RoleValue>(
    (employee?.roles[0] as RoleValue | undefined) ?? "nhan_vien",
  );
  const [dienThoai, setDienThoai] = useState(employee?.phone ?? "");
  const [phong, setPhong] = useState(employee?.department?.id ?? "");
  const [chuc, setChuc] = useState(employee?.position?.id ?? "");
  const [quanLy, setQuanLy] = useState(employee?.manager?.id ?? "");
  const [ngayVao, setNgayVao] = useState(employee?.joined_at ?? "");

  /** Có giá trị nghĩa là đã tạo xong, đang ở bước hiện mật khẩu. */
  const [ketQua, setKetQua] = useState<{ mk: string; ten: string } | null>(
    null,
  );

  /** Cảnh báo sau khi lưu — thao tác đã thành công, đây không phải lỗi. */
  const [canhBao, setCanhBao] = useState<string[] | null>(null);

  const loi = (dangSua ? sua.error : tao.error) as ApiError | null;
  const dangGui = dangSua ? sua.isPending : tao.isPending;

  // Backend chặn tự đổi vai trò của chính mình (quản trị viên cuối cùng tự hạ
  // vai trò là khoá cả công ty ra ngoài). Khoá luôn ô chọn ở đây để người dùng
  // biết trước, thay vì bấm Lưu rồi mới ăn lỗi.
  const tuSua = dangSua && nguoiDangDangNhap?.id === employee.id;

  function dong() {
    setKetQua(null);
    setCanhBao(null);
    tao.reset();
    sua.reset();
    onClose();
  }

  function lamLai() {
    setHoTen("");
    setEmail("");
    setMaNV("");
    setVaiTro("nhan_vien");
    setDienThoai("");
    setPhong("");
    setChuc("");
    setQuanLy("");
    setNgayVao("");
    setKetQua(null);
    tao.reset();
  }

  function gui() {
    const rong = (v: string) => (v.trim() === "" ? null : v.trim());

    const duLieu = {
      name: hoTen.trim(),
      email: email.trim(),
      employee_code: maNV.trim(),
      role: vaiTro,
      phone: rong(dienThoai),
      department_id: rong(phong),
      position_id: rong(chuc),
      manager_id: rong(quanLy),
      joined_at: rong(ngayVao),
    };

    if (dangSua) {
      sua.mutate(
        { id: employee.id, input: duLieu },
        {
          onSuccess: (kq) => {
            // Không có cảnh báo thì đóng luôn — bắt bấm thêm một nút "Xong" chỉ
            // để xác nhận điều đã thấy là phiền.
            if (kq.meta.warnings.length === 0) dong();
            else setCanhBao(kq.meta.warnings);
          },
        },
      );

      return;
    }

    tao.mutate(duLieu, {
      onSuccess: (kq) =>
        setKetQua({ mk: kq.meta.temporary_password, ten: kq.data.name }),
    });
  }

  function tieuDe() {
    if (ketQua) return "Đã tạo tài khoản";
    if (canhBao) return "Đã lưu, có vài điều cần biết";

    return dangSua ? `Sửa hồ sơ ${employee.name}` : "Thêm nhân viên";
  }

  return (
    <Dialog
      open={open}
      onClose={dong}
      title={tieuDe()}
      description={
        ketQua || canhBao || dangSua
          ? undefined
          : "Hệ thống sinh mật khẩu tạm cho tài khoản mới và hiện đúng một lần sau khi tạo."
      }
    >
      {ketQua ? (
        <div className="space-y-5">
          <TemporaryPassword password={ketQua.mk} hoTen={ketQua.ten} />

          <div className="border-line flex gap-3 border-t pt-4">
            <Button variant="primary" onClick={dong}>
              Xong
            </Button>
            <Button variant="ghost" onClick={lamLai}>
              Thêm người nữa
            </Button>
          </div>
        </div>
      ) : canhBao ? (
        <div className="space-y-5">
          <ul className="space-y-2.5">
            {canhBao.map((cau) => (
              <li
                key={cau}
                className="border-notice-line bg-notice-surface text-notice rounded-xl border px-4 py-3 text-[0.86rem] leading-relaxed"
              >
                {cau}
              </li>
            ))}
          </ul>

          <div className="border-line border-t pt-4">
            <Button variant="primary" onClick={dong}>
              Đã hiểu
            </Button>
          </div>
        </div>
      ) : (
        <div className="space-y-4">
          <Field label="Họ và tên" required error={loi?.fieldError("name")}>
            {(id) => (
              <TextInput
                id={id}
                value={hoTen}
                autoFocus
                onChange={(e) => setHoTen(e.target.value)}
                placeholder="Nguyễn Thị Mai"
              />
            )}
          </Field>

          <div className="grid gap-4 sm:grid-cols-2">
            <Field
              label="Email công ty"
              required
              error={loi?.fieldError("email")}
              hint="Mã OTP đăng nhập sẽ gửi vào địa chỉ này."
            >
              {(id, describedBy) => (
                <TextInput
                  id={id}
                  type="email"
                  aria-describedby={describedBy}
                  value={email}
                  onChange={(e) => setEmail(e.target.value)}
                  placeholder="mai.nguyen@congty.vn"
                />
              )}
            </Field>

            <Field
              label="Mã nhân viên"
              required
              error={loi?.fieldError("employee_code")}
            >
              {(id) => (
                <TextInput
                  id={id}
                  value={maNV}
                  onChange={(e) => setMaNV(e.target.value)}
                  placeholder="NV2026001"
                />
              )}
            </Field>
          </div>

          <Field
            label="Vai trò trong hệ thống"
            required
            error={loi?.fieldError("role")}
            hint={
              tuSua
                ? "Không tự đổi vai trò của chính mình được. Nhờ một quản trị viên khác thực hiện."
                : ROLES.find((r) => r.value === vaiTro)?.description
            }
          >
            {(id, describedBy) => (
              <SelectInput
                id={id}
                aria-describedby={describedBy}
                value={vaiTro}
                disabled={tuSua}
                onChange={(e) => setVaiTro(e.target.value as RoleValue)}
              >
                {ROLES.map((r) => (
                  <option key={r.value} value={r.value}>
                    {r.label}
                  </option>
                ))}
              </SelectInput>
            )}
          </Field>

          <div className="grid gap-4 sm:grid-cols-2">
            <Field label="Phòng ban" error={loi?.fieldError("department_id")}>
              {(id) => (
                <SelectInput
                  id={id}
                  value={phong}
                  onChange={(e) => setPhong(e.target.value)}
                >
                  <option value="">Chưa xếp phòng</option>
                  {phongBan?.map((p) => (
                    <option key={p.id} value={p.id}>
                      {p.parent_name ? `${p.parent_name} › ${p.name}` : p.name}
                    </option>
                  ))}
                </SelectInput>
              )}
            </Field>

            <Field label="Chức vụ" error={loi?.fieldError("position_id")}>
              {(id) => (
                <SelectInput
                  id={id}
                  value={chuc}
                  onChange={(e) => setChuc(e.target.value)}
                >
                  <option value="">Chưa xếp chức vụ</option>
                  {chucVu?.map((c) => (
                    <option key={c.id} value={c.id}>
                      {c.name}
                    </option>
                  ))}
                </SelectInput>
              )}
            </Field>

            <Field
              label="Quản lý trực tiếp"
              error={loi?.fieldError("manager_id")}
            >
              {(id) => (
                <SelectInput
                  id={id}
                  value={quanLy}
                  onChange={(e) => setQuanLy(e.target.value)}
                >
                  <option value="">Không có</option>
                  {nguoi
                    // Không tự làm quản lý của chính mình. Backend cũng chặn,
                    // nhưng để lựa chọn đó trong danh sách là mời người ta bấm
                    // vào rồi báo lỗi.
                    ?.filter((n) => n.id !== employee?.id)
                    .map((n) => (
                      <option key={n.id} value={n.id}>
                        {n.name}
                      </option>
                    ))}
                </SelectInput>
              )}
            </Field>

            <Field label="Ngày vào làm" error={loi?.fieldError("joined_at")}>
              {(id) => (
                <TextInput
                  id={id}
                  type="date"
                  value={ngayVao}
                  onChange={(e) => setNgayVao(e.target.value)}
                />
              )}
            </Field>

            <Field label="Số điện thoại" error={loi?.fieldError("phone")}>
              {(id) => (
                <TextInput
                  id={id}
                  inputMode="tel"
                  value={dienThoai}
                  onChange={(e) => setDienThoai(e.target.value)}
                  placeholder="0901234567"
                />
              )}
            </Field>
          </div>

          {loi && !loi.errors && (
            <p role="alert" className="text-danger text-[0.85rem]">
              {loi.message}
            </p>
          )}

          <div className="border-line flex gap-3 border-t pt-4">
            <Button
              variant="primary"
              loading={dangGui}
              disabled={
                hoTen.trim() === "" || email.trim() === "" || maNV.trim() === ""
              }
              onClick={gui}
            >
              {dangSua ? "Lưu thay đổi" : "Tạo tài khoản"}
            </Button>
            <Button variant="ghost" onClick={dong}>
              Huỷ
            </Button>
          </div>
        </div>
      )}
    </Dialog>
  );
}
