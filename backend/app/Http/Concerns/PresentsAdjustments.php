<?php

declare(strict_types=1);

namespace App\Http\Concerns;

use App\Domain\Attendance\Models\AttendanceAdjustment;

/**
 * Hình dạng JSON của một đơn giải trình công.
 *
 * Năm controller cùng trả về nó, một trong số đó thêm thông tin người nộp. Để
 * mỗi controller tự dựng mảng thì thêm một trường phải sửa năm chỗ — và quên
 * một chỗ là client nhận về hai hình dạng khác nhau cho cùng một thứ, im lặng.
 *
 * Đây không phải lo xa: `/late-arrivals/team` từng trả lệch hình dạng so với ba
 * đường anh em và làm sập cả tab Đi muộn, nhưng chỉ với người có quyền duyệt.
 * Lỗi sống từ lúc viết tính năng tới lúc có người duyệt mở nó ra.
 */
trait PresentsAdjustments
{
    /**
     * @return array<string, mixed>
     */
    protected function presentAdjustment(
        AttendanceAdjustment $don,
        bool $kemNguoiNop = false,
    ): array {
        $data = [
            'id' => $don->uuid,
            'work_date' => $don->work_date,
            'reason' => $don->reason,
            'requested_minutes' => $don->requested_minutes,
            'status' => $don->status->value,
            'status_label' => $don->status->label(),
            'is_editable' => $don->status->isEditable(),
            'created_at' => $don->created_at?->toIso8601String(),

            'review' => $don->reviewed_at === null ? null : [
                'by' => $don->reviewer?->name,
                'at' => $don->reviewed_at->toIso8601String(),
                'note' => $don->review_note,
                // Số người DUYỆT chốt — có thể khác số đã xin, và người nộp cần
                // thấy ngay chứ không phải tự đi so lại bảng công.
                'approved_minutes' => $don->approved_minutes,
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
