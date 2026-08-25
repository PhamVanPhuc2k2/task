"use client";

import { useEffect, useRef, useState } from "react";

import { api } from "@/lib/api-client";

/**
 * Gửi nhịp tim **chừng nào tab còn mở**, kèm cờ nói phút đó có thao tác không.
 *
 * ── Vì sao đổi khỏi "chỉ tính khi có thao tác" ───────────────────────────────
 *
 * Bản trước chỉ gửi nhịp khi có bấm/gõ/cuộn trên Explus và tab đang hiển thị.
 * Với lập trình viên đó là đo sai người: họ sống trong IDE và terminal, cả buổi
 * sáng viết code xong hệ thống hiện số 0.
 *
 * Đo hụt người làm thật tệ hơn hẳn đếm dư người treo máy. Nên giờ tab mở là
 * tính — kể cả khi tab bị ẩn sau cửa sổ VS Code, vì đó chính là tình huống cần
 * đo.
 *
 * ── Nhưng vẫn báo có thao tác hay không ──────────────────────────────────────
 *
 * Cờ `active` giữ lại đúng tín hiệu mà bản cũ có. Tổng giờ cộng cả hai loại,
 * nhưng dòng thời gian vẫn vẽ được "ngồi làm" khác màu với "để tab đó". Bỏ hẳn
 * cờ này thì đổi cách tính đồng nghĩa với mất luôn khả năng phân biệt, và khi
 * có tranh cãi về một ngày công cụ thể thì không còn gì để nhìn.
 *
 * ── Bốn cái bẫy của trình duyệt ──────────────────────────────────────────────
 *
 * 1. **Tab nền bị bóp.** Chrome hạ `setInterval` ở tab ẩn xuống 1 lần/phút —
 *    đúng bằng nhịp ở đây, nên vẫn về đều, chỉ trôi vài giây. Ngưỡng nối phiên
 *    của backend là 10 phút nên trôi bấy nhiêu không cắt phiên.
 *
 *    Trường hợp xấu: trình duyệt **đóng băng hẳn** tab (Chrome Memory Saver,
 *    iOS Safari nền lâu). Lúc đó nhịp ngừng và phiên đóng lại — đo hụt chứ
 *    không đo dư. Đây là giới hạn thật, không vá được từ phía trang.
 *
 * 2. **Đóng máy đột ngột không bắn sự kiện.** Sập nắp laptop, mất điện, mất
 *    mạng, đóng trình duyệt trên điện thoại — `beforeunload` không chạy. Vì
 *    vậy không có khái niệm "ra ca": phiên tự kết thúc ở nhịp cuối cùng
 *    backend nhận được.
 *
 * 3. **Nhiều tab.** Mỗi tab gửi nhịp riêng, nhưng backend gộp theo người trên
 *    trục thời gian nên mở mười tab vẫn ra đúng một phiên.
 *
 * 4. **Ngủ máy giữa chừng.** Máy thức dậy thì `setInterval` bắn bù dồn dập;
 *    cờ `dangGui` chặn chồng request, và khoảng máy ngủ không có nhịp nào nên
 *    backend không tính.
 */

/** Khoảng gửi nhịp. Khớp với ngưỡng nối phiên 10 phút ở backend. */
const NHIP_MS = 60_000;

/**
 * Các sự kiện tính là "có thao tác thật".
 *
 * `pointerdown`/`keydown`/`wheel` chứ không phải `mousemove`: rê chuột qua màn
 * hình lúc đi ngang bàn không phải là làm việc, và `mousemove` bắn hàng trăm
 * lần mỗi giây.
 */
const SU_KIEN = ["pointerdown", "keydown", "wheel", "touchstart"] as const;

export interface NhipTim {
  /** Số phút hôm nay, null khi chưa nhận được nhịp nào. */
  soPhut: number | null;
  /** Đã chạm trần giờ tự động trong ngày — nhịp không còn được ghi. */
  chamTran: boolean;
}

export function useHeartbeat(enabled: boolean): NhipTim {
  const [soPhut, setSoPhut] = useState<number | null>(null);
  const [chamTran, setChamTran] = useState(false);

  // `useRef` chứ không `useState`: cờ này đổi hàng chục lần mỗi phút và không
  // được phép làm component vẽ lại.
  const coThaoTac = useRef(false);
  const dangGui = useRef(false);

  useEffect(() => {
    if (!enabled) return;

    const danhDau = () => {
      coThaoTac.current = true;
    };

    for (const ten of SU_KIEN) {
      window.addEventListener(ten, danhDau, { passive: true });
    }

    const gui = async () => {
      // Chỉ còn MỘT điều kiện chặn: đang có request bay dở.
      //
      // Máy vừa thức dậy sau khi ngủ thì `setInterval` bắn bù dồn dập; không
      // có cờ này thì hàng chục request cùng lúc chồng lên nhau, và cái tới
      // sau ghi đè số phút của cái tới trước theo thứ tự ngẫu nhiên.
      //
      // KHÔNG còn chặn theo `document.hidden`: tab ẩn sau cửa sổ VS Code chính
      // là tình huống cần đo, không phải tình huống cần bỏ qua.
      if (dangGui.current) return;

      const daThaoTac = coThaoTac.current;
      coThaoTac.current = false;
      dangGui.current = true;

      try {
        const kq = await api.post<{
          data: { today_minutes: number; capped: boolean };
        }>("/attendance/heartbeat", { active: daThaoTac });

        setSoPhut(kq.data.today_minutes);
        setChamTran(kq.data.capped);
      } catch {
        // Mất mạng thì im lặng bỏ qua. Nhịp sau sẽ gửi lại, và backend tính
        // giờ từ mốc thời gian nên mất vài nhịp giữa chừng không tạo lỗ hổng
        // — chỉ khi mất quá 10 phút liền mới bị cắt phiên, mà lúc đó đúng là
        // không nên tính.
      } finally {
        dangGui.current = false;
      }
    };

    // Gửi ngay một nhịp lúc vào app, đừng bắt chờ đủ một phút mới bắt đầu đếm.
    coThaoTac.current = true;
    void gui();

    const dinhKy = window.setInterval(() => void gui(), NHIP_MS);

    return () => {
      window.clearInterval(dinhKy);

      for (const ten of SU_KIEN) {
        window.removeEventListener(ten, danhDau);
      }
    };
  }, [enabled]);

  return { soPhut, chamTran };
}
