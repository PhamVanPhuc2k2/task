"use client";

import { useState } from "react";

import { Button } from "@/components/ui/button";
import { SelectInput } from "@/components/ui/field";
import { ErrorState, Skeleton } from "@/components/ui/states";
import {
  useDayTimeline,
  useMyAttendance,
  useTeamAttendance,
} from "@/features/attendance/api/attendance-api";
import { AdjustmentPanel } from "@/features/attendance/components/adjustment-panel";
import { DayTimeline } from "@/features/attendance/components/day-timeline";
import { OvertimePanel } from "@/features/attendance/components/overtime-panel";
import { PeriodPanel } from "@/features/attendance/components/period-panel";
import { ReviewDialog } from "@/features/attendance/components/review-dialog";
import {
  formatMinutes,
  type AttendanceCell,
  type AttendanceRow,
} from "@/features/attendance/types/attendance";
import { useCurrentUser } from "@/features/auth/api/auth-api";
import { cn } from "@/lib/cn";
import { formatDate } from "@/lib/format";

/**
 * Chấm công.
 *
 * Một trang, hai tầng: giờ làm của chính mình (ai cũng thấy), và bảng công cả
 * phòng (chỉ người có quyền). Gộp làm một vì quản lý cũng là người có giờ làm
 * của riêng họ — tách hai trang thì họ phải nhớ mình đang ở trang nào.
 *
 * **Số giờ ở đây không tự trừ vào lương.** Hệ thống đo và gắn cờ; con người
 * quyết định. Mọi ngày bất thường đều chờ một người bấm nút kèm lý do.
 */
