/** Khớp với App\Http\Controllers\Api\V1\Payroll\ProjectBonusController. */

import type { Money } from "@/features/payroll/types/payroll";

export type BonusPoolStatusValue = "draft" | "locked" | "distributed";

export const POOL_STATUSES: {
  value: BonusPoolStatusValue;
  label: string;
  description: string;
}[] = [
  {
    value: "draft",
    label: "Đang lập",
    description: "Sửa được thoải mái. Nhân viên chưa nhìn thấy gì.",
  },
  {
    value: "locked",
    label: "Đã chốt",
    description:
      "Nhân viên xem được phần của mình. Không sửa được nữa — kể cả để tăng.",
  },
  {
    value: "distributed",
    label: "Đã chi",
    description: "Kế toán xác nhận đã trả.",
  },
];

export interface BonusAllocation {
  id: string;
  user: { id: string | null; name: string | null };
  amount: Money;
  reason: string;
}

export interface BonusPool {
  id: string;
  total_amount: Money;
  allocated_total: Money;
  remaining: Money;
  currency: string;
  status: BonusPoolStatusValue;
  status_label: string;
  is_editable: boolean;
  condition_note: string;
  locked_at: string | null;
  distributed_at: string | null;
  allocations?: BonusAllocation[];
}

export interface ProjectBonusResponse {
  /** `null` = dự án này chưa lập quỹ thưởng. */
  data: BonusPool | null;
  meta: {
    project: { id: string; name: string };
    can_manage: boolean;
  };
}

export interface MyBonusItem {
  id: string;
  project: string;
  amount: Money;
  reason: string;
  status: BonusPoolStatusValue | null;
  status_label: string | null;
  decided_by: string | null;
}

export interface MyBonus {
  total: Money;
  items: MyBonusItem[];
}
