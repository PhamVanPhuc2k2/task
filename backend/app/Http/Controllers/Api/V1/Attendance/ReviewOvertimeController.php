<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Attendance;

use App\Domain\Attendance\Actions\ReviewOvertimeAction;
use App\Domain\Attendance\Models\OvertimeRequest;
use App\Domain\Attendance\Notifications\OvertimeReviewedNotification;
use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Models\User;
use App\Http\Concerns\GuardsClosedPeriods;
use App\Http\Concerns\PresentsOvertime;
use App\Support\Contracts\WorkCalendar;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Notification;

/**
 * Quản lý duyệt hoặc từ chối một đăng ký làm thêm giờ.
 *
 * Ba lớp kiểm quyền, giống hệt màn giải trình và vì đúng những lý do đó:
 *
 *   1. Có quyền `attendance.review`
 *   2. Người nộp nằm trong phạm vi mình quản lý
 *   3. Không tự duyệt đơn của chính mình
 *
 * Lớp thứ ba KHÔNG suy ra được từ lớp thứ hai: trưởng phòng luôn nằm trong
 * phòng của chính mình.
 *
 * ## Dùng chung `attendance.review`, có chủ ý
 *
 * Duyệt làm thêm giờ là quyết định của người GIAO VIỆC: họ cần việc xong tối
 * nay, và họ là người biết có đáng hay không. Đó cũng chính là người quyết định
 * ngày công của nhân sự trong phòng.
 *
 * Khác với quỹ phép năm — thứ được tách thành `leave.balance.manage` riêng vì
 * nó là quyết định hành chính về cả năm, không phải về một buổi tối cụ thể.
 */
final class ReviewOvertimeController
{
    use GuardsClosedPeriods;
    use PresentsOvertime;

    public function __invoke(
        Request $request,
        OvertimeRequest $overtime,
        ReviewOvertimeAction $action,
        WorkCalendar $lich,
    ): JsonResponse {
        /** @var User $actor */
        $actor = $request->user();

        abort_unless($actor->can(Permission::ReviewAttendance->value), Response::HTTP_FORBIDDEN);
        abort_unless($this->trongPhamVi($actor, $overtime), Response::HTTP_FORBIDDEN);
        abort_if(
            $overtime->user_id === $actor->id,
            Response::HTTP_FORBIDDEN,
            'Không tự duyệt đăng ký làm thêm giờ của chính mình được.',
        );

        $duLieu = $request->validate([
            'approve' => ['required', 'boolean'],

            /*
            | Số phút NGƯỜI DUYỆT chốt. Để trống thì lấy đúng số đã đăng ký.
            |
            | Trần trên là SỐ ĐÃ ĐĂNG KÝ, không phải trần của Điều 107: ba cái
            | trần đó đã được kiểm lúc nộp, và cho phép duyệt nhiều hơn số đã
            | đăng ký là mở một đường vòng qua chúng — người duyệt gõ 600 phút
            | vào một đơn 60 phút và không có gì chặn.
            */
            'minutes' => ['nullable', 'integer', 'min:1', 'max:'.$overtime->minutes],

            // Từ chối bắt buộc có lý do; duyệt thì không.
            'note' => [
                $request->boolean('approve') ? 'nullable' : 'required',
                'string',
                'min:5',
                'max:500',
            ],
        ], [
            'note.required' => 'Từ chối thì phải ghi lý do — người nộp cần biết vì sao.',
            'note.min' => 'Lý do quá ngắn để người khác hiểu được.',
            'minutes.max' => 'Không duyệt được nhiều hơn số phút đã đăng ký.',
        ]);

        $this->guardPeriodOpen($overtime->work_date, 'work_date');

        $dongY = (bool) $duLieu['approve'];
        $soPhut = $dongY && isset($duLieu['minutes']) ? (int) $duLieu['minutes'] : null;
        $ghiChu = isset($duLieu['note']) ? (string) $duLieu['note'] : null;

        $moi = $action->execute($overtime, $actor, $dongY, $soPhut, $ghiChu);

        /*
        | Báo cho người nộp. Trường hợp quan trọng nhất là BỊ TỪ CHỐI: không báo
        | thì người ta ở lại làm hai tiếng buổi tối cho một khoản tiền sẽ không
        | bao giờ được trả.
        */
        $nguoiNop = $moi->user;

        if ($nguoiNop instanceof User) {
            Notification::send($nguoiNop, new OvertimeReviewedNotification(
                $dongY,
                $moi->work_date,
                $moi->effectiveMinutes(),
                $moi->rate_percent,
                $ghiChu,
            ));
        }

        return new JsonResponse([
            'data' => $this->presentOvertime(
                $moi->load(['user.department', 'reviewer']),
                $lich,
                kemNguoiNop: true,
            ),
        ]);
    }

    private function trongPhamVi(User $actor, OvertimeRequest $don): bool
    {
        if ($actor->can(Permission::ViewAllAttendance->value)) {
            return true;
        }

        $phamVi = $actor->department?->subtreeIds() ?? [];

        return in_array($don->user?->department_id, $phamVi, true);
    }
}