export default function AttendancePage() {
  const { data: user } = useCurrentUser();

  const [thang, setThang] = useState(() => thangHienTai());

  /*
  | Ba việc, một trang.
  |
  | Bảng công, giải trình và chốt sổ đều xoay quanh cùng một con số giờ, do
  | cùng những người mở ra xem. Tách thành ba mục điều hướng thì thanh bên dài
  | thêm cho những thứ người ta tìm ở cùng chỗ — cùng lý do đã ghi ở trang Nghỉ
  | phép cho cặp nghỉ phép / đi muộn.
  */
  const [mucXem, setMucXem] = useState<MucXem>("cong");

  /*
  | Ngày điền sẵn khi người dùng bấm "Giải trình ngày này" trong hộp thoại chi
  | tiết. Đây là đường vào tự nhiên nhất của module: người ta phát hiện ra ngày
  | công sai lúc đang NHÌN vào ô đó, chứ không phải lúc mở một tab trống rồi
  | ngồi nhớ lại hôm ấy là ngày mấy.
  */
  const [giaiTrinhNgay, setGiaiTrinhNgay] = useState("");

  /*
  | Hai cách xem, hai câu hỏi khác nhau.
  |
  | Lưới tháng: "tháng này ai làm bao nhiêu giờ". Dòng thời gian: "hôm nay ai
  | đang làm, khoảng nào ngồi không". Mặc định vẫn là lưới tháng — nó là màn
  | tổng hợp, còn dòng thời gian là màn theo dõi trong ngày.
  */
  const [cheDo, setCheDo] = useState<"thang" | "ngay">("thang");
  const [ngayXem, setNgayXem] = useState(() => homNay());
  const [dangMo, setDangMo] = useState<{
    userId: string;
    userName: string;
    date: string;
    cell: AttendanceCell | undefined;
  } | null>(null);

  const xemDoi =
    user?.permissions.some(
      (p) => p === "attendance.view.team" || p === "attendance.view.all",
    ) === true;

  // Xem được đội là một chuyện, quyết định được ngày công là chuyện khác. Hộp
  // duyệt đơn giải trình chỉ hiện cho người làm được cả hai — hiện cho người
  // chỉ xem được thì mọi nút trong đó đều dẫn tới 403.
  const duyetCongDuoc =
    user?.permissions.some((p) => p === "attendance.review") === true;

  const chotSoDuoc =
    user?.permissions.some(
      (p) =>
        p === "attendance.period.close" || p === "attendance.period.reopen",
    ) === true;

  const cuaToi = useMyAttendance(thang);
  const cuaDoi = useTeamAttendance(thang, "", xemDoi && cheDo === "thang");
  const dongThoiGian = useDayTimeline(ngayXem, xemDoi && cheDo === "ngay");

  return (
    <div data-tone="time" className="enter space-y-8">
      <header className="flex flex-wrap items-end justify-between gap-4">
        <div>
          <span
            aria-hidden="true"
            className="bg-tone mb-3 block h-[3px] w-9 rounded-full"
          />
          <h1 className="text-[1.65rem] leading-tight font-semibold tracking-[-0.035em]">
            Chấm công
          </h1>
          <p className="text-ink-soft mt-1.5 text-[0.9rem]">
            Giờ làm ghi nhận từ thao tác thật trên hệ thống. Không tự trừ vào
            lương — mọi ngày bất thường đều do người quản lý xem xét.
          </p>
        </div>

        {mucXem === "cong" && <ChonThang value={thang} onChange={setThang} />}
      </header>

      <ChonMuc mucXem={mucXem} onChange={setMucXem} chotSoDuoc={chotSoDuoc} />

      {mucXem === "cong" && (
        <>
          {/* ── Của tôi ─────────────────────────────────── */}
          <section className="tone-card rounded-2xl p-5">
            <div className="mb-4 flex flex-wrap items-baseline justify-between gap-3">
              <h2 className="text-[0.95rem] font-semibold tracking-tight">
                Giờ làm của tôi
              </h2>

              {cuaToi.data && (
                <p className="text-ink-soft text-[0.85rem] tabular-nums">
                  <strong className="text-ink font-semibold">
                    {formatMinutes(cuaToi.data.total_minutes)}
                  </strong>{" "}
                  trong {cuaToi.data.days_worked} ngày
                </p>
              )}
            </div>

            {/* Nói thẳng ra thay vì để người dùng tự dò ba mươi ô. Đặt ở màn của
            chính mình trước màn của quản lý là có chủ ý: tự thấy rồi tự bù thì
            không cần ai hỏi tới. */}
            {cuaToi.data !== undefined &&
              cuaToi.data.missing_report_days > 0 && (
                <p className="border-notice-line bg-notice-surface text-notice mb-4 rounded-xl border px-3 py-2 text-[0.84rem]">
                  Có <strong>{cuaToi.data.missing_report_days} ngày</strong> bạn
                  có giờ làm nhưng chưa nộp báo cáo. Bấm vào ô có chấm để xem
                  ngày nào.
                </p>
              )}

            {cuaToi.isPending && <Skeleton className="h-24" />}

            {cuaToi.isError && (
              <ErrorState
                error={cuaToi.error}
                onRetry={() => void cuaToi.refetch()}
              />
            )}

            {cuaToi.data && (
              <>
                <TomTat data={cuaToi.data} />

                <LichThang
                  days={cuaToi.data.days}
                  holidays={cuaToi.data.holidays}
                  cells={cuaToi.data.cells}
                  onPick={(date, cell) =>
                    user &&
                    setDangMo({
                      userId: user.id,
                      userName: user.name,
                      date,
                      cell,
                    })
                  }
                />
              </>
            )}
          </section>

          {/* ── Của đội ─────────────────────────────────── */}
          {xemDoi && (
            <section className="tone-card rounded-2xl p-5">
              <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                <h2 className="text-[0.95rem] font-semibold tracking-tight">
                  {cheDo === "thang" ? "Bảng công của đội" : "Hôm nay của đội"}
                </h2>

                {/*
              Hai màn trả lời hai câu khác nhau, không phải hai cách hiển thị
              cùng một thứ: lưới tháng nói "ai làm bao nhiêu giờ", dòng thời
              gian nói "hôm nay ai đang làm và khoảng nào ngồi không".
            */}
                <div
                  role="radiogroup"
                  aria-label="Cách xem"
                  className="border-line bg-paper-sunken inline-flex gap-0.5 rounded-xl border p-0.5"
                >
                  {(
                    [
                      ["thang", "Lưới tháng"],
                      ["ngay", "Dòng thời gian"],
                    ] as const
                  ).map(([v, nhan]) => (
                    <button
                      key={v}
                      type="button"
                      role="radio"
                      aria-checked={cheDo === v}
                      onClick={() => setCheDo(v)}
                      className={cn(
                        "focus-frame rounded-lg px-3 py-1.5 text-[0.82rem] font-medium transition-colors",
                        cheDo === v
                          ? "bg-paper-raised text-ink shadow-card"
                          : "text-ink-faint hover:text-ink-soft",
                      )}
                    >
                      {nhan}
                    </button>
                  ))}
                </div>
              </div>

              {cheDo === "ngay" && (
                <div className="space-y-4">
                  <div className="flex flex-wrap items-center gap-2.5">
                    <label
                      htmlFor="ngay-dong-thoi-gian"
                      className="text-ink-soft text-[0.84rem] font-medium"
                    >
                      Ngày
                    </label>
                    <input
                      id="ngay-dong-thoi-gian"
                      type="date"
                      value={ngayXem}
                      onChange={(e) => setNgayXem(e.target.value)}
                      className="focus-frame border-line bg-paper-raised rounded-lg border px-2.5 py-1.5 text-[0.84rem]"
                    />
                  </div>

                  {dongThoiGian.isPending && <Skeleton className="h-48" />}

                  {dongThoiGian.isError && (
                    <ErrorState
                      error={dongThoiGian.error}
                      onRetry={() => void dongThoiGian.refetch()}
                    />
                  )}

                  {dongThoiGian.data && (
                    <DayTimeline data={dongThoiGian.data} />
                  )}
                </div>
              )}

              {cheDo === "thang" && cuaDoi.isPending && (
                <Skeleton className="h-48" />
              )}

              {cheDo === "thang" && cuaDoi.isError && (
                <ErrorState
                  error={cuaDoi.error}
                  onRetry={() => void cuaDoi.refetch()}
                />
              )}

              {cheDo === "thang" && cuaDoi.data && (
                <BangDoi
                  rows={cuaDoi.data.rows}
                  days={cuaDoi.data.days}
                  holidays={cuaDoi.data.holidays}
                  onPick={(row, date) =>
                    setDangMo({
                      userId: row.user.id,
                      userName: row.user.name,
                      date,
                      cell: row.cells[date],
                    })
                  }
                />
              )}
            </section>
          )}
        </>
      )}

      {mucXem === "giaitrinh" && (
        <AdjustmentPanel
          canReview={xemDoi && duyetCongDuoc}
          initialDate={giaiTrinhNgay}
        />
      )}

      {mucXem === "lamthem" && (
        <OvertimePanel canReview={xemDoi && duyetCongDuoc} />
      )}

      {mucXem === "chotso" && <PeriodPanel enabled={chotSoDuoc} />}

      {dangMo && (
        <ReviewDialog
          key={`${dangMo.userId}:${dangMo.date}`}
          open
          onClose={() => setDangMo(null)}
          userId={dangMo.userId}
          userName={dangMo.userName}
          date={dangMo.date}
          cell={dangMo.cell}
          canReview={
            (cuaDoi.data?.can_review ?? false) && dangMo.userId !== user?.id
          }
          /*
          | Chỉ ngày CỦA CHÍNH MÌNH mới giải trình được — đơn giải trình là một
          | lời khai, người khác khai hộ thì chữ ký nằm sai chỗ. Backend cũng
          | chặn; đây chỉ là để nút không hiện ra rồi bấm vào ăn lỗi.
          */
          onExplain={
            dangMo.userId === user?.id
              ? () => {
                  setGiaiTrinhNgay(dangMo.date);
                  setMucXem("giaitrinh");
                  setDangMo(null);
                }
              : undefined
          }
        />
      )}
    </div>
  );
}

