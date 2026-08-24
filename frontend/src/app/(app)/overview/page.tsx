"use client";

import Link from "next/link";

import { Avatar } from "@/components/ui/pill";
import { EmptyState, ErrorState, Skeleton } from "@/components/ui/states";
import { useCurrentUser } from "@/features/auth/api/auth-api";
import { useOverview } from "@/features/dashboard/api/overview-api";
import type {
  OverdueTask,
  OverviewSummary,
  ProjectProgressRow,
  WorkloadRow,
} from "@/features/dashboard/types/overview";
import { cn } from "@/lib/cn";
import { formatDate, formatLongDate } from "@/lib/format";

/**
 * Tổng quan toàn công ty — màn hình của quản trị viên và giám đốc.
 *
 * Trả lời hai câu hỏi mà trước đây không màn nào trả lời được: *đang có dự án
 * nào, tiến độ tới đâu* và *ai đang làm việc gì, ai đang quá tải*.
 *
 * **Không dùng thư viện biểu đồ.** Với quy mô một công ty vài trăm người, biểu
 * đồ tròn chia sáu trạng thái task không giúp ai quyết định điều gì. Thứ có ích
 * là bảng có thanh đo ngay trong dòng — nhìn một giây là thấy ai lệch tải. Thanh
 * đo dựng bằng Tailwind, không tốn thêm KB nào.
 */
export default function OverviewPage() {
  const { data: user } = useCurrentUser();

  // Chỉ gọi API khi đã biết người dùng có quyền. Gọi trước rồi ăn 403 là một
  // lượt request bỏ đi cộng một dòng đỏ trong console không có ý nghĩa gì.
  const duocXem = user?.permissions.includes("task.view.all") === true;
  const { data, isPending, isError, error, refetch } = useOverview(duocXem);

  if (user && !duocXem) {
    return (
      <EmptyState
        title="Trang này dành cho quản trị viên và giám đốc"
        description="Bạn không có quyền xem số liệu toàn công ty. Việc của bạn nằm ở màn Hôm nay của tôi."
      />
    );
  }

  return (
    <div data-tone="all" className="enter space-y-8">
      <header>
        <p className="text-ink-faint mb-1 text-[0.82rem]">{formatLongDate()}</p>
        <span
          aria-hidden="true"
          className="bg-tone mb-3 block h-[3px] w-9 rounded-full"
        />
        <h1 className="text-[1.9rem] leading-tight font-semibold tracking-[-0.035em] sm:text-[2.2rem]">
          Tổng quan
        </h1>
        <p className="text-ink-soft mt-2 text-[0.92rem]">
          Tình hình công việc và dự án của toàn công ty.
        </p>
      </header>

      {(isPending || !user) && <KhungXuong />}

      {isError && <ErrorState error={error} onRetry={() => void refetch()} />}

      {data && (
        <>
          <ConSoChinh summary={data.summary} />

          <div className="grid gap-5 xl:grid-cols-2">
            <TaiViec
              rows={data.workload.rows}
              total={data.workload.total}
              rong={data.summary.open_tasks === 0}
            />
            <TienDoDuAn rows={data.projects.rows} total={data.projects.total} />
          </div>

          <TreLauNhat tasks={data.most_overdue} />
        </>
      )}
    </div>
  );
}

/*
|--------------------------------------------------------------------------
| Các con số chính
|--------------------------------------------------------------------------
*/

function ConSoChinh({ summary }: { summary: OverviewSummary }) {
  return (
    <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
      <O nhan="Việc đang mở" so={summary.open_tasks} den="/tasks?open=1" />
      <O
        nhan="Quá hạn"
        so={summary.overdue_tasks}
        tone="danger"
        den="/tasks?overdue=1"
      />
      <O
        nhan="Hạn hôm nay"
        so={summary.due_today}
        tone="notice"
        den="/tasks?due_today=1"
      />
      {/* Việc chưa giao là thứ dễ trôi nhất: không ai nhận thông báo nhắc hạn,
          không xuất hiện trong "việc của tôi" của bất kỳ ai. */}
      <O
        nhan="Chưa giao ai"
        so={summary.unassigned_tasks}
        tone="notice"
        den="/tasks?unassigned=1"
      />
      <O
        nhan="Xong tuần này"
        so={summary.completed_this_week}
        tone="tot"
        den="/tasks?completed_this_week=1"
      />
      <O nhan="Dự án đang chạy" so={summary.active_projects} den="/projects" />
      <O
        nhan="Nhân sự đang làm"
        so={summary.active_employees}
        den="/employees"
      />
    </div>
  );
}

