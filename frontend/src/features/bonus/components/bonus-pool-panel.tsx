"use client";

import { useState } from "react";

import { Button } from "@/components/ui/button";
import { Field, SelectInput, TextArea, TextInput } from "@/components/ui/field";
import { ErrorState, Skeleton } from "@/components/ui/states";
import {
  useAllocateBonus,
  useChangePoolStatus,
  useProjectBonus,
  useSaveBonusPool,
  type AllocationInput,
} from "@/features/bonus/api/bonus-api";
import type { BonusPool } from "@/features/bonus/types/bonus";
import { formatMoney } from "@/features/payroll/types/payroll";
import { useAssignableUsers } from "@/features/users/api/directory-api";
import { cn } from "@/lib/cn";

/**
 * Quỹ thưởng của một dự án: lập quỹ, chia cho từng người, chốt.
 *
 * **Không có ô nào nhập số âm.** Muốn ai đó không nhận gì thì đặt 0 — đó là
 * giảm thưởng, hợp pháp. Trừ vào thu nhập vì làm sai là phạt tiền, mà Điều 127
 * Bộ luật Lao động 2019 nghiêm cấm.
 */
export function BonusPoolPanel({ projectId }: { projectId: string }) {
  const { data, isPending, isError, error, refetch } = useProjectBonus(
    projectId,
    true,
  );

  if (isPending) return <Skeleton className="h-64" />;
  if (isError) {
    return <ErrorState error={error} onRetry={() => void refetch()} />;
  }
  if (!data) return null;

  return (
    <div className="space-y-5">
      {data.data === null ? (
        <LapQuy projectId={projectId} canManage={data.meta.can_manage} />
      ) : (
        <QuyDaCo
          projectId={projectId}
          pool={data.data}
          canManage={data.meta.can_manage}
        />
      )}
    </div>
  );
}

/*
|--------------------------------------------------------------------------
| Chưa có quỹ
|--------------------------------------------------------------------------
*/

function LapQuy({
  projectId,
  canManage,
}: {
  projectId: string;
  canManage: boolean;
}) {
  const luu = useSaveBonusPool();
  const [tong, setTong] = useState("");
  const [dieuKien, setDieuKien] = useState("");

  if (!canManage) {
    return (
      <p className="border-line text-ink-faint rounded-xl border border-dashed px-4 py-6 text-[0.86rem]">
        Dự án này chưa lập quỹ thưởng.
      </p>
    );
  }

  return (
    <div className="space-y-4">
      <p className="text-ink-soft text-[0.86rem]">
        Dự án này chưa có quỹ thưởng. Lập quỹ để chia cho những người tham gia.
      </p>

      <Field
        label="Tổng quỹ"
        required
        hint="Đơn vị: đồng."
        error={luu.error?.fieldError("total_amount")}
      >
        {(id, describedBy) => (
          <TextInput
            id={id}
            inputMode="numeric"
            aria-describedby={describedBy}
            value={tong}
            placeholder="50000000"
            onChange={(e) => setTong(e.target.value)}
          />
        )}
      </Field>

      <Field
        label="Điều kiện mở quỹ"
        required
        hint="Ghi bằng lời, không phải công thức — điều kiện thưởng là quyết định kinh doanh."
        error={luu.error?.fieldError("condition_note")}
      >
        {(id, describedBy) => (
          <TextArea
            id={id}
            rows={2}
            aria-describedby={describedBy}
            value={dieuKien}
            placeholder="Dự án nghiệm thu đúng hạn và khách hàng thanh toán đủ."
            onChange={(e) => setDieuKien(e.target.value)}
          />
        )}
      </Field>

      <Button
        variant="primary"
        loading={luu.isPending}
        disabled={tong.trim() === "" || dieuKien.trim().length < 5}
        onClick={() =>
          luu.mutate({
            projectId,
            total_amount: tong.replaceAll(".", "").trim(),
            condition_note: dieuKien.trim(),
          })
        }
      >
        Lập quỹ thưởng
      </Button>
    </div>
  );
}

/*
|--------------------------------------------------------------------------
| Quỹ đã có
|--------------------------------------------------------------------------
*/

