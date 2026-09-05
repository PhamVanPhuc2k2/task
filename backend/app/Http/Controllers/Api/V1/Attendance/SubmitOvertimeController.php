<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Attendance;

use App\Domain\Attendance\Actions\ReviewOvertimeAction;
use App\Domain\Attendance\Actions\SubmitOvertimeAction;
use App\Domain\Attendance\Notifications\OvertimeRequestedNotification;
use App\Domain\Identity\Models\User;
use App\Http\Concerns\GuardsClosedPeriods;
use App\Http\Concerns\PresentsOvertime;
use App\Http\Requests\Attendance\SubmitOvertimeRequest;
use App\Support\Contracts\WorkCalendar;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Notification;

/**
 * Nhân viên đăng ký làm thêm giờ cho chính mình.
 *
 * ## Duyệt TRƯỚC mới được tính
 *
 * Làm thêm giờ ra tiền ở mức 150–300% (Điều 98). Suy nó từ giờ ngồi trước máy
 * là để hệ thống tự ký một khoản chi mà không ai quyết định — và một cái tab
 * quên đóng qua đêm sẽ thành mười tiếng làm thêm ngày nghỉ.
 *
 * ## Đăng ký cho chính mình, không đăng ký hộ
 *
 * Không có tham số người dùng trên đường dẫn; người nộp luôn là
 * `$request->user()`. Quản lý muốn giao việc buổi tối thì vẫn phải để người làm
 * tự đăng ký — chữ ký nằm ở người sẽ ở lại làm.
 */
final class SubmitOvertimeController
{
    use GuardsClosedPeriods;
    use PresentsOvertime;

    public function __invoke(
        SubmitOvertimeRequest $request,
        SubmitOvertimeAction $action,
        ReviewOvertimeAction $heSo,
        WorkCalendar $lich,
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
            tuGio: (string) $request->string('start_time'),
            denGio: (string) $request->string('end_time'),
            lyDo: (string) $request->string('reason'),
        );

        /*
        | Báo cho quản lý TRỰC TIẾP, không bắn cho mọi người có quyền duyệt.
        |
        | Loại này gấp theo GIỜ chứ không theo ngày: người ta đăng ký cho tối
        | nay. Người không có quản lý trực tiếp thì không ai được báo — đơn vẫn
        | nằm trong hộp duyệt. Đó là lưới hứng có chủ ý.
        */
        $quanLy = $actor->manager;

        if ($quanLy instanceof User) {
            Notification::send($quanLy, new OvertimeRequestedNotification(
                $actor->name,
                $don->work_date,
                $don->startLabel(),
                $don->endLabel(),
                $don->minutes,
                // Hệ số DỰ KIẾN theo lịch hiện tại. Con số chính thức được đóng
                // băng lúc duyệt — xem ReviewOvertimeAction.
                $heSo->heSo($don->work_date),
            ));
        }

        return new JsonResponse(
            ['data' => $this->presentOvertime($don, $lich)],
            Response::HTTP_CREATED,
        );
    }
}