const O_TONE = {
  thuong: "border-line bg-paper-raised",
  danger: "border-danger-line bg-danger-surface",
  notice: "border-notice-line bg-notice-surface",
  tot: "border-accent-line bg-accent-surface",
} as const;

const SO_TONE = {
  thuong: "text-ink",
  danger: "text-danger",
  notice: "text-notice",
  tot: "text-accent-ink",
} as const;

function O({
  nhan,
  so,
  tone = "thuong",
  den,
}: {
  nhan: string;
  so: number;
  tone?: keyof typeof O_TONE;
  /** Nơi bấm vào sẽ tới — danh sách đã lọc sẵn đúng con số này. */
  den: string;
}) {
  // Số 0 thì về tông trung tính. Một ô đỏ ghi "0 quá hạn" là báo động giả —
  // màu ở đây phải mang nghĩa "cần để ý", không phải "đây là loại số gì".
  const t = so === 0 ? "thuong" : tone;

  const noiDung = (
    <>
      {/*
        Con số là lý do người ta mở trang này, nên nó là thứ to nhất trên màn
        hình. Bản trước để 1,7rem — đọc như một dòng chữ thường; giờ 2,6rem với
        nét đậm và chữ bó sát, nhìn một cái là thấy.
      */}
      <p className={cn("figure text-[2.6rem]", SO_TONE[t])}>{so}</p>
      <p className="text-ink-soft mt-2.5 text-[0.82rem] font-medium">{nhan}</p>
    </>
  );

  const lop = cn(
    "block rounded-2xl border p-4 pt-5",
    O_TONE[t],
    t === "thuong" && "shadow-card",
  );

  /*
   * Số 0 thì KHÔNG bấm được.
   *
   * Bấm vào "0 việc quá hạn" chỉ dẫn tới một danh sách rỗng — một cú bấm bị
   * phí, và tệ hơn là một liên kết nói dối rằng có gì đó để xem. Ô vẫn hiện
   * bình thường, chỉ là không mời bấm nữa.
   */
  if (so === 0) {
    return <div className={lop}>{noiDung}</div>;
  }

  return (
    <Link
      href={den}
      // `group` để mũi tên hiện khi rê chuột; `lift` nhấc thẻ lên 1px.
      className={cn(lop, "lift focus-frame group relative")}
      aria-label={`${nhan}: ${so}. Mở danh sách`}
    >
      {noiDung}

      {/*
        Mũi tên chỉ hiện khi rê chuột hoặc khi focus bằng bàn phím.
        Hiện thường trực trên bảy ô là bảy mũi tên chen vào giữa các con số —
        thứ người ta đến đây để đọc.
      */}
      <span
        aria-hidden="true"
        className="text-ink-faint absolute top-3.5 right-3.5 opacity-0 transition-opacity group-hover:opacity-100 group-focus-visible:opacity-100"
      >
        <svg
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          strokeWidth="2"
          strokeLinecap="round"
          strokeLinejoin="round"
          className="size-3.5"
        >
          <path d="M7 17 17 7M9 7h8v8" />
        </svg>
      </span>
    </Link>
  );
}

/*
|--------------------------------------------------------------------------
| Tải việc theo người
|--------------------------------------------------------------------------
*/

