<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Leave;

use App\Domain\Attendance\Data\WorkShift;
use App\Domain\Attendance\Data\WorkWeek;
use App\Domain\Identity\Models\User;
use App\Domain\Leave\Actions\SubmitLateArrivalAction;
use App\Domain\Leave\Enums\AttendanceExceptionType;
use App\Domain\Leave\Notifications\LateArrivalRequestedNotification;
use App\Http\Concerns\GuardsClosedPeriods;
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
    use GuardsClosedPeriods;
    use PresentsLateArrivals;

    public function __invoke(
        SubmitLateArrivalRequest $request,
        SubmitLateArrivalAction $action,
    ): JsonResponse {
        /** @var User $actor */
        $actor = $request->user();

        // Thiếu `type` thì coi như đi muộn — client cũ chỉ gửi
        // `expected_arrival` vẫn chạy y như trước.
        $loai = AttendanceExceptionType::tryFrom((string) $request->string('type'))
            ?? AttendanceExceptionType::Late;

        $ngay = (string) $request->string('date');

        $this->guardPeriodOpen($ngay);

        $don = $action->execute(
            nguoiNop: $actor,
            loai: $loai,
            ngay: $ngay,
            gioDuKien: $loai === AttendanceExceptionType::Early
                ? (string) $request->string('expected_departure')
                : (string) $request->string('expected_arrival'),
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
                $don->timeLabel(),
                $this->soPhutLech($loai, $ngay, $don->timeLabel()),
                $loai,
            ));
        }

        return new JsonResponse(
            ['data' => $this->presentLateArrival($don->load('reviewer'))],
            Response::HTTP_CREATED,
        );
    }

    /**
     * Số phút lệch so với mốc ca, để câu thông báo nói ra con số.
     *
     * Về sớm đọc giờ tan của ĐÚNG NGÀY đó: thứ bảy tan lúc 12:00, nên xin về
     * lúc 11:30 là sớm 30 phút chứ không phải sớm 6 tiếng.
     */
    private function soPhutLech(AttendanceExceptionType $loai, string $ngay, string $gioHen): int
    {
        $ca = WorkWeek::fromConfig()->shiftFor($ngay) ?? WorkShift::fromConfig();

        if ($loai === AttendanceExceptionType::Late) {
            return $ca->lateMinutesFromLocalTime($gioHen);
        }

        return $ca->earlyMinutesFromLocalTime($gioHen);
    }
}
