<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Leave;

use App\Domain\Identity\Models\User;
use App\Domain\Leave\Actions\SubmitLeaveRequestAction;
use App\Domain\Leave\Enums\LeaveType;
use App\Domain\Leave\Notifications\LeaveRequestedNotification;
use App\Http\Concerns\GuardsClosedPeriods;
use App\Http\Concerns\PresentsLeaveRequests;
use App\Http\Requests\Leave\SubmitLeaveRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Notification;

/**
 * Nộp đơn xin nghỉ.
 *
 * **Chỉ nộp cho chính mình.** Không có tham số người dùng trên đường này, nên
 * không có cách nào nộp hộ người khác — kể cả quản trị viên. Nghỉ phép là việc
 * cá nhân khai, không phải việc ai đó khai thay.
 */
final class SubmitLeaveController
{
    use GuardsClosedPeriods;
    use PresentsLeaveRequests;

    public function __invoke(
        SubmitLeaveRequest $request,
        SubmitLeaveRequestAction $action,
    ): JsonResponse {
        /** @var User $actor */
        $actor = $request->user();

        // Đơn nghỉ đổi số ngày công của kỳ, nên kỳ đã chốt thì không nhận
        // đơn phủ vào đó nữa. Kiểm cả KHOẢNG vì đơn có thể vắt hai kỳ.
        $this->guardPeriodRangeOpen(
            (string) $request->string('start_date'),
            (string) $request->string('end_date'),
        );

        $don = $action->execute(
            nguoiNop: $actor,
            loai: LeaveType::from((string) $request->string('type')),
            tuNgay: (string) $request->string('start_date'),
            denNgay: (string) $request->string('end_date'),
            lyDo: (string) $request->string('reason'),
        );

        /*
        | Báo cho quản lý trực tiếp, không báo cho mọi người có quyền duyệt.
        |
        | Bắn cho cả nhóm thì bốn người cùng nhận một đơn, ba người trong đó
        | không liên quan, và chẳng ai chắc mình có phải người phải xử lý không.
        |
        | Người không có quản lý trực tiếp thì không ai được báo — đơn vẫn nằm
        | trong hộp duyệt. Đó là lưới hứng có chủ ý, và là lý do hộp duyệt phải
        | hiện số đơn đang chờ.
        */
        $quanLy = $actor->manager;

        if ($quanLy instanceof User && $quanLy->is_active) {
            Notification::send($quanLy, new LeaveRequestedNotification(
                $actor->name,
                $don->type->label(),
                $don->start_date,
                $don->end_date,
                $don->dayCount(),
            ));
        }

        return new JsonResponse(
            ['data' => $this->presentLeave($don->load('reviewer'))],
            Response::HTTP_CREATED,
        );
    }
}
