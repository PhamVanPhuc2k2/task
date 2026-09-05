<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Attendance;

use App\Domain\Attendance\Actions\SubmitAdjustmentAction;
use App\Domain\Attendance\Notifications\AdjustmentRequestedNotification;
use App\Domain\Identity\Models\User;
use App\Http\Concerns\GuardsClosedPeriods;
use App\Http\Concerns\PresentsAdjustments;
use App\Http\Requests\Attendance\SubmitAdjustmentRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Notification;

/**
 * Nhân viên nộp đơn giải trình cho một ngày công của mình.
 *
 * ## Lỗ hổng nó bịt
 *
 * `work_days` trước đây chỉ có một cửa vào: người quản lý bấm nút. Nhân viên đi
 * gặp khách cả ngày, mất mạng, hay quên mở máy thì không có đường nào nói điều
 * đó trong hệ thống — họ nhắn Zalo, quản lý nhớ thì bấm, quên thì thôi.
 *
 * Từ khi có chốt sổ kỳ công thì chuyện đó thành hạn chót cứng, nên nhân viên
 * **phải** có đường tự khởi xướng, và đường đó phải để lại vết.
 *
 * ## Nộp cho chính mình, không nộp hộ
 *
 * Không có tham số người dùng trên đường dẫn; người nộp luôn là
 * `$request->user()`. Đơn giải trình là một LỜI KHAI về ngày công của mình —
 * người khác khai hộ thì chữ ký nằm sai chỗ.
 */
final class SubmitAdjustmentController
{
    use GuardsClosedPeriods;
    use PresentsAdjustments;

    public function __invoke(
        SubmitAdjustmentRequest $request,
        SubmitAdjustmentAction $action,
    ): JsonResponse {
        /** @var User $actor */
        $actor = $request->user();

        $ngay = (string) $request->string('work_date');

        // Kỳ đã chốt thì số liệu của nó là căn cứ trả lương. Chặn ở đây chứ
        // không để đơn nộp xong rồi mới phát hiện không ai duyệt được.
        $this->guardPeriodOpen($ngay, 'work_date');

        $don = $action->execute(
            nguoiNop: $actor,
            ngay: $ngay,
            lyDo: (string) $request->string('reason'),
            soPhutDeNghi: $request->filled('requested_minutes')
                ? $request->integer('requested_minutes')
                : null,
        );

        /*
        | Báo cho quản lý TRỰC TIẾP, không bắn cho mọi người có quyền duyệt.
        |
        | Người không có quản lý trực tiếp thì không ai được báo — đơn vẫn nằm
        | trong hộp duyệt. Đó là lưới hứng có chủ ý, cùng khuôn với đơn nghỉ.
        */
        $quanLy = $actor->manager;

        if ($quanLy instanceof User) {
            Notification::send($quanLy, new AdjustmentRequestedNotification(
                $actor->name,
                $don->work_date,
                $don->requested_minutes,
            ));
        }

        return new JsonResponse(
            ['data' => $this->presentAdjustment($don)],
            Response::HTTP_CREATED,
        );
    }
}
