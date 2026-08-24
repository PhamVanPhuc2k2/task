"use client";

import { useState } from "react";

import { Button } from "@/components/ui/button";
import { TextInput } from "@/components/ui/field";
import { Avatar } from "@/components/ui/pill";
import { EmptyState, ErrorState, Skeleton } from "@/components/ui/states";
import { useCurrentUser } from "@/features/auth/api/auth-api";
import {
  useMyReports,
  useReviewReport,
  useTeamReports,
} from "@/features/reports/api/reports-api";
import { ReportComposer } from "@/features/reports/components/report-composer";
import {
  todayInVietnam,
  type TeamReportRow,
} from "@/features/reports/types/report";
import { cn } from "@/lib/cn";
import { formatDate } from "@/lib/format";

/**
 * Báo cáo tiến độ hằng ngày.
 *
 * Hai tầng trong một trang, cùng khuôn với Chấm công / Lương / Thưởng: báo cáo
 * của chính mình (ai cũng thấy) và báo cáo cả phòng (chỉ người có quyền). Quản
 * lý cũng là người phải nộp báo cáo của riêng họ.
 *
 * Màn của quản lý trả lời **hai** câu chứ không phải một: *ai đã báo cáo gì* và
 * **ai chưa báo cáo** — câu thứ hai mới là câu khó, và là lý do báo cáo gắn vào
 * ngày chứ không gắn vào từng task.
 */
export default function ReportsPage() {
  const { data: user } = useCurrentUser();

  const [ngay, setNgay] = useState(() => todayInVietnam());
  const [thang] = useState(() => todayInVietnam().slice(0, 7));

  const xemDoi =
    user?.permissions.includes("report.view.team") === true ||
    user?.permissions.includes("task.view.all") === true;

  const cuaToi = useMyReports(thang);
  const cuaDoi = useTeamReports(ngay, xemDoi);

  const homNay = cuaToi.data?.reports.find((r) => r.report_date === ngay);

  return (
    <div data-tone="report" className="enter space-y-8">
      <header className="flex flex-wrap items-end justify-between gap-4">
        <div>
          <span
            aria-hidden="true"
            className="bg-tone mb-3 block h-[3px] w-9 rounded-full"
          />
          <h1 className="text-[1.65rem] leading-tight font-semibold tracking-[-0.035em]">
            Báo cáo ngày
          </h1>
          <p className="text-ink-soft mt-1.5 text-[0.9rem]">
            Kể lại việc đã làm trong ngày. Đây là thứ xác nhận thật cho bảng
            công — giờ online mà không có báo cáo thì con số giờ không nói lên
            gì.
          </p>
        </div>

        <div className="w-44">
          <label htmlFor="chon-ngay-bc" className="sr-only">
            Chọn ngày
          </label>
          <TextInput
            id="chon-ngay-bc"
            type="date"
            value={ngay}
            max={todayInVietnam()}
            onChange={(e) => setNgay(e.target.value)}
          />
        </div>
      </header>

      {/* ── Của tôi ─────────────────────────────────── */}
      <section className="tone-card rounded-2xl p-5">
        <div className="mb-4 flex flex-wrap items-baseline justify-between gap-3">
          <h2 className="text-[0.95rem] font-semibold tracking-tight">
            Báo cáo của tôi
          </h2>

          {cuaToi.data && (
            <p className="text-ink-soft text-[0.85rem]">
              Đã nộp {cuaToi.data.submitted_count} báo cáo trong tháng
            </p>
          )}
        </div>

        {cuaToi.isPending && <Skeleton className="h-40" />}

        {cuaToi.isError && (
          <ErrorState
            error={cuaToi.error}
            onRetry={() => void cuaToi.refetch()}
          />
        )}

        {cuaToi.data && (
          <>
            <ReportComposer
              key={`${ngay}:${homNay?.status ?? "new"}`}
              date={ngay}
              existing={homNay}
              window={cuaToi.data.window}
            />

            {homNay?.review?.note && (
              <p className="border-notice-line bg-notice-surface text-notice mt-4 rounded-xl border px-4 py-3 text-[0.86rem] leading-relaxed">
                <strong className="font-semibold">
                  {homNay.review.by} nhận xét:
                </strong>{" "}
                {homNay.review.note}
              </p>
            )}

            {cuaToi.data.reports.length > 0 && (
              <div className="border-line mt-6 border-t pt-4">
                <h3 className="text-ink-soft mb-2 text-[0.82rem] font-medium">
                  Các ngày khác trong tháng
                </h3>
                <ul className="flex flex-wrap gap-1.5">
                  {cuaToi.data.reports
                    .filter((r) => r.report_date !== ngay)
                    .map((r) => (
                      <li key={r.id}>
                        <button
                          type="button"
                          onClick={() => setNgay(r.report_date)}
                          className={cn(
                            "focus-frame rounded-lg border px-2.5 py-1 text-[0.78rem] transition-colors",
                            r.status === "draft"
                              ? "border-notice-line bg-notice-surface text-notice"
                              : "border-line bg-paper-sunken text-ink-soft hover:border-line-strong",
                          )}
                        >
                          {r.report_date.slice(8)}/{r.report_date.slice(5, 7)}
                          {r.status === "draft" && " · nháp"}
                        </button>
                      </li>
                    ))}
                </ul>
              </div>
            )}
          </>
        )}
      </section>

      {/* ── Của đội ─────────────────────────────────── */}
      {xemDoi && (
        <section className="tone-card rounded-2xl p-5">
          <div className="mb-4 flex flex-wrap items-baseline justify-between gap-3">
            <h2 className="text-[0.95rem] font-semibold tracking-tight">
              Báo cáo của đội — {formatDate(ngay)}
            </h2>

            {cuaDoi.data && (
              <p className="text-ink-soft text-[0.85rem] tabular-nums">
                <strong className="text-ink font-semibold">
                  {cuaDoi.data.submitted}
                </strong>
                /{cuaDoi.data.total} người đã nộp
              </p>
            )}
          </div>

          {cuaDoi.isPending && <Skeleton className="h-48" />}

          {cuaDoi.isError && (
            <ErrorState
              error={cuaDoi.error}
              onRetry={() => void cuaDoi.refetch()}
            />
          )}

          {cuaDoi.data && cuaDoi.data.rows.length === 0 && (
            <EmptyState
              title="Chưa có nhân sự nào trong phạm vi của bạn"
              description="Báo cáo của phòng ban bạn quản lý sẽ hiện ở đây."
            />
          )}

          {cuaDoi.data && cuaDoi.data.rows.length > 0 && (
            <ul className="divide-line divide-y">
              {cuaDoi.data.rows.map((dong) => (
                <DongBaoCao
                  key={dong.user.id}
                  row={dong}
                  canReview={cuaDoi.data.can_review}
                  laToi={dong.user.id === user?.id}
                />
              ))}
            </ul>
          )}
        </section>
      )}
    </div>
  );
}

