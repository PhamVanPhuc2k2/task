<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Attendance;

use App\Domain\Attendance\Actions\CancelAdjustmentAction;
use App\Domain\Attendance\Models\AttendanceAdjustment;
use App\Domain\Identity\Models\User;
use App\Http\Concerns\GuardsClosedPeriods;
use App\Http\Concerns\PresentsAdjustments;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/** Người nộp tự rút đơn giải trình của mình khi còn đang chờ duyệt. */
final class CancelAdjustmentController
{
    use GuardsClosedPeriods;
    use PresentsAdjustments;

    public function __invoke(
        Request $request,
        AttendanceAdjustment $adjustment,
        CancelAdjustmentAction $action,
    ): JsonResponse {
        /** @var User $actor */
        $actor = $request->user();

        // Chỉ rút được đơn của CHÍNH MÌNH. Quản lý muốn bác đơn thì dùng đường
        // duyệt và ghi lý do — rút hộ là xoá dấu vết của một quyết định.
        abort_unless($adjustment->user_id === $actor->id, Response::HTTP_FORBIDDEN);

        $this->guardPeriodOpen($adjustment->work_date, 'work_date');

        return new JsonResponse([
            'data' => $this->presentAdjustment($action->execute($adjustment)->load('reviewer')),
        ]);
    }
}
