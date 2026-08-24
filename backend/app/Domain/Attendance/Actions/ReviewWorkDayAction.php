<?php

declare(strict_types=1);

namespace App\Domain\Attendance\Actions;

use App\Domain\Attendance\Enums\AttendanceDecision;
use App\Domain\Attendance\Models\WorkDay;
use App\Domain\Identity\Models\User;
use Illuminate\Support\Facades\Date;

/**
 * Người quản lý ra quyết định trên một ngày công.
 *
 * Ghi đè quyết định cũ nếu đã có — một người một ngày chỉ có một quyết định
 * hiện hành (ràng buộc `unique` ở migration). Lịch sử các lần đổi ý nằm ở
 * `user_activities` nếu sau này cần; bảng này chỉ giữ trạng thái hiện tại.
 *
 * `reason` bắt buộc và được kiểm ở tầng Http. Một quyết định không lý do thì
 * sáu tháng sau vô dụng ngang với không ghi gì — mà đây đúng là loại quyết định
 * sinh tranh cãi muộn: *"sao tháng trước anh bỏ qua cho tôi mà tháng này lại
 * tính?"*
 */
final class ReviewWorkDayAction
{
    public function execute(
        User $nhanVien,
        string $workDate,
        AttendanceDecision $decision,
        string $reason,
        User $actor,
        ?int $adjustedMinutes = null,
    ): WorkDay {
        return WorkDay::query()->updateOrCreate(
            ['user_id' => $nhanVien->id, 'work_date' => $workDate],
            [
                'decision' => $decision,
                // Chỉ "bỏ qua" mới được ấn định số phút. Với "ghi nhận" thì số
                // của hệ thống là số đúng, còn "cần hỏi lại" thì chưa kết luận
                // gì nên chưa có số nào để ấn định.
                'adjusted_minutes' => $decision === AttendanceDecision::Waived
                    ? $adjustedMinutes
                    : null,
                'reason' => $reason,
                'reviewed_by' => $actor->id,
                'reviewed_at' => Date::now(),
            ],
        );
    }
}