function TaiViec({
  rows,
  total,
  rong,
}: {
  rows: WorkloadRow[];
  total: number;
  rong: boolean;
}) {
  // Thanh đo tính theo người ôm nhiều việc nhất, không theo một mốc cố định:
  // mục đích ở đây là so sánh giữa người với người, không phải đo tuyệt đối.
  const nhieuNhat = Math.max(...rows.map((r) => r.open), 1);

  return (
    <Khoi
      tieuDe="Ai đang làm việc gì"
      phu={
        total > rows.length ? `${rows.length} trên ${total} người` : undefined
      }
    >
      {rows.length === 0 ? (
        <p className="text-ink-faint px-1 py-6 text-[0.86rem]">
          {rong
            ? "Chưa có việc nào đang mở trong hệ thống."
            : "Việc đang mở đều chưa giao cho ai."}
        </p>
      ) : (
        <ul className="space-y-3.5">
          {rows.map((r) => (
            <li key={r.id}>
              {/*
                Bấm vào một người mở đúng danh sách việc đang mở của họ. `id` ở
                đây là uuid — cùng thứ mà bộ lọc `assignee_id` nhận — nên không
                phải tra thêm lần nào.

                `open=1` đi kèm để danh sách khớp với con số đang hiện: cột này
                đếm việc ĐANG MỞ, không đếm việc đã xong.
              */}
              <Link
                href={`/tasks?assignee_id=${r.id}&open=1`}
                className="focus-frame hover:bg-paper-sunken -mx-2 flex items-center gap-3 rounded-xl px-2 py-1.5 transition-colors"
              >
                <Avatar name={r.name} size="sm" />

                <div className="min-w-0 flex-1">
                  <div className="flex items-baseline justify-between gap-3">
                    <p className="truncate text-[0.88rem] font-medium">
                      {r.name}
                      {r.department && (
                        <span className="text-ink-faint ml-2 text-[0.76rem] font-normal">
                          {r.department}
                        </span>
                      )}
                    </p>

                    <p className="text-ink-soft shrink-0 text-[0.8rem] tabular-nums">
                      {r.overdue > 0 && (
                        <span className="text-danger font-semibold">
                          {r.overdue} trễ
                        </span>
                      )}
                      {r.overdue > 0 && " · "}
                      {r.open} việc
                    </p>
                  </div>

                  {/* Thanh chồng hai đoạn: phần đỏ là việc đã trễ nằm TRONG tổng
                    việc đang mở, không phải cộng thêm. Nhìn tỉ lệ đỏ trên thanh
                    là biết người đó đang ngập tới đâu. */}
                  <div className="bg-paper-sunken mt-1.5 flex h-2 overflow-hidden rounded-full">
                    <span
                      className="bg-danger h-full"
                      style={{ width: `${(r.overdue / nhieuNhat) * 100}%` }}
                    />
                    <span
                      className="bg-accent h-full"
                      style={{
                        width: `${((r.open - r.overdue) / nhieuNhat) * 100}%`,
                      }}
                    />
                  </div>
                </div>
              </Link>
            </li>
          ))}
        </ul>
      )}
    </Khoi>
  );
}

/*
|--------------------------------------------------------------------------
| Tiến độ dự án
|--------------------------------------------------------------------------
*/