type MucXem = "cong" | "giaitrinh" | "lamthem" | "chotso";

/**
 * Bộ chọn ba chế độ, cùng khuôn với trang Nghỉ phép.
 *
 * "Chốt sổ" chỉ hiện cho người chốt hoặc mở khoá được. Hiện cho mọi người rồi
 * để họ bấm vào ăn 403 là dạy người dùng rằng lỗi đỏ là chuyện bình thường.
 *
 * Ba mục còn lại thì ai cũng thấy: bảng công, giải trình và làm thêm giờ đều có
 * phần "của tôi" mà mọi nhân viên đều dùng tới.
 */
function ChonMuc({
  mucXem,
  onChange,
  chotSoDuoc,
}: {
  mucXem: MucXem;
  onChange: (v: MucXem) => void;
  chotSoDuoc: boolean;
}) {
  const muc: { v: MucXem; nhan: string }[] = [
    { v: "cong", nhan: "Bảng công" },
    { v: "giaitrinh", nhan: "Giải trình" },
    { v: "lamthem", nhan: "Làm thêm" },
    ...(chotSoDuoc ? [{ v: "chotso" as const, nhan: "Chốt sổ" }] : []),
  ];

  return (
    <div
      role="radiogroup"
      aria-label="Chế độ xem"
      className="border-line bg-paper-sunken inline-flex gap-0.5 rounded-xl border p-0.5"
    >
      {muc.map((m) => (
        <button
          key={m.v}
          type="button"
          role="radio"
          aria-checked={mucXem === m.v}
          onClick={() => onChange(m.v)}
          className={cn(
            "focus-frame rounded-lg px-4 py-1.5 text-[0.86rem] font-medium transition-colors",
            mucXem === m.v
              ? "bg-paper-raised text-ink shadow-card"
              : "text-ink-faint hover:text-ink-soft",
          )}
        >
          {m.nhan}
        </button>
      ))}
    </div>
  );
}

