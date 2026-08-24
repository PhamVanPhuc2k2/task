<?php

declare(strict_types=1);

namespace App\Http\Concerns;

use App\Domain\Leave\Models\LeaveRequest;

/**
 * Dựng một đơn nghỉ thành JSON.
 *
 * Gom về một chỗ vì bốn endpoint cùng trả về hình dạng này (đơn của tôi, hộp
 * duyệt, kết quả nộp, kết quả duyệt). Bốn bản sao thì sớm muộn một bản thiếu
 * trường, và giao diện đọc `undefined` mà không lỗi.
 */
trait PresentsLeaveRequests
{
    /**
     * @return array<string, mixed>
     */
    protected function presentLeave(LeaveRequest $don, bool $kemNguoiNop = false): array
    {
        $ra = [
            'id' => $don->uuid,
            'type' => $don->type->value,
            'type_label' => $don->type->label(),
            'start_date' => $don->start_date,
            'end_date' => $don->end_date,
            'days' => $don->dayCount(),
            'reason' => $don->reason,
            'status' => $don->status->value,
            'status_label' => $don->status->label(),
            'is_editable' => $don->status->isEditable(),
            'created_at' => $don->created_at?->toIso8601String(),

            // `null` = chưa ai xử lý. Giao diện phân biệt "đang chờ" với "đã
            // duyệt nhưng không ghi chú" bằng chính chỗ này.
            'review' => $don->reviewed_at === null ? null : [
                'by' => $don->reviewer?->name,
                'at' => $don->reviewed_at->toIso8601String(),
                'note' => $don->review_note,
            ],
        ];

        if ($kemNguoiNop) {
            $ra['user'] = [
                'id' => $don->user?->uuid,
                'name' => $don->user?->name,
                'department' => $don->user?->department?->name,
            ];
        }

        return $ra;
    }
}
