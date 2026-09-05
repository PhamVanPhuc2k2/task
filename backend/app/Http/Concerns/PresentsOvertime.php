<?php

declare(strict_types=1);

namespace App\Http\Concerns;

use App\Domain\Attendance\Data\OvertimePolicy;
use App\Domain\Attendance\Models\OvertimeRequest;
use App\Support\Contracts\WorkCalendar;

/**
 * Hình dạng JSON của một đơn đăng ký làm thêm giờ.
 *
 * Năm controller cùng trả về nó. Chép năm lần là năm chỗ sẽ lệch nhau ở lần
 * thêm trường đầu tiên, và giao diện nhận về hai hình dạng khác nhau cho cùng
 * một thứ — im lặng, cho tới khi có người mở đúng màn còn lại.
 *
 * ## Hệ số: đã chốt thì lấy số đã chốt, chưa duyệt thì tính sống
 *
 * `rate_percent` chỉ được ghi lúc DUYỆT — đó là thời điểm công ty cam kết trả.
 * Với đơn còn chờ, màn hình vẫn phải nói được *"nếu duyệt thì 200%"*, nên chỗ
 * này tính từ lịch hiện tại và đánh dấu `rate_is_final = false`.
 *
 * Hai con số có thể khác nhau nếu nhân sự nhập thêm một ngày lễ sau khi đơn đã
 * duyệt. Đó là hành vi đúng: đơn đã duyệt giữ nguyên con số đã cam kết.
 */
trait PresentsOvertime
{
    /**
     * @return array<string, mixed>
     */
    protected function presentOvertime(
        OvertimeRequest $don,
        WorkCalendar $lich,
        bool $kemNguoiNop = false,
    ): array {
        $loaiNgay = $lich->kindOf($don->work_date);

        $data = [
            'id' => $don->uuid,
            'work_date' => $don->work_date,

            // `HH:MM` chứ không phải `HH:MM:SS` mà MySQL trả về: giây không
            // mang thông tin nào ở đây, và để nguyên thì giao diện phải cắt.
            'start_time' => $don->startLabel(),
            'end_time' => $don->endLabel(),

            'minutes' => $don->minutes,
            'reason' => $don->reason,

            'day_kind' => $loaiNgay->value,
            'day_kind_label' => $loaiNgay->label(),

            'rate_percent' => $don->rate_percent
                ?? OvertimePolicy::fromConfig()->percentFor($loaiNgay),
            // `false` = con số này còn có thể đổi. Giao diện nói "dự kiến".
            'rate_is_final' => $don->rate_percent !== null,

            'status' => $don->status->value,
            'status_label' => $don->status->label(),
            'is_editable' => $don->status->isEditable(),
            'created_at' => $don->created_at?->toIso8601String(),

            'review' => $don->reviewed_at === null ? null : [
                'by' => $don->reviewer?->name,
                'at' => $don->reviewed_at->toIso8601String(),
                'note' => $don->review_note,
                // Số phút NGƯỜI DUYỆT chốt — có thể ít hơn số đã đăng ký.
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
