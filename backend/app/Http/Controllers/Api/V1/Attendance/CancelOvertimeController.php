<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Attendance;

use App\Domain\Attendance\Actions\CancelOvertimeAction;
use App\Domain\Attendance\Models\OvertimeRequest;
use App\Domain\Identity\Models\User;
use App\Http\Concerns\GuardsClosedPeriods;
use App\Http\Concerns\PresentsOvertime;
use App\Support\Contracts\WorkCalendar;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/** Người nộp tự rút đăng ký làm thêm giờ của mình khi còn đang chờ duyệt. */
final class CancelOvertimeController
{
    use GuardsClosedPeriods;
    use PresentsOvertime;

    public function __invoke(
        Request $request,
        OvertimeRequest $overtime,
        CancelOvertimeAction $action,
        WorkCalendar $lich,
    ): JsonResponse {
        /** @var User $actor */
        $actor = $request->user();

        // Chỉ rút được đơn của CHÍNH MÌNH. Quản lý muốn bác thì dùng đường
        // duyệt và ghi lý do — rút hộ là xoá dấu vết của một quyết định.
        abort_unless($overtime->user_id === $actor->id, Response::HTTP_FORBIDDEN);

        $this->guardPeriodOpen($overtime->work_date, 'work_date');

        return new JsonResponse([
            'data' => $this->presentOvertime($action->execute($overtime)->load('reviewer'), $lich),
        ]);
    }
}
