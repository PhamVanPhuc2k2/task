<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Payroll\Models\BonusAllocation;
use App\Domain\Payroll\Models\BonusPool;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Quỹ thưởng của một dự án, kèm phần chia.
 *
 * Tiền trả về dạng **chuỗi** — xem `SalaryRecordResource` để biết lý do.
 *
 * @mixin BonusPool
 */
final class BonusPoolResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'total_amount' => $this->total_amount,
            'allocated_total' => $this->allocatedTotal(),
            'remaining' => $this->remaining(),
            'currency' => $this->currency,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'is_editable' => $this->status->isEditable(),
            'condition_note' => $this->condition_note,
            'locked_at' => $this->locked_at?->toIso8601String(),
            'distributed_at' => $this->distributed_at?->toIso8601String(),

            'allocations' => $this->whenLoaded(
                'allocations',
                fn (): array => $this->allocations
                    ->map(fn (BonusAllocation $p): array => [
                        'id' => $p->uuid,
                        'user' => [
                            'id' => $p->user?->uuid,
                            'name' => $p->user?->name,
                        ],
                        'amount' => $p->amount,
                        'reason' => $p->reason,
                    ])
                    ->all(),
            ),
        ];
    }
}
