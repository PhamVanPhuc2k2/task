"use client";

import { Avatar } from "@/components/ui/pill";
import { cn } from "@/lib/cn";

import { formatMinutes } from "../types/attendance";
import {
  toMinutes,
  type DayTimeline as DayTimelineData,
  type TimelineRow,
  type TimelineSegment,
} from "../types/timeline";

/**
 * Dòng thời gian một ngày của cả đội.
 *
 * ## Bốn màu, bốn ý nghĩa khác nhau
 *
 * | Màu | Nghĩa |
 * |---|---|
 * | Sắc miền đậm | Đang thao tác trên Explus |
 * | Sắc miền **nhạt** | Mở Explus nhưng làm việc ở nơi khác |
 * | Vàng | **Không mở Explus** — máy tắt, đóng trình duyệt, mất mạng |
 * | Xám | Ngoài khoảng đã ghi nhận: chưa vào, hoặc đã nghỉ |
 *
 * ### Vì sao nhạt chứ không đổi màu
 *
 * Hai sắc đầu **đều là giờ công**, chỉ khác nhau ở chỗ người dùng có chạm vào
 * Explus hay không. Đổi cái thứ hai sang vàng sẽ nói sai một điều quan trọng:
 * lập trình viên ngồi cả buổi trong IDE là chuyện bình thường, không phải dấu
 * hiệu cần để mắt tới. Nhạt hơn là đủ để phân biệt mà không hàm ý phán xét.
 *
 * Vàng chỉ dùng cho khe **ở giữa**. Khoảng trước phiên đầu và sau phiên cuối để
 * xám: chưa tới giờ làm và đã về thì không phải ngồi không, tô vàng vào đó là
 * biến cả buổi tối thành thời gian lười biếng.
 *
 * ## Vì sao tách hai hàng sáng / chiều
 *
 * Một hàng phủ 08h–18h thì mỗi giờ chỉ được vài chục pixel, và một khe 20 phút
 * mảnh tới mức không nhìn ra. Cắt đôi ở giờ nghỉ trưa cho gấp đôi độ phân giải,
 * và cũng khớp với cách người ta nghĩ về ngày làm việc.
 */
export function DayTimeline({ data }: { data: DayTimelineData }) {
  const trua = {
    tu: toMinutes(data.shift.lunch_start),
    den: toMinutes(data.shift.lunch_end),
  };

  const dau = toMinutes(data.range.start);
  const cuoi = toMinutes(data.range.end);
  const giuaTrua = toMinutes(data.shift.lunch_start);

  // Điểm cắt phải nằm TRONG khoảng, nếu không thì một hàng sẽ có bề rộng âm.
  // Xảy ra thật khi cả đội chỉ làm buổi tối: khung giờ bắt đầu sau 12h.
  const cat = Math.min(Math.max(giuaTrua, dau), cuoi);

  return (
    <div className="space-y-2.5">
      {data.rows.map((r) => (
        <HangNguoi
          key={r.user.id}
          row={r}
          dau={dau}
          cat={cat}
          cuoi={cuoi}
          trua={trua}
        />
      ))}

      <ChuGiaiDongThoiGian />
    </div>
  );
}

function HangNguoi({
  row,
  dau,
  cat,
  cuoi,
  trua,
}: {
  row: TimelineRow;
  dau: number;
  cat: number;
  cuoi: number;
  trua: { tu: number; den: number };
}) {
  const chuaVao = row.sessions.length === 0;

  return (
    <div
      className={cn(
        "border-line bg-paper-raised rounded-xl border p-3.5",
        // Người chưa có hoạt động nào thì cả hàng chìm xuống — mắt lướt qua
        // được ngay, không phải đọc từng dòng để tìm ai vắng.
        chuaVao && "opacity-60",
      )}
    >
      <div className="mb-2.5 flex flex-wrap items-center gap-x-3 gap-y-1.5">
        <Avatar name={row.user.name} size="sm" />

        <span className="text-[0.88rem] font-medium">
          {row.user.name}
          {row.user.department && (
            <span className="text-ink-faint ml-1.5 text-[0.74rem] font-normal">
              {row.user.department}
            </span>
          )}
        </span>

        <TomTatHang row={row} />
      </div>

      <Truc row={row} tu={dau} den={cat} trua={trua} />
      <Truc row={row} tu={cat} den={cuoi} trua={trua} />
    </div>
  );
}

/** Các con số bên phải tên: đọc được mà không cần nhìn thanh. */
function TomTatHang({ row }: { row: TimelineRow }) {
  if (row.on_leave) {
    return (
      <span className="border-tone-line bg-tone-surface text-tone-ink rounded-full border px-2 py-0.5 text-[0.74rem] font-medium">
        Nghỉ phép
      </span>
    );
  }

  if (row.sessions.length === 0) {
    return (
      <span className="text-ink-faint text-[0.8rem]">Chưa có hoạt động</span>
    );
  }

  return (
    <span className="text-ink-soft flex flex-wrap items-center gap-x-3 gap-y-1 text-[0.8rem] tabular-nums">
      <span>
        {row.first_seen} – {row.last_seen}
      </span>

      <span className="text-ink font-semibold">
        {formatMinutes(row.worked_minutes)}
      </span>

      {row.idle_minutes > 0 && (
        <span className="text-notice">
          ngồi không {formatMinutes(row.idle_minutes)}
        </span>
      )}

      {row.late_minutes > 0 && (
        <span
          className={row.late_excused ? "text-ink-faint" : "text-danger"}
          title={
            row.late_excused
              ? "Có đơn xin đi muộn đã duyệt"
              : "Không có đơn xin phép"
          }
        >
          muộn {row.late_minutes}′{row.late_excused && " (có phép)"}
        </span>
      )}
    </span>
  );
}