/*
|--------------------------------------------------------------------------
| Dải ngày của một người
|--------------------------------------------------------------------------
*/

/*
|--------------------------------------------------------------------------
| Lịch
|--------------------------------------------------------------------------
*/

/** Thứ trong tuần, bắt đầu từ thứ Hai — cách người Việt đọc lịch. */
const THU = ["T2", "T3", "T4", "T5", "T6", "T7", "CN"];

/**
 * Chỉ số thứ trong tuần của một ngày `YYYY-MM-DD`, thứ Hai = 0.
 *
 * Dựng mốc ở UTC chứ không dùng `new Date("2026-08-01")` rồi `getDay()`: chuỗi
 * chỉ có ngày được hiểu là nửa đêm UTC, nên trình duyệt ở múi giờ ÂM sẽ lùi về
 * ngày hôm trước và cả lịch lệch một cột. Lỗi đó không bao giờ lộ ra ở Việt
 * Nam (UTC+7) — chỉ lộ khi có người mở từ châu Âu hay châu Mỹ.
 */
function thuTrongTuan(ngay: string): number {
  const [nam, thang, ngayTrongThang] = ngay.split("-").map(Number);
  const d = new Date(
    Date.UTC(nam ?? 2000, (thang ?? 1) - 1, ngayTrongThang ?? 1),
  );

  return (d.getUTCDay() + 6) % 7;
}

const laCuoiTuan = (thu: number): boolean => thu >= 5;

/**
 * Lưới lịch tháng của một người.
 *
 * **Bảy cột cố định theo thứ, hàng là tuần.** Bản trước dùng `flex-wrap` cho 31
 * ô, nên số ô mỗi hàng phụ thuộc chiều rộng cửa sổ — thứ Hai không bao giờ nằm
 * cùng một cột, và mắt không có mốc nào. Không thấy được cuối tuần, không thấy
 * "tuần này nghỉ ba ngày liền", không thấy "thứ Sáu nào cũng trống".
 *
 * Cùng dữ liệu, cùng màu. Khác biệt nằm ở chỗ mắt có chỗ bám.
 */
function LichThang({
  days,
  holidays,
  cells,
  onPick,
}: {
  days: string[];
  holidays: Record<string, string>;
  cells: Record<string, AttendanceCell>;
  onPick: (date: string, cell: AttendanceCell | undefined) => void;
}) {
  const dauThang = days[0];

  if (dauThang === undefined) return null;

  // Ô trống đầu tháng để ngày 1 rơi đúng cột thứ của nó.
  const oTrong = Array.from({ length: thuTrongTuan(dauThang) }, (_, i) => i);

  return (
    <div>
      <div className="grid grid-cols-7 gap-1.5">
        {THU.map((t, i) => (
          <div
            key={t}
            className={cn(
              "pb-1 text-center text-[0.72rem] font-medium",
              laCuoiTuan(i) ? "text-ink-faint" : "text-ink-soft",
            )}
          >
            {t}
          </div>
        ))}

        {oTrong.map((i) => (
          <div key={`trong-${i}`} aria-hidden="true" />
        ))}

        {days.map((d) => {
          const o = cells[d];
          const le = holidays[d];
          const cuoiTuan = laCuoiTuan(thuTrongTuan(d));

          return (
            <button
              key={d}
              type="button"
              onClick={() => onPick(d, o)}
              title={nhanO(d, o, le)}
              className={cn(
                "focus-frame relative flex h-14 flex-col items-center justify-center rounded-xl border text-[0.72rem] transition-colors",
                mauO(o, le !== undefined),
                // Cuối tuần chìm xuống khi KHÔNG có dữ liệu gì. Có dữ liệu thì
                // để màu thật nói — làm cuối tuần là chuyện bình thường ở công
                // ty remote, không phải điều cần làm mờ đi.
                cuoiTuan && o === undefined && le === undefined && "opacity-45",
              )}
            >
              {thieuBaoCao(o) && <ChamThieuBaoCao />}
              {diMuon(o) && <ChamDiMuon excused={o?.late_excused === true} />}
              <span className="font-semibold tabular-nums">{d.slice(8)}</span>
              <span className="mt-0.5 tabular-nums opacity-80">
                {o?.report_match === "on_leave"
                  ? "Nghỉ"
                  : le !== undefined && o === undefined
                    ? "Lễ"
                    : formatMinutes(o?.minutes ?? 0)}
              </span>
            </button>
          );
        })}
      </div>

      <ChuGiai />
    </div>
  );
}