function QuyDaCo({
  projectId,
  pool,
  canManage,
}: {
  projectId: string;
  pool: BonusPool;
  canManage: boolean;
}) {
  const { data: danhBa } = useAssignableUsers();
  const nguoi = danhBa?.people;
  const chia = useAllocateBonus();
  const doiTrangThai = useChangePoolStatus();

  const [dong, setDong] = useState<AllocationInput[]>(() =>
    (pool.allocations ?? []).map((p) => ({
      user_id: p.user.id ?? "",
      amount: p.amount,
      reason: p.reason,
    })),
  );

  const suaDuoc = canManage && pool.is_editable;

  return (
    <div className="space-y-5">
      {/* ── Số liệu quỹ ─────────────────────────── */}
      <div className="grid grid-cols-3 gap-3">
        <O nhan="Tổng quỹ" gia={formatMoney(pool.total_amount)} />
        <O nhan="Đã chia" gia={formatMoney(pool.allocated_total)} />
        <O
          nhan="Còn lại"
          gia={formatMoney(pool.remaining)}
          canhBao={pool.remaining === "0.00"}
        />
      </div>

      <p className="text-ink-soft text-[0.84rem] leading-relaxed">
        <span className="text-ink font-medium">{pool.status_label}</span> ·{" "}
        {pool.condition_note}
      </p>

      {!pool.is_editable && (
        <p className="border-notice-line bg-notice-surface text-notice rounded-xl border px-4 py-2.5 text-[0.84rem]">
          Quỹ đã chốt — nhân viên đã xem được phần của mình, nên không sửa được
          nữa, kể cả để tăng.
        </p>
      )}

      {/* ── Danh sách chia ──────────────────────── */}
      <section>
        <h3 className="text-ink-soft mb-2 text-[0.82rem] font-medium">
          Phần chia
        </h3>

        {dong.length === 0 ? (
          <p className="border-line text-ink-faint rounded-xl border border-dashed px-4 py-5 text-[0.84rem]">
            Chưa chia cho ai.
          </p>
        ) : (
          <ul className="space-y-2.5">
            {dong.map((d, i) => (
              <li
                key={`${d.user_id}-${i}`}
                className="border-line bg-paper-sunken rounded-xl border p-3"
              >
                <div className="grid gap-2.5 sm:grid-cols-[1fr_10rem]">
                  <SelectInput
                    aria-label="Nhân viên"
                    value={d.user_id}
                    disabled={!suaDuoc}
                    onChange={(e) =>
                      setDong(capNhat(dong, i, { user_id: e.target.value }))
                    }
                  >
                    <option value="">Chọn người…</option>
                    {nguoi?.map((n) => (
                      <option key={n.id} value={n.id}>
                        {n.name}
                      </option>
                    ))}
                  </SelectInput>

                  <TextInput
                    aria-label="Số tiền"
                    inputMode="numeric"
                    // `min=0` — không có đường nhập số âm. Muốn ai đó không
                    // nhận gì thì đặt 0.
                    min={0}
                    value={d.amount}
                    disabled={!suaDuoc}
                    placeholder="0"
                    onChange={(e) =>
                      setDong(capNhat(dong, i, { amount: e.target.value }))
                    }
                  />
                </div>

                <TextArea
                  aria-label="Lý do"
                  rows={1}
                  className="mt-2.5"
                  value={d.reason}
                  disabled={!suaDuoc}
                  placeholder="Vì sao người này nhận mức đó."
                  onChange={(e) =>
                    setDong(capNhat(dong, i, { reason: e.target.value }))
                  }
                />

                {suaDuoc && (
                  <Button
                    size="sm"
                    variant="ghost"
                    className="text-danger mt-2"
                    onClick={() => setDong(dong.filter((_, j) => j !== i))}
                  >
                    Bỏ khỏi danh sách
                  </Button>
                )}
              </li>
            ))}
          </ul>
        )}

        {chia.error && (
          <p role="alert" className="text-danger mt-3 text-[0.84rem]">
            {chia.error.message}
          </p>
        )}

        {suaDuoc && (
          <div className="mt-3 flex flex-wrap gap-2">
            <Button
              size="sm"
              onClick={() =>
                setDong([...dong, { user_id: "", amount: "", reason: "" }])
              }
            >
              Thêm người
            </Button>

            <Button
              size="sm"
              variant="primary"
              loading={chia.isPending}
              disabled={dong.some(
                (d) =>
                  d.user_id === "" ||
                  d.amount.trim() === "" ||
                  d.reason.trim().length < 5,
              )}
              onClick={() =>
                chia.mutate({
                  projectId,
                  allocations: dong.map((d) => ({
                    ...d,
                    amount: d.amount.replaceAll(".", "").trim(),
                    reason: d.reason.trim(),
                  })),
                })
              }
            >
              Lưu phần chia
            </Button>
          </div>
        )}
      </section>

      {/* ── Chốt / chi ──────────────────────────── */}
      {canManage && pool.status !== "distributed" && (
        <div className="border-line border-t pt-4">
          {doiTrangThai.error && (
            <p role="alert" className="text-danger mb-3 text-[0.84rem]">
              {doiTrangThai.error.message}
            </p>
          )}

          <Button
            variant={pool.status === "draft" ? "primary" : "secondary"}
            loading={doiTrangThai.isPending}
            onClick={() =>
              doiTrangThai.mutate({
                projectId,
                status: pool.status === "draft" ? "locked" : "distributed",
              })
            }
          >
            {pool.status === "draft" ? "Chốt quỹ" : "Đánh dấu đã chi"}
          </Button>

          <p className="text-ink-faint mt-2 text-[0.78rem]">
            {pool.status === "draft"
              ? "Chốt xong, nhân viên xem được phần của mình và không ai sửa được nữa."
              : "Xác nhận kế toán đã trả xong."}
          </p>
        </div>
      )}
    </div>
  );
}

function capNhat(
  ds: AllocationInput[],
  i: number,
  thayDoi: Partial<AllocationInput>,
): AllocationInput[] {
  return ds.map((d, j) => (j === i ? { ...d, ...thayDoi } : d));
}

function O({
  nhan,
  gia,
  canhBao,
}: {
  nhan: string;
  gia: string;
  canhBao?: boolean;
}) {
  return (
    <div
      className={cn(
        "rounded-xl border px-3 py-2.5",
        canhBao
          ? "border-accent-line bg-accent-surface"
          : "border-line bg-paper-sunken",
      )}
    >
      <p className="text-[0.98rem] leading-none font-semibold tabular-nums">
        {gia}
      </p>
      <p className="text-ink-faint mt-1 text-[0.74rem]">{nhan}</p>
    </div>
  );
}
