<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Attendance;

use App\Domain\Attendance\Models\AttendancePeriod;
use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Models\User;
use App\Http\Concerns\PresentsPeriods;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Danh sách kỳ công đã từng bị chốt.
 *
 * Nền móng của mọi phép tính tiền ở đợt 4: trả lương từ những con số còn sửa
 * được nghĩa là không bao giờ trả lời được câu *"phiếu lương này tính từ đâu
 * ra"*.
 *
 * ## Hai quyền, không phải một
 *
 * `attendance.period.close` cho giám đốc và admin; `attendance.period.reopen`
 * **chỉ** cho giám đốc. Chốt là việc hành chính cuối kỳ, mở khoá là việc đụng
 * vào số liệu đã dùng để trả lương — hai mức trách nhiệm khác nhau, và admin
 * thường là IT chứ không phải người chịu trách nhiệm về con số lương.
 *
 * ## Mọi lần chốt và mở đều vào nhật ký kiểm toán
 *
 * `payroll_audits` — cùng chỗ với việc đổi mức lương, vì cùng một họ. Bảng đó
 * chỉ ghi thêm, không sửa, không xoá. Bảng `attendance_periods` chỉ giữ trạng
 * thái hiện tại; lịch sử đóng mở nằm ở nhật ký.
 */
final class PeriodController
{
    use PresentsPeriods;

    /** Số kỳ gần nhất trả về. Mười hai tháng đủ cho mọi câu hỏi thực tế. */
    private const int TRAN = 12;

    public function index(Request $request): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        abort_unless(
            $actor->can(Permission::ViewAllAttendance->value)
                || $actor->can(Permission::ClosePeriod->value),
            Response::HTTP_FORBIDDEN,
        );

        $ds = AttendancePeriod::query()
            ->with(['closedBy', 'reopenedBy'])
            ->orderByDesc('period')
            ->limit(self::TRAN)
            ->get();

        return new JsonResponse([
            'data' => [
                'periods' => $ds->map(fn (AttendancePeriod $k): array => $this->presentPeriod($k))->all(),
                // Giao diện cần biết hiện nút nào, và hỏi server thay vì tự suy
                // từ danh sách quyền — thêm một quyền mới thì giao diện tự đúng.
                'can_close' => $actor->can(Permission::ClosePeriod->value),
                'can_reopen' => $actor->can(Permission::ReopenPeriod->value),
            ],
        ]);
    }
}
