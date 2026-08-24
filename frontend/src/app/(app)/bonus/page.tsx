"use client";

import { useState } from "react";

import { SelectInput } from "@/components/ui/field";
import { EmptyState, ErrorState, Skeleton } from "@/components/ui/states";
import { useCurrentUser } from "@/features/auth/api/auth-api";
import { useMyBonus } from "@/features/bonus/api/bonus-api";
import { BonusPoolPanel } from "@/features/bonus/components/bonus-pool-panel";
import { formatMoney } from "@/features/payroll/types/payroll";
import { useProjects } from "@/features/projects/api/projects-api";

/**
 * Thưởng dự án.
 *
 * Hai tầng trong một trang: phần thưởng của chính mình (ai cũng thấy) và quỹ
 * thưởng theo dự án (chỉ người có quyền). Cùng khuôn với trang Chấm công và
 * trang Lương — người quản lý cũng là người có phần thưởng riêng của họ.
 *
 * **Không có chỗ nào nhập số âm, và đó là điểm chính của thiết kế.** Điều 127
 * Bộ luật Lao động 2019 cấm phạt tiền; giảm thưởng thì hợp lệ vì thưởng là
 * khoản có điều kiện. Muốn ai đó không nhận gì thì đặt 0.
 */
export default function BonusPage() {
  const { data: user } = useCurrentUser();
  const cuaToi = useMyBonus();

  const xemQuy = user?.permissions.includes("bonus.view.all") === true;

  // `per_page: 100` chứ không để mặc định 25.
  //
  // Bản đầu chỉ gọi `{ page: 1 }` và nhận đúng 25 dự án — công ty quá 25 thì
  // những dự án còn lại không chọn được, và không có gì báo. `meta.total` bên
  // dưới nói rõ nếu vẫn còn bị cắt.
  const { data: duAn } = useProjects({ status: "", page: 1, per_page: 100 });
  const [chon, setChon] = useState("");

  const thieuDuAn = duAn !== undefined && duAn.meta.total > duAn.data.length;

  return (
    <div data-tone="bonus" className="enter space-y-8">
      <header>
        <span
          aria-hidden="true"
          className="bg-tone mb-3 block h-[3px] w-9 rounded-full"
        />
        <h1 className="text-[1.65rem] leading-tight font-semibold tracking-[-0.035em]">
          Thưởng dự án
        </h1>
        <p className="text-ink-soft mt-1.5 text-[0.9rem]">
          Quỹ thưởng có điều kiện theo từng dự án. Không có khoản trừ — làm tốt
          thì phần chia lớn, làm ít thì phần chia nhỏ.
        </p>
      </header>

      {/* ── Của tôi ─────────────────────────────────── */}
      <section className="tone-card rounded-2xl p-5">
        <div className="mb-4 flex flex-wrap items-baseline justify-between gap-3">
          <h2 className="text-[0.95rem] font-semibold tracking-tight">
            Thưởng của tôi
          </h2>

          {cuaToi.data && (
            <p className="text-ink text-[1.05rem] font-semibold tabular-nums">
              {formatMoney(cuaToi.data.total)}
            </p>
          )}
        </div>

        {cuaToi.isPending && <Skeleton className="h-20" />}

        {cuaToi.isError && (
          <ErrorState
            error={cuaToi.error}
            onRetry={() => void cuaToi.refetch()}
          />
        )}

        {cuaToi.data && cuaToi.data.items.length === 0 && (
          <p className="border-line text-ink-faint rounded-xl border border-dashed px-4 py-6 text-[0.86rem]">
            Chưa có khoản thưởng nào. Quỹ đang lập chưa hiện ở đây — chỉ hiện
            sau khi người quản lý chốt.
          </p>
        )}

        {cuaToi.data && cuaToi.data.items.length > 0 && (
          <ul className="divide-line border-line divide-y rounded-xl border">
            {cuaToi.data.items.map((m) => (
              <li key={m.id} className="px-4 py-3">
                <div className="flex flex-wrap items-baseline justify-between gap-2">
                  <span className="text-[0.9rem] font-medium">{m.project}</span>
                  <span className="font-semibold tabular-nums">
                    {formatMoney(m.amount)}
                  </span>
                </div>

                {/* Lý do hiện cùng số tiền, có chủ ý: quỹ thưởng bí mật là
                    nguồn nghi ngờ lớn nhất. */}
                <p className="text-ink-soft mt-1 text-[0.82rem] leading-relaxed">
                  {m.reason}
                  {m.decided_by && (
                    <span className="text-ink-faint"> — {m.decided_by}</span>
                  )}
                </p>

                <p className="text-ink-faint mt-1 text-[0.76rem]">
                  {m.status_label}
                </p>
              </li>
            ))}
          </ul>
        )}
      </section>

      {/* ── Quỹ theo dự án ──────────────────────────── */}
      {xemQuy && (
        <section className="tone-card rounded-2xl p-5">
          <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
            <h2 className="text-[0.95rem] font-semibold tracking-tight">
              Quỹ thưởng theo dự án
            </h2>

            <div className="w-full sm:w-64">
              <label htmlFor="chon-du-an" className="sr-only">
                Chọn dự án
              </label>
              <SelectInput
                id="chon-du-an"
                value={chon}
                onChange={(e) => setChon(e.target.value)}
              >
                <option value="">Chọn dự án…</option>
                {duAn?.data.map((d) => (
                  <option key={d.id} value={d.id}>
                    {d.name}
                  </option>
                ))}
              </SelectInput>

              {thieuDuAn && (
                <p className="text-notice mt-1.5 text-[0.78rem]">
                  Đang hiện {duAn?.data.length} trên {duAn?.meta.total} dự án.
                  Mở quỹ từ chính trang dự án nếu không thấy ở đây.
                </p>
              )}
            </div>
          </div>

          {chon === "" ? (
            <EmptyState
              title="Chọn một dự án"
              description="Mỗi dự án có một quỹ thưởng riêng, mở khi dự án đạt điều kiện đã đặt ra."
            />
          ) : (
            <BonusPoolPanel key={chon} projectId={chon} />
          )}
        </section>
      )}
    </div>
  );
}
