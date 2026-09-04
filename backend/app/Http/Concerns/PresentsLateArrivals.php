<?php

declare(strict_types=1);

namespace App\Http\Concerns;

use App\Domain\Leave\Models\LateArrivalRequest;

/**
 * Hình dạng JSON của một đơn xin đi muộn.
 *
 * Tách thành trait vì năm controller cùng trả về nó, và một trong số đó thêm
 * thông tin người nộp. Để mỗi controller tự dựng mảng thì thêm một trường phải
 * sửa năm chỗ — và quên một chỗ là client nhận về hai hình dạng khác nhau cho
 * cùng một thứ, im lặng.
 */
trait PresentsLateArrivals
{
    /**
     * @return array<string, mixed>
     */
    protected function presentLateArrival(
        LateArrivalRequest $don,
        bool $kemNguoiNop = false,
    ): array {
        $data = [
            'id' => $don->uuid,
            'date' => $don->date,
            'type' => $don->type->value,
            'type_label' => $don->type->label(),
            // `HH:MM` chứ không phải `HH:MM:SS` mà MySQL trả về: giây không
            // mang thông tin nào ở đây, và để nguyên thì giao diện phải cắt.
            //
            // `expected_time` là mốc giờ của ĐƠN — giờ đến với đơn đi muộn, giờ
            // rời với đơn về sớm. Giữ luôn `expected_arrival` vì giao diện cũ
            // đọc nó; bỏ ngay thì mọi bản chưa cập nhật hiện ô trống.
            'expected_time' => $don->timeLabel(),
            'expected_arrival' => $don->arrivalLabel(),
            'reason' => $don->reason,
            'status' => $don->status->value,
            'status_label' => $don->status->label(),
            'is_editable' => $don->status->isEditable(),
            'created_at' => $don->created_at?->toIso8601String(),

            'review' => $don->reviewed_at === null ? null : [
                'by' => $don->reviewer?->name,
                'at' => $don->reviewed_at->toIso8601String(),
                'note' => $don->review_note,
            ],
        ];

        if ($kemNguoiNop) {
            $data['user'] = [
                'id' => $don->user?->uuid,
                'name' => $don->user?->name,
                'department' => $don->user?->department?->name,
            ];
        }

        return $data;
    }
}