/**
 * Một hàng trục: thanh màu ở trên, nhãn giờ ở dưới.
 *
 * Mọi đoạn đều được **cắt về đúng khoảng của hàng này** trước khi tính vị trí.
 * Không cắt thì một phiên 11h–14h sẽ tràn ra ngoài hàng buổi sáng, và trình
 * duyệt vẽ nó đè lên tên người bên cạnh.
 */
function Truc({
  row,
  tu,
  den,
  trua,
}: {
  row: TimelineRow;
  tu: number;
  den: number;
  trua: { tu: number; den: number };
}) {
  const rong = den - tu;

  if (rong <= 0) return null;

  const gio: number[] = [];
  for (let g = Math.ceil(tu / 60); g * 60 <= den; g++) gio.push(g);

  return (
    <div className="mb-1.5 last:mb-0">
      <div className="bg-paper-sunken border-line relative h-3.5 overflow-hidden rounded-full border">
        {/* Dải nghỉ trưa vẽ dưới cùng, rất nhạt: nó là bối cảnh để người xem
            hiểu vì sao có khoảng trống ở đó, không phải một sự kiện. */}
        <span
          aria-hidden="true"
          className="bg-line/70 absolute inset-y-0"
          style={{
            left: `${((Math.max(trua.tu, tu) - tu) / rong) * 100}%`,
            width: `${(Math.max(0, Math.min(trua.den, tu + rong) - Math.max(trua.tu, tu)) / rong) * 100}%`,
          }}
        />

        {/* Vàng vẽ TRƯỚC: nếu có sai lệch làm tràn một pixel thì phiên làm việc
            đè lên nó, chứ không phải ngược lại. Thà mất một vạch vàng còn hơn
            che mất một phiên có thật. */}
        {row.gaps.map((k) => (
          <Doan key={`k-${k.start}`} seg={k} tu={tu} rong={rong} loai="idle" />
        ))}

        {row.sessions.map((p) => (
          <Doan
            key={`p-${p.start}`}
            seg={p}
            tu={tu}
            rong={rong}
            // `=== false` chứ không `!p.interactive`: trường này có thể vắng
            // mặt ở dữ liệu cũ, và vắng mặt nghĩa là "không biết" chứ không
            // phải "không có thao tác". Đoán sai hướng thì cả ngày công cũ
            // hiện ra nhạt màu như thể không ai làm gì.
            loai={p.interactive === false ? "present" : "work"}
          />
        ))}
      </div>

      <div className="text-ink-faint relative mt-0.5 h-3.5 text-[0.66rem] tabular-nums">
        {gio.map((g) => (
          <span
            key={g}
            className="absolute -translate-x-1/2"
            style={{ left: `${((g * 60 - tu) / rong) * 100}%` }}
          >
            {String(g).padStart(2, "0")}h
          </span>
        ))}
      </div>
    </div>
  );
}

function Doan({
  seg,
  tu,
  rong,
  loai,
}: {
  seg: TimelineSegment;
  tu: number;
  rong: number;
  loai: "work" | "idle" | "present";
}) {
  const s = Math.max(toMinutes(seg.start), tu);
  const e = Math.min(toMinutes(seg.end), tu + rong);

  // Đoạn nằm hoàn toàn ngoài hàng này.
  if (e <= s) return null;

  return (
    <span
      aria-hidden="true"
      title={
        `${seg.start} – ${seg.end} · ${seg.minutes} phút` +
        (loai === "present" ? " · mở Explus, không thao tác" : "")
      }
      className={cn(
        "absolute inset-y-0 rounded-full",
        loai === "work" && "bg-tone",
        loai === "idle" && "bg-notice",
        // Mở Explus nhưng không chạm vào: vẫn là giờ công, nên dùng CÙNG sắc
        // với phiên có thao tác chứ không đổi sang màu cảnh báo. Chỉ nhạt hơn.
        //
        // Đổi sang màu vàng ở đây sẽ nói sai một điều quan trọng: lập trình
        // viên làm cả buổi trong IDE là chuyện bình thường, không phải dấu
        // hiệu cần để mắt.
        loai === "present" && "bg-tone/40",
      )}
      style={{
        left: `${((s - tu) / rong) * 100}%`,
        // Sàn 0,4% để một khe 12 phút trên trục 5 tiếng vẫn còn nhìn thấy —
        // không có sàn thì nó mảnh tới mức biến mất, và cả điểm mạnh của màn
        // hình này mất theo.
        width: `${Math.max(((e - s) / rong) * 100, 0.4)}%`,
      }}
    />
  );
}

function ChuGiaiDongThoiGian() {
  const muc: { mau: string; nhan: string }[] = [
    { mau: "bg-tone", nhan: "Đang thao tác trên Explus" },
    { mau: "bg-tone/40", nhan: "Mở Explus, làm việc ở nơi khác" },
    { mau: "bg-notice", nhan: "Không mở Explus" },
    { mau: "bg-paper-sunken border-line border", nhan: "Ngoài giờ ghi nhận" },
  ];

  return (
    <div className="text-ink-soft flex flex-wrap items-center gap-x-4 gap-y-2 pt-1 text-[0.76rem]">
      {muc.map((m) => (
        <span key={m.nhan} className="inline-flex items-center gap-1.5">
          <span
            aria-hidden="true"
            className={cn("h-2.5 w-5 rounded-full", m.mau)}
          />
          {m.nhan}
        </span>
      ))}

      <span className="text-ink-faint">
        Khoảng lặng dưới 10 phút được tính là vẫn đang làm — đọc tài liệu hay
        nghe điện thoại không cắt phiên.
      </span>
    </div>
  );
}
