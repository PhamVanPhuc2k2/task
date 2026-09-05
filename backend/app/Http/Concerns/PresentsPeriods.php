<?php

declare(strict_types=1);

namespace App\Http\Concerns;

use App\Domain\Attendance\Models\AttendancePeriod;

/**
 * Hình dạng một kỳ công trả về cho giao diện.
 *
 * Ba controller cùng trả về đúng hình dạng này — xem danh sách, chốt sổ, mở
 * khoá. Chép ba lần là ba chỗ sẽ lệch nhau ở lần thêm trường đầu tiên, và giao
 * diện sẽ thấy một trường chỉ xuất hiện sau khi bấm nút chứ không có lúc tải
 * trang. Cùng khuôn với `PresentsLateArrivals` và `PresentsDailyReports`.
 */
trait PresentsPeriods
{
    /**
     * @return array<string, mixed>
     */
    protected function presentPeriod(AttendancePeriod $k): array
    {
        return [
            'period' => $k->period,
            'status' => $k->status->value,
            'status_label' => $k->status->label(),
            'is_locked' => $k->isLocked(),
            'closed_at' => $k->closed_at->toIso8601String(),
            'closed_by' => $k->closedBy?->name,
            'reopened_at' => $k->reopened_at?->toIso8601String(),
            'reopened_by' => $k->reopenedBy?->name,
            'reopen_reason' => $k->reopen_reason,
        ];
    }
}
