"use client";

import { useState } from "react";

import { Button } from "@/components/ui/button";
import { Field, TextArea } from "@/components/ui/field";
import { useSaveReport } from "@/features/reports/api/reports-api";
import type { DailyReport } from "@/features/reports/types/report";
import { useMyTasks } from "@/features/tasks/api/tasks-api";
import type { Task } from "@/features/tasks/types/task";
import { cn } from "@/lib/cn";
import { formatDate } from "@/lib/format";

/**
 * Ô viết báo cáo cho một ngày.
 *
 * Danh sách task lấy từ **việc đang mở của chính mình** (`/tasks/my`), không
 * phải toàn bộ task xem được: người viết báo cáo cuối ngày cần tích nhanh vài
 * việc mình vừa làm, không cần lục danh bạ công việc cả công ty.
 *
 * Gắn task là **tuỳ chọn** — người họp cả ngày hoặc hỗ trợ đồng nghiệp vẫn nộp
 * được mà không tích gì. Bắt buộc phải có task là ràng buộc khiến người ta bịa
 * ra một việc để nộp cho xong.
 */
export function ReportComposer({
  date,
  existing,
  window: khoang,
}: {
  date: string;
  existing?: DailyReport;
  /** Khoảng ngày server còn nhận. Xem `MyReports["window"]`. */
  window?: { earliest: string; latest: string };
}) {
  const luu = useSaveReport();
  const { data: viecCuaToi } = useMyTasks();

  const [noiDung, setNoiDung] = useState(existing?.content ?? "");
  const [chon, setChon] = useState<string[]>(
    () => existing?.tasks.map((t) => t.id) ?? [],
  );

  // Ngoài khoảng nộp bù thì đóng hẳn ô soạn. Để mở rồi trả 422 sau khi người ta
  // gõ xong vài trăm chữ là cách chắc chắn nhất để mất công của họ.
  const trongKhoang =
    khoang === undefined || (date >= khoang.earliest && date <= khoang.latest);

  const suaDuoc = (existing?.is_editable ?? true) && trongKhoang;

  // Bốn nhóm của "Hôm nay của tôi" gộp lại thành một danh sách phẳng để tích.
  const dsTask: Task[] = viecCuaToi ? Object.values(viecCuaToi).flat() : [];

  function gui(nop: boolean) {
    luu.mutate({
      report_date: date,
      content: noiDung.trim(),
      task_ids: chon,
      submit: nop,
    });
  }

  if (!suaDuoc) {
    return (
      <div className="border-line bg-paper-sunken rounded-xl border px-4 py-3">
        <p className="text-ink-soft text-[0.86rem]">
          {!trongKhoang
            ? `Ngày này đã ngoài hạn nộp bù (chỉ nộp được từ ${formatDate(khoang?.earliest ?? "")}). Cần bổ sung thì trao đổi với quản lý.`
            : "Quản lý đã đọc báo cáo này nên không sửa được nữa. Cần bổ sung thì trao đổi trực tiếp."}
        </p>
      </div>
    );
  }

  return (
    <div className="space-y-4">
      <Field
        label={`Báo cáo ngày ${formatDate(date)}`}
        required
        hint="Kể lại việc đã làm, việc đang vướng, và việc định làm tiếp."
        error={luu.error?.fieldError("content")}
      >
        {(id, describedBy) => (
          <TextArea
            id={id}
            rows={4}
            aria-describedby={describedBy}
            value={noiDung}
            placeholder="Hoàn thành phần đăng nhập, họp với khách hàng buổi chiều. Đang vướng phần gửi email, mai hỏi lại anh Tuấn."
            onChange={(e) => setNoiDung(e.target.value)}
          />
        )}
      </Field>

      {dsTask.length > 0 && (
        <div>
          <p className="text-ink-soft mb-2 text-[0.82rem] font-medium">
            Việc đã đụng tới hôm nay{" "}
            <span className="text-ink-faint font-normal">— không bắt buộc</span>
          </p>

          <div className="flex flex-wrap gap-2">
            {dsTask.map((t) => {
              const daChon = chon.includes(t.id);

              return (
                <button
                  key={t.id}
                  type="button"
                  onClick={() =>
                    setChon(
                      daChon ? chon.filter((x) => x !== t.id) : [...chon, t.id],
                    )
                  }
                  className={cn(
                    "focus-frame max-w-full truncate rounded-full border px-3 py-1 text-[0.8rem] transition-colors",
                    daChon
                      ? "border-accent-line bg-accent-surface text-accent-ink font-medium"
                      : "border-line bg-paper-raised text-ink-soft hover:border-line-strong",
                  )}
                >
                  {t.title}
                </button>
              );
            })}
          </div>
        </div>
      )}

      {luu.error && !luu.error.errors && (
        <p role="alert" className="text-danger text-[0.84rem]">
          {luu.error.message}
        </p>
      )}

      <div className="flex flex-wrap gap-2">
        <Button
          variant="primary"
          loading={luu.isPending}
          disabled={noiDung.trim().length < 10}
          onClick={() => gui(true)}
        >
          {existing?.status === "submitted"
            ? "Cập nhật báo cáo"
            : "Nộp báo cáo"}
        </Button>

        {/* Nháp vẫn giữ được sau khi nộp — sửa lại rồi lưu nháp là hạ trạng
            thái, nên chỉ hiện khi chưa nộp. */}
        {existing?.status !== "submitted" && (
          <Button
            variant="ghost"
            loading={luu.isPending}
            disabled={noiDung.trim() === ""}
            onClick={() => gui(false)}
          >
            Lưu nháp
          </Button>
        )}
      </div>
    </div>
  );
}