/**
 * Chú giải màu.
 *
 * Màn này có sáu trạng thái màu và một chấm, mà trước đây không chỗ nào nói
 * chúng nghĩa gì — người dùng phải rê chuột từng ô để đoán. Một dòng chú giải
 * rẻ hơn nhiều so với việc mỗi người tự dựng một cách hiểu riêng.
 */
function ChuGiai() {
  const muc: { mau: string; nhan: string }[] = [
    { mau: "border-tone-line bg-tone-surface", nhan: "Nghỉ phép đã duyệt" },
    { mau: "border-accent-line bg-accent-surface", nhan: "Quản lý đã xử lý" },
    { mau: "border-notice-line bg-notice-surface", nhan: "Cần hỏi lại" },
    { mau: "border-line-strong bg-ink/[0.10]", nhan: "Có giờ làm" },
    { mau: "border-line bg-paper-sunken", nhan: "Không có hoạt động" },
  ];

  return (
    <div className="border-line text-ink-soft mt-4 flex flex-wrap items-center gap-x-4 gap-y-2 border-t pt-3 text-[0.76rem]">
      {muc.map((m) => (
        <span key={m.nhan} className="inline-flex items-center gap-1.5">
          <span
            aria-hidden="true"
            className={cn("size-3 rounded border", m.mau)}
          />
          {m.nhan}
        </span>
      ))}

      <span className="inline-flex items-center gap-1.5">
        <span className="border-line relative size-3 rounded border">
          <span className="bg-notice absolute top-0 right-0 size-1.5 rounded-full" />
        </span>
        Chưa nộp báo cáo
      </span>

      <span className="inline-flex items-center gap-1.5">
        <span className="border-line relative size-3 rounded border">
          <span className="bg-danger absolute top-0 left-0 size-1.5 rounded-full" />
        </span>
        Đi muộn
      </span>

      <span className="inline-flex items-center gap-1.5">
        <span className="border-line relative size-3 rounded border">
          <span className="bg-ink-faint absolute top-0 left-0 size-1.5 rounded-full" />
        </span>
        Đi muộn có phép
      </span>
    </div>
  );
}

/**
 * Một dòng tóm tắt thay cho việc đọc 31 ô.
 *
 * Người ta mở trang này để biết "tháng này thế nào", không phải để đếm ô. Con
 * số đứng trước, lưới lịch là chỗ tra lại khi cần biết ngày cụ thể.
 */
function TomTat({
  data,
}: {
  data: {
    total_minutes: number;
    days_worked: number;
    missing_report_days: number;
    cells: Record<string, AttendanceCell>;
  };
}) {
  const o = Object.values(data.cells);

  const soNgayNghi = o.filter((c) => c.report_match === "on_leave").length;

  // Chỉ đếm ngày đi muộn KHÔNG có đơn: ngày đã xin phép và được duyệt thì
  // không còn là việc cần ai nhìn tới nữa. Gộp cả hai vào một con số thì con
  // số đó không bao giờ về 0, và một chỉ số không bao giờ về 0 là chỉ số người
  // ta ngừng đọc.
  const soNgayMuon = o.filter(
    (c) => c.late_minutes > 0 && !c.late_excused,
  ).length;

  const muc: { so: string; nhan: string; nhan_manh?: boolean }[] = [
    { so: formatMinutes(data.total_minutes), nhan: "tổng giờ" },
    { so: String(data.days_worked), nhan: "ngày có làm" },
    { so: String(soNgayNghi), nhan: "ngày nghỉ phép" },
    {
      so: String(soNgayMuon),
      nhan: "ngày đi muộn",
      nhan_manh: soNgayMuon > 0,
    },
    {
      so: String(data.missing_report_days),
      nhan: "ngày thiếu báo cáo",
      nhan_manh: data.missing_report_days > 0,
    },
  ];

  return (
    <div className="mb-5 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
      {muc.map((m) => (
        <div
          key={m.nhan}
          className={cn(
            "rounded-xl border px-3 py-2.5",
            m.nhan_manh === true
              ? "border-notice-line bg-notice-surface"
              : "border-line bg-paper-sunken",
          )}
        >
          <p
            className={cn(
              "figure text-[1.5rem]",
              m.nhan_manh === true ? "text-notice" : "text-ink",
            )}
          >
            {m.so}
          </p>
          <p className="text-ink-soft mt-1 text-[0.78rem]">{m.nhan}</p>
        </div>
      ))}
    </div>
  );
}

