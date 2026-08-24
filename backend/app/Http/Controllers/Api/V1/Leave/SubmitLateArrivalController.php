<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Leave;

use App\Domain\Attendance\Data\WorkShift;
use App\Domain\Identity\Models\User;
use App\Domain\Leave\Actions\SubmitLateArrivalAction;
use App\Domain\Leave\Notifications\LateArrivalRequestedNotification;
use App\Http\Concerns\PresentsLateArrivals;
use App\Http\Requests\Leave\SubmitLateArrivalRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Notification;

/**
 * Nộp đơn xin đi làm muộn.
 *
 * **Chỉ nộp cho chính mình.** Không có tham số người dùng trên đường này, nên
 * không có cách nào nộp hộ người khác — kể cả quản trị viên. Cùng nguyên tắc
 * với đơn nghỉ: đây là việc cá nhân khai, không phải việc ai đó khai thay.
 */
final class SubmitLateArrivalController
{
    use PresentsLateArrivals;

    public function __invoke(
        SubmitLateArrivalRequest $request,
        SubmitLateArrivalAction $action,
    ): JsonResponse {
        /** @var User $actor */
        $actor = $request->user();

        $don = $action->execute(
            nguoiNop: $actor,
            ngay: (string) $request->string('date'),
            gioDuKien: (string) $request->string('expected_arrival'),
            lyDo: (string) $request->string('reason'),
        );

        /*
        | Báo cho quản lý trực tiếp, không báo cho mọi người có quyền duyệt.
        |
        | Cùng lý do đã ghi ở SubmitLeaveController: bắn cho cả nhóm thì bốn
        | người cùng nhận một đơn, ba người trong đó không liên quan, và chẳng
        | ai chắc mình có phải người phải xử lý không.
        |
        | Người không có quản lý trực tiếp thì không ai được báo — đơn vẫn nằm
        | trong hộp duyệt. Đó là lưới hứng có chủ ý, và là lý do hộp duyệt phải
        | hiện số đơn đang chờ.
        */
        $quanLy = $actor->manager;

        if ($quanLy instanceof User && $quanLy->is_active) {
            Notification::send($quanLy, new LateArrivalRequestedNotification(
                $actor->name,
                $don->date,
                $don->arrivalLabel(),
                WorkShift::fromConfig()->lateMinutesFromLocalTime($don->arrivalLabel()),
            ));
        }

        return new JsonResponse(
            ['data' => $this->presentLateArrival($don->load('reviewer'))],
            Response::HTTP_CREATED,
        );
    }
}
