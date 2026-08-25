import type { Department } from "@/features/users/types/employee";

export interface NodePhongBan {
  phongBan: Department;
  /** 0 là phòng ban gốc. Dùng để thụt lề. */
  cap: number;
}

/**
 * Duỗi cây phòng ban thành một danh sách phẳng đã xếp đúng thứ tự cha–con.
 *
 * Trả về danh sách phẳng chứ không phải cấu trúc lồng nhau: cái cần vẽ là các
 * hàng thụt lề, và một mảng phẳng kèm `cap` render thẳng ra được mà không phải
 * đệ quy trong JSX.
 *
 * ## Không có phòng ban nào được phép biến mất
 *
 * Hai trường hợp làm một nút rơi khỏi cây nếu duyệt ngây thơ:
 *
 *   1. **Mồ côi** — `parent_id` trỏ tới phòng ban không có trong danh sách.
 *   2. **Vòng** — A là cha của B và B là cha của A. Backend chặn, nhưng dữ liệu
 *      cũ hoặc một lần sửa tay bằng SQL vẫn có thể tạo ra.
 *
 * Cả hai đều được kéo lên mức gốc thay vì bỏ qua. Một phòng ban không hiện trên
 * màn hình quản lý phòng ban là một phòng ban không ai sửa được — và nhân sự
 * bên trong nó cũng không ai chuyển đi đâu được.
 */
export function duoiCay(danhSach: Department[]): NodePhongBan[] {
  const theoId = new Map(danhSach.map((p) => [p.id, p]));

  const conTheoCha = new Map<string, Department[]>();
  const goc: Department[] = [];

  for (const phongBan of danhSach) {
    const chaId = phongBan.parent_id;

    if (chaId === null || !theoId.has(chaId)) {
      goc.push(phongBan);
      continue;
    }

    const anhEm = conTheoCha.get(chaId);
    if (anhEm === undefined) {
      conTheoCha.set(chaId, [phongBan]);
    } else {
      anhEm.push(phongBan);
    }
  }

  const ketQua: NodePhongBan[] = [];
  const daTham = new Set<string>();

  const di = (phongBan: Department, cap: number): void => {
    if (daTham.has(phongBan.id)) return;
    daTham.add(phongBan.id);

    ketQua.push({ phongBan, cap });

    for (const con of conTheoCha.get(phongBan.id) ?? []) {
      di(con, cap + 1);
    }
  };

  for (const phongBan of goc) {
    di(phongBan, 0);
  }

  // Còn sót lại nghĩa là nằm trong một vòng: không nút nào trong vòng đó với
  // tới được từ gốc. Đẩy lên mức 0 để người dùng còn thấy mà gỡ.
  for (const phongBan of danhSach) {
    if (!daTham.has(phongBan.id)) {
      di(phongBan, 0);
    }
  }

  return ketQua;
}

/**
 * Những phòng ban KHÔNG được chọn làm cấp trên của `id`: chính nó và mọi cấp
 * dưới của nó.
 *
 * Chỉ để ô chọn không bày ra lựa chọn sẽ bị từ chối. Chặn thật nằm ở
 * `UpdateDepartmentAction` phía backend — cùng nguyên tắc với việc ẩn mục điều
 * hướng theo quyền.
 */
export function khongChonDuoc(
  danhSach: Department[],
  id: string | null,
): Set<string> {
  const cam = new Set<string>();
  if (id === null) return cam;

  cam.add(id);

  // Lặp cho tới khi không thêm được gì nữa: danh sách phẳng nên một lượt duyệt
  // có thể bỏ sót cháu nếu nó đứng trước con trong mảng.
  let themDuoc = true;

  while (themDuoc) {
    themDuoc = false;

    for (const phongBan of danhSach) {
      if (
        phongBan.parent_id !== null &&
        cam.has(phongBan.parent_id) &&
        !cam.has(phongBan.id)
      ) {
        cam.add(phongBan.id);
        themDuoc = true;
      }
    }
  }

  return cam;
}
