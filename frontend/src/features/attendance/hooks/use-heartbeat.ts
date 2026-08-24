"use client";

import { useEffect, useRef, useState } from "react";

import { api } from "@/lib/api-client";

/**
 * Gửi nhịp tim khi người dùng **thật sự thao tác**, không phải khi tab còn mở.
 *
 * Đây là khác biệt cốt lõi so với cách "treo web đếm giờ": mở tab rồi bỏ đi
 * không sinh nhịp nào, nên khoảng đó không được tính vào giờ làm.
 *
 * Bốn cái bẫy của trình duyệt, cái nào cũng đủ làm hỏng số liệu:
 *
 * 1. **Tab nền bị bóp.** Chrome hạ `setInterval` ở tab ẩn xuống 1 lần/phút rồi
 *    có thể đóng băng hẳn. Ở đây không sao vì tab ẩn thì cũng không nên gửi
 *    nhịp — nhưng đó là lý do KHÔNG được đếm giờ bằng bộ đếm chạy trong tab.
 *    Tổng giờ do backend tính từ mốc thời gian, không do trình duyệt cộng.
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
 *    cờ `dangGui` chặn chồng request, và khoảng lặng vượt ngưỡng đã được
 *    backend cắt ra khỏi tổng.
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

export function useHeartbeat(enabled: boolean): number | null {
  const [soPhut, setSoPhut] = useState<number | null>(null);

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
      // Ba điều kiện, thiếu một là số liệu sai:
      //   - có thao tác trong phút vừa rồi
      //   - tab đang hiển thị (ẩn tab đi pha cà phê không tính là làm)
      //   - chưa có request nào đang bay (máy vừa thức dậy bắn bù dồn dập)
      if (!coThaoTac.current || document.hidden || dangGui.current) return;

      coThaoTac.current = false;
      dangGui.current = true;

      try {
        const kq = await api.post<{
          data: { today_minutes: number };
        }>("/attendance/heartbeat");

        setSoPhut(kq.data.today_minutes);
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

  return soPhut;
}