/*
|--------------------------------------------------------------------------
| Một dòng trong danh sách của quản lý
|--------------------------------------------------------------------------
*/

function DongBaoCao({
  row,
  canReview,
  laToi,
}: {
  row: TeamReportRow;
  canReview: boolean;
  laToi: boolean;
}) {
  const [moNhanXet, setMoNhanXet] = useState(false);
  const [nhanXet, setNhanXet] = useState("");
  const doc = useReviewReport();

  return (
    <li className="py-4 first:pt-0 last:pb-0">
      <div className="flex items-start gap-3">
        <Avatar name={row.user.name} size="sm" />

        <div className="min-w-0 flex-1">
          <p className="flex flex-wrap items-center gap-2">
            <span className="text-[0.88rem] font-medium">{row.user.name}</span>

            {row.report === null ? (
              // Cố ý không dùng màu đỏ: chưa báo cáo không phải lỗi — có thể
              // đang nghỉ phép, đi công tác, hoặc chưa tới cuối ngày.
              <span className="text-notice bg-notice-surface border-notice-line rounded-full border px-2 py-0.5 text-[0.72rem] font-medium">
                {row.has_draft ? "Còn là bản nháp" : "Chưa nộp"}
              </span>
            ) : (
              <span className="text-ink-faint text-[0.74rem]">
                {row.report.status_label}
              </span>
            )}
          </p>

          {row.report && (
            <>
              <p className="text-ink-soft mt-1.5 text-[0.86rem] leading-relaxed whitespace-pre-wrap">
                {row.report.content}
              </p>

              {row.report.tasks.length > 0 && (
                <ul className="mt-2 flex flex-wrap gap-1.5">
                  {row.report.tasks.map((t) => (
                    <li
                      key={t.id}
                      className="border-line bg-paper-sunken text-ink-faint max-w-full truncate rounded-full border px-2.5 py-0.5 text-[0.74rem]"
                    >
                      {t.title}
                    </li>
                  ))}
                </ul>
              )}

              {row.report.review?.note && (
                <p className="text-ink-faint mt-2 text-[0.8rem]">
                  {row.report.review.by} đã nhận xét: {row.report.review.note}
                </p>
              )}

              {/* Không tự đánh dấu đã đọc báo cáo của chính mình — backend
                  cũng chặn, ẩn nút để khỏi bấm rồi mới biết. */}
              {canReview && !laToi && row.report.status !== "reviewed" && (
                <div className="mt-3">
                  {moNhanXet ? (
                    <div className="space-y-2">
                      <TextInput
                        value={nhanXet}
                        placeholder="Nhận xét hoặc hỏi lại (không bắt buộc)"
                        onChange={(e) => setNhanXet(e.target.value)}
                      />
                      <div className="flex gap-2">
                        <Button
                          size="sm"
                          variant="primary"
                          loading={doc.isPending}
                          onClick={() =>
                            doc.mutate({
                              reportId: row.report?.id ?? "",
                              note: nhanXet.trim(),
                            })
                          }
                        >
                          Gửi
                        </Button>
                        <Button
                          size="sm"
                          variant="ghost"
                          onClick={() => setMoNhanXet(false)}
                        >
                          Huỷ
                        </Button>
                      </div>
                    </div>
                  ) : (
                    <div className="flex gap-2">
                      <Button
                        size="sm"
                        loading={doc.isPending}
                        onClick={() =>
                          doc.mutate({
                            reportId: row.report?.id ?? "",
                            note: "",
                          })
                        }
                      >
                        Đã đọc
                      </Button>
                      <Button
                        size="sm"
                        variant="ghost"
                        onClick={() => setMoNhanXet(true)}
                      >
                        Nhận xét
                      </Button>
                    </div>
                  )}
                </div>
              )}
            </>
          )}
        </div>
      </div>
    </li>
  );
}