function TienDoDuAn({
  rows,
  total,
}: {
  rows: ProjectProgressRow[];
  total: number;
}) {
  return (
    <Khoi
      tieuDe="Dự án và tiến độ"
      phu={
        total > rows.length ? `${rows.length} trên ${total} dự án` : undefined
      }
    >
      {rows.length === 0 ? (
        <p className="text-ink-faint px-1 py-6 text-[0.86rem]">
          Chưa có dự án nào.
        </p>
      ) : (
        <ul className="space-y-3.5">
          {rows.map((d) => (
            <li key={d.id}>
              <Link
                href={`/projects/${d.id}`}
                className="focus-frame hover:bg-paper-sunken -mx-2 block rounded-xl px-2 py-1.5 transition-colors"
              >
                <div className="flex items-baseline justify-between gap-3">
                  <p className="truncate text-[0.88rem] font-medium">
                    {d.name}
                    <span className="text-ink-faint ml-2 text-[0.76rem] font-normal">
                      {d.status.label}
                    </span>
                  </p>

                  <p className="text-ink-soft shrink-0 text-[0.8rem] tabular-nums">
                    {d.overdue > 0 && (
                      <span className="text-danger font-semibold">
                        {d.overdue} trễ
                      </span>
                    )}
                    {d.overdue > 0 && " · "}
                    {d.done}/{d.total}
                  </p>
                </div>

                <div className="mt-1.5 flex items-center gap-2.5">
                  <div className="bg-paper-sunken h-2 flex-1 overflow-hidden rounded-full">
                    <span
                      className="bg-accent block h-full rounded-full"
                      style={{ width: `${d.progress_percent}%` }}
                    />
                  </div>
                  <span className="text-ink-faint w-9 shrink-0 text-right text-[0.76rem] tabular-nums">
                    {d.progress_percent}%
                  </span>
                </div>
              </Link>
            </li>
          ))}
        </ul>
      )}
    </Khoi>
  );
}

/*
|--------------------------------------------------------------------------
| Việc trễ lâu nhất
|--------------------------------------------------------------------------
*/

function TreLauNhat({ tasks }: { tasks: OverdueTask[] }) {
  if (tasks.length === 0) {
    return (
      <Khoi tieuDe="Việc trễ lâu nhất">
        <p className="text-ink-faint px-1 py-6 text-[0.86rem]">
          Không có việc nào quá hạn. Hiếm khi được vậy.
        </p>
      </Khoi>
    );
  }

  return (
    <Khoi tieuDe="Việc trễ lâu nhất">
      <ul className="divide-line divide-y">
        {tasks.map((t) => (
          <li key={t.id} className="first:pt-0 last:pb-0">
            <Link
              href={`/tasks/${t.id}`}
              className="focus-frame hover:bg-paper-sunken -mx-2 flex flex-wrap items-center gap-x-3 gap-y-1 rounded-xl px-2 py-2.5 transition-colors"
            >
              <span className="bg-danger-surface text-danger border-danger-line shrink-0 rounded-md border px-2 py-0.5 text-[0.76rem] font-semibold tabular-nums">
                {t.days_overdue} ngày
              </span>

              <span className="min-w-0 flex-1 truncate text-[0.88rem] font-medium">
                {t.title}
              </span>

              <span className="text-ink-faint text-[0.78rem]">
                {t.assignee ?? "Chưa giao ai"}
                {t.project && ` · ${t.project}`}
                {t.due_date && ` · hạn ${formatDate(t.due_date)}`}
              </span>
            </Link>
          </li>
        ))}
      </ul>
    </Khoi>
  );
}

/*
|--------------------------------------------------------------------------
| Khung dùng chung
|--------------------------------------------------------------------------
*/

function Khoi({
  tieuDe,
  phu,
  children,
}: {
  tieuDe: string;
  phu?: string;
  children: React.ReactNode;
}) {
  return (
    <section className="tone-card rounded-2xl p-5">
      <div className="mb-4 flex items-baseline justify-between gap-3">
        <h2 className="text-[0.95rem] font-semibold tracking-tight">
          {tieuDe}
        </h2>
        {/* Nói rõ bảng đang bị cắt. Hiện 12 dòng trông như toàn bộ công ty
            trong khi còn người nữa đang ôm việc trễ là kiểu nói dối khó chịu. */}
        {phu && <span className="text-ink-faint text-[0.78rem]">{phu}</span>}
      </div>

      {children}
    </section>
  );
}

function KhungXuong() {
  return (
    <div className="space-y-8" role="status" aria-label="Đang tải">
      <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
        {Array.from({ length: 5 }, (_, i) => (
          <Skeleton key={i} className="h-[5.5rem]" />
        ))}
      </div>
      <div className="grid gap-5 xl:grid-cols-2">
        <Skeleton className="h-72" />
        <Skeleton className="h-72" />
      </div>
    </div>
  );
}