/*
|--------------------------------------------------------------------------
| Bảng công cả đội
|--------------------------------------------------------------------------
*/

function BangDoi({
  rows,
  days,
  holidays,
  onPick,
}: {
  rows: AttendanceRow[];
  days: string[];
  holidays: Record<string, string>;
  onPick: (row: AttendanceRow, date: string) => void;
}) {
  if (rows.length === 0) {
    return (
      <p className="text-ink-faint py-6 text-[0.86rem]">
        Chưa có nhân sự nào trong phạm vi của bạn.
      </p>
    );
  }

  return (
    // Bảng tháng rộng hơn màn hình là điều bình thường — cuộn ngang trong
    // khung riêng để phần còn lại của trang không bị đẩy lệch.
    <div className="overflow-x-auto">
      <table className="border-collapse text-[0.75rem]">
        <thead>
          <tr>
            <th
              scope="col"
              className="bg-paper-raised text-ink-faint sticky left-0 z-10 px-2 py-2 text-left font-medium"
            >
              Nhân sự
            </th>
            {/*
              Header hai tầng: THỨ ở trên, ngày ở dưới.
              Bản trước chỉ có số ngày 1..31, nên cuộn ngang giữa một biển ô
              giống hệt nhau mà không có gì tách tuần. Chữ thứ là mốc rẻ nhất
              để mắt bám vào.
            */}
            {days.map((d) => {
              const thu = thuTrongTuan(d);

              return (
                <th
                  key={d}
                  scope="col"
                  className={cn(
                    "text-ink-faint w-8 px-0 pt-2 pb-1 text-center font-medium tabular-nums",
                    laCuoiTuan(thu) && "bg-paper-sunken",
                    holidays[d] !== undefined && "text-notice",
                  )}
                  title={holidays[d]}
                >
                  <span className="block text-[0.66rem] opacity-70">
                    {THU[thu]?.replace("T", "")}
                  </span>
                  <span className="block">{d.slice(8)}</span>
                </th>
              );
            })}
            <th
              scope="col"
              className="text-ink-faint px-2 py-2 text-right font-medium"
            >
              Tổng
            </th>
            <th
              scope="col"
              className="text-ink-faint px-2 py-2 text-center font-medium"
              title="Số ngày có giờ làm nhưng chưa nộp báo cáo, và chưa ai xử lý"
            >
              Thiếu BC
            </th>
          </tr>
        </thead>

        <tbody>
          {rows.map((r) => (
            <tr key={r.user.id}>
              <th
                scope="row"
                className="bg-paper-raised border-line sticky left-0 z-10 max-w-[10rem] truncate border-r px-2 py-1.5 text-left font-medium"
                title={r.user.name}
              >
                {r.user.name}
              </th>

              {days.map((d) => {
                const o = r.cells[d];

                return (
                  <td
                    key={d}
                    className={cn(
                      "p-0.5",
                      laCuoiTuan(thuTrongTuan(d)) && "bg-paper-sunken",
                    )}
                  >
                    <button
                      type="button"
                      onClick={() => onPick(r, d)}
                      title={nhanO(d, o, holidays[d])}
                      className={cn(
                        "focus-frame relative h-7 w-7 rounded-md border text-[0.68rem] tabular-nums transition-colors",
                        mauO(o, holidays[d] !== undefined),
                      )}
                    >
                      {thieuBaoCao(o) && <ChamThieuBaoCao />}
                      {diMuon(o) && (
                        <ChamDiMuon excused={o?.late_excused === true} />
                      )}
                      {noiDungO(o)}
                    </button>
                  </td>
                );
              })}

              <td className="px-2 py-1.5 text-right font-semibold tabular-nums">
                {formatMinutes(r.total_minutes)}
              </td>

              <td className="px-2 py-1.5 text-center tabular-nums">
                {r.missing_report_days > 0 ? (
                  <span
                    className="border-notice-line bg-notice-surface text-notice inline-flex min-w-6 justify-center rounded-full border px-1.5 py-0.5 font-semibold"
                    title={`${r.missing_report_days} ngày có giờ làm nhưng chưa nộp báo cáo`}
                  >
                    {r.missing_report_days}
                  </span>
                ) : (
                  <span className="text-ink-faint">—</span>
                )}
              </td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}

/*
|--------------------------------------------------------------------------
| Màu ô và nhãn
|--------------------------------------------------------------------------
*/

/**
 * Màu của một ô ngày.
 *
 * Cố ý **không** dùng đỏ cho "giờ thấp". Giờ thấp không phải lỗi — có thể là
 * nghỉ phép, họp ngoài, hoặc làm việc trên công cụ khác. Đỏ ở đây sẽ biến bảng
 * công thành bảng buộc tội, đúng thứ chính sách "nhìn cho biết" tránh.
 *
 * Chỉ có ba mức: chưa có gì, có làm, và đã được người quản lý xử lý.
 */
/**
 * Chữ hiện trong một ô của bảng đội.
 *
 * Bản trước dùng thẳng `Math.round(phút / 60)`. Ngày làm 29 phút ra **"0"** —
 * nhìn gần như y hệt ô trống, và người quản lý đọc thành "không làm gì" trong
 * khi thực tế là "có mở máy nửa tiếng". Sai theo hướng nguy hiểm nhất: nó
 * không giống lỗi, nó giống một sự thật.
 *
 * `<1` dài hơn một ký tự nhưng vẫn vừa ô 28px, và nó nói đúng điều cần nói.
 */
function noiDungO(o: AttendanceCell | undefined): string {
  if (o === undefined) return "";
  if (o.report_match === "on_leave") return "N";

  const gio = Math.round(o.minutes / 60);

  return gio === 0 ? (o.minutes > 0 ? "<1" : "") : String(gio);
}

function mauO(o: AttendanceCell | undefined, laLe: boolean): string {
  /*
   * Nghỉ phép đã duyệt đứng TRƯỚC mọi thứ khác.
   *
   * Đó là câu trả lời đầy đủ cho ô này — không cần biết thêm giờ làm, quyết
   * định hay ngày lễ. Và nó là lý do cả tính năng nghỉ phép tồn tại: trước
   * đây ngày nghỉ để lại một ô trắng y hệt ngày vắng mặt không lý do.
   */
  if (o?.report_match === "on_leave") {
    return "border-tone-line bg-tone-surface text-tone-ink hover:border-tone";
  }

  if (o?.decision === "waived" || o?.decision === "confirmed") {
    return "border-accent-line bg-accent-surface text-accent-ink hover:border-accent";
  }

  if (o?.decision === "flagged") {
    return "border-notice-line bg-notice-surface text-notice hover:border-notice";
  }

  if (o === undefined) {
    return laLe
      ? "border-notice-line bg-notice-surface/50 text-notice hover:border-notice"
      : "border-line bg-paper-sunken text-ink-faint hover:border-line-strong";
  }

  // Đậm dần theo số giờ — so sánh tương đối giữa các ngày, không phải chấm
  // điểm so với một mốc "đủ giờ" nào đó.
  const gio = o.minutes / 60;

  if (gio >= 6)
    return "border-line-strong bg-ink/[0.10] text-ink hover:border-ink-faint";
  if (gio >= 3)
    return "border-line bg-ink/[0.06] text-ink-soft hover:border-line-strong";

  return "border-line bg-ink/[0.03] text-ink-soft hover:border-line-strong";
}

/**
 * Ngày này có giờ làm mà chưa nộp báo cáo, và chưa ai xử lý.
 *
 * Có quyết định của quản lý rồi thì thôi đánh dấu: cờ đã có người nhìn và kết
 * luận. Giữ dấu lại thì bảng không bao giờ sạch, và một bảng không bao giờ sạch
 * là bảng người ta ngừng đọc.
 */
function thieuBaoCao(o: AttendanceCell | undefined): boolean {
  return o?.report_match === "missing_report" && o.decision === null;
}

/**
 * Ngày này đến muộn so với giờ vào ca.
 *
 * Đơn đã duyệt KHÔNG làm dấu biến mất — nó chỉ đổi màu. Xoá hẳn thì bảng công
 * nói dối: người xin phép đàng hoàng và người đúng giờ trông y hệt nhau, và
 * không ai tra lại được "hôm đó đến lúc mấy giờ". Sự thật ở lại, chỉ được giải
 * thích thêm.
 */
function diMuon(o: AttendanceCell | undefined): boolean {
  return o !== undefined && o.late_minutes > 0;
}

/**
 * Chấm đi muộn, góc TRÁI trên.
 *
 * Góc phải đã dành cho chấm thiếu báo cáo. Một ngày có thể vừa đi muộn vừa
 * chưa nộp báo cáo, nên hai dấu phải ở hai chỗ khác nhau — chồng lên nhau thì
 * một trong hai biến mất mà không ai biết.
 */
function ChamDiMuon({ excused }: { excused: boolean }) {
  return (
    <span
      aria-hidden="true"
      className={cn(
        "absolute top-1 left-1 size-1.5 rounded-full",
        excused ? "bg-ink-faint" : "bg-danger",
      )}
    />
  );
}

/**
 * Chấm nhỏ ở góc ô.
 *
 * Cố ý **không** đổi màu nền cả ô như cách đánh dấu lỗi thường làm. Quên nộp
 * báo cáo không phải vi phạm, và tô đỏ ba mươi ô sẽ biến bảng công thành bảng
 * buộc tội — đúng thứ chính sách "nhìn cho biết" của dự án tránh. Một chấm đủ
 * để mắt bắt được khi lướt, và đủ nhỏ để không phải lời kết tội.
 */
function ChamThieuBaoCao() {
  return (
    <span
      aria-hidden="true"
      className="bg-notice absolute top-1 right-1 size-1.5 rounded-full"
    />
  );
}

function nhanO(
  date: string,
  o: AttendanceCell | undefined,
  le: string | undefined,
): string {
  const phan = [formatDate(date)];

  if (le !== undefined) phan.push(le);
  if (o) {
    phan.push(`${formatMinutes(o.minutes)} · ${o.session_count} phiên`);
    // Nhãn đối chiếu đứng ngay cạnh số giờ — số giờ đơn độc không nói được
    // ngày đó đã có báo cáo hay chưa.
    phan.push(o.report_match_label);
    if (o.late_minutes > 0) {
      phan.push(
        o.late_excused
          ? `Đi muộn ${o.late_minutes} phút (có đơn đã duyệt)`
          : `Đi muộn ${o.late_minutes} phút`,
      );
    }
    if (o.decision_label) phan.push(o.decision_label);
  } else {
    phan.push("Không có hoạt động");
  }

  return phan.join(" — ");
}

/*
|--------------------------------------------------------------------------
| Chọn tháng
|--------------------------------------------------------------------------
*/

function ChonThang({
  value,
  onChange,
}: {
  value: string;
  onChange: (v: string) => void;
}) {
  // Mười hai tháng gần nhất là đủ: bảng công cũ hơn thế thì tra ở bản xuất
  // Excel của kế toán, không phải ở đây.
  const ds = Array.from({ length: 12 }, (_, i) => {
    const d = new Date();
    d.setDate(1);
    d.setMonth(d.getMonth() - i);

    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, "0")}`;
  });

  return (
    <div className="flex items-center gap-2">
      <label htmlFor="chon-thang" className="sr-only">
        Chọn tháng
      </label>
      <SelectInput
        id="chon-thang"
        className="w-40"
        value={value}
        onChange={(e) => onChange(e.target.value)}
      >
        {ds.map((m) => (
          <option key={m} value={m}>
            Tháng {m.slice(5)}/{m.slice(0, 4)}
          </option>
        ))}
      </SelectInput>

      {value !== ds[0] && (
        <Button size="sm" variant="ghost" onClick={() => onChange(ds[0] ?? "")}>
          Tháng này
        </Button>
      )}
    </div>
  );
}

function thangHienTai(): string {
  const d = new Date();

  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, "0")}`;
}

/**
 * Ngày hôm nay theo đồng hồ MÁY NGƯỜI DÙNG, chỉ dùng làm giá trị mặc định cho
 * ô chọn ngày.
 *
 * Chấp nhận được ở đây vì đây chỉ là gợi ý ban đầu, và người dùng đổi được
 * ngay. Server vẫn tự chốt lại ngày theo giờ Việt Nam khi tham số không hợp lệ
 * — xem AttendanceTimelineController.
 */
function homNay(): string {
  const d = new Date();

  return [
    d.getFullYear(),
    String(d.getMonth() + 1).padStart(2, "0"),
    String(d.getDate()).padStart(2, "0"),
  ].join("-");
}
