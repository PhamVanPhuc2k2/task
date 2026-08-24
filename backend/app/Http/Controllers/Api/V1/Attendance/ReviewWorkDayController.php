<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Attendance;

use App\Domain\Attendance\Actions\ReviewWorkDayAction;
use App\Domain\Attendance\Enums\AttendanceDecision;
use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Models\User;
use App\Http\Requests\Attendance\ReviewWorkDayRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * Người quản lý ghi nhận, bỏ qua, hoặc đánh dấu hỏi lại một ngày công.
 *
 * Đây là chỗ chính sách "nhìn cho biết, duyệt không trừ tuỳ hoàn cảnh" thành
 * hình: hệ thống không tự kết luận gì, mọi ngày công bất thường đều chờ một
 * con người quyết định, và quyết định nào cũng kèm lý do.
 *
 * **Không tự duyệt cho chính mình.** Người có quyền duyệt vẫn là nhân viên có
 * ngày công của riêng họ; tự bỏ qua ngày của mình thì cả cơ chế mất ý nghĩa.
 * Cùng họ với `CannotDisableSelfException` và luật chặn tự đổi vai trò.
 *
 * **Phạm vi giống màn xem bảng công**: `attendance.view.all` duyệt được toàn
 * công ty, còn lại chỉ trong phòng mình và các phòng trực thuộc.
 */
final class ReviewWorkDayController
{
    public function __invoke(
        ReviewWorkDayRequest $request,
        User $user,
        ReviewWorkDayAction $action,
    ): JsonResponse {
        /** @var User $actor */
        $actor = $request->user();

        abort_unless(
            $actor->can(Permission::ReviewAttendance->value),
            Response::HTTP_FORBIDDEN,
        );

        abort_if(
            $actor->is($user),
            Response::HTTP_FORBIDDEN,
            'Không tự duyệt ngày công của chính mình được.',
        );

        abort_unless($this->trongPhamVi($actor, $user), Response::HTTP_FORBIDDEN);

        $ngay = $action->execute(
            nhanVien: $user,
            workDate: (string) $request->string('work_date'),
            decision: AttendanceDecision::from((string) $request->string('decision')),
            reason: (string) $request->string('reason'),
            actor: $actor,
            adjustedMinutes: $request->filled('adjusted_minutes')
                ? (int) $request->integer('adjusted_minutes')
                : null,
        );

        return new JsonResponse([
            'data' => [
                'work_date' => $ngay->work_date,
                'decision' => $ngay->decision->value,
                'decision_label' => $ngay->decision->label(),
                'adjusted_minutes' => $ngay->adjusted_minutes,
                'reason' => $ngay->reason,
                'reviewed_by' => $actor->name,
                'reviewed_at' => $ngay->reviewed_at->toIso8601String(),
            ],
        ]);
    }

    private function trongPhamVi(User $actor, User $target): bool
    {
        if ($actor->can(Permission::ViewAllAttendance->value)) {
            return true;
        }

        $phamVi = $actor->department?->subtreeIds() ?? [];

        return $target->department_id !== null
            && in_array($target->department_id, $phamVi, strict: true);
    }
}
