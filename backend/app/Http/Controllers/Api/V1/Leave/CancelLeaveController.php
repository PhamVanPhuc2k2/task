<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Leave;

use App\Domain\Identity\Models\User;
use App\Domain\Leave\Actions\CancelLeaveRequestAction;
use App\Domain\Leave\Models\LeaveRequest;
use App\Http\Concerns\GuardsClosedPeriods;
use App\Http\Concerns\PresentsLeaveRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/** Người nộp tự rút đơn của mình khi còn đang chờ duyệt. */
final class CancelLeaveController
{
    use GuardsClosedPeriods;
    use PresentsLeaveRequests;

    public function __invoke(
        Request $request,
        LeaveRequest $leave,
        CancelLeaveRequestAction $action,
    ): JsonResponse {
        /** @var User $actor */
        $actor = $request->user();

        // Chỉ rút được đơn của CHÍNH MÌNH. Quản lý muốn bác đơn thì dùng đường
        // duyệt và ghi lý do — rút hộ là xoá dấu vết của một quyết định.
        abort_unless($leave->user_id === $actor->id, Response::HTTP_FORBIDDEN);

        // Rút một đơn ĐÃ DUYỆT cũng đổi số ngày công của kỳ.
        $this->guardPeriodRangeOpen($leave->start_date, $leave->end_date);

        return new JsonResponse([
            'data' => $this->presentLeave($action->execute($leave)->load('reviewer')),
        ]);
    }
}
