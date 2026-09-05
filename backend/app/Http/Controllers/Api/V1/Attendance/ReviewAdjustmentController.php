<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Attendance;

use App\Domain\Attendance\Actions\ReviewAdjustmentAction;
use App\Domain\Attendance\Models\AttendanceAdjustment;
use App\Domain\Attendance\Notifications\AdjustmentReviewedNotification;
use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Models\User;
use App\Http\Concerns\GuardsClosedPeriods;
use App\Http\Concerns\PresentsAdjustments;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Notification;

/**
 * Quản lý duyệt hoặc từ chối một đơn giải trình công.
 *
 * Ba lớp kiểm quyền, giống hệt đơn nghỉ và vì đúng những lý do đó:
 *
 *   1. Có quyền `attendance.review` — được phép quyết định ngày công nói chung
 *   2. Người nộp nằm trong phạm vi mình quản lý
 *   3. Không tự duyệt đơn của chính mình
 *
 * Lớp thứ ba KHÔNG suy ra được từ lớp thứ hai: trưởng phòng luôn nằm trong
 * phòng của chính mình.
 *
 * Dùng chung quyền `attendance.review` với nút bấm tay trên bảng công, có chủ
 * ý: duyệt một đơn giải trình **chính là** ra quyết định trên ngày công đó, chỉ
 * khác ở chỗ ai khởi xướng. Tách thành quyền riêng sẽ tạo ra người sửa được
 * ngày công bằng nút bấm nhưng không duyệt được đơn nói về đúng ngày ấy.
 */
final class ReviewAdjustmentController
{
    use GuardsClosedPeriods;
    use PresentsAdjustments;

    /** Một ngày không thể có quá 24 giờ công. */
    private const int PHUT_TOI_DA = 1440;

    public function __invoke(
        Request $request,
        AttendanceAdjustment $adjustment,
        ReviewAdjustmentAction $action,
    ): JsonResponse {
        /** @var User $actor */
        $actor = $request->user();

        abort_unless($actor->can(Permission::ReviewAttendance->value), Response::HTTP_FORBIDDEN);
        abort_unless($this->trongPhamVi($actor, $adjustment), Response::HTTP_FORBIDDEN);
        abort_if(
            $adjustment->user_id === $actor->id,
            Response::HTTP_FORBIDDEN,
            'Không tự duyệt đơn giải trình của chính mình được.',
        );

        $duLieu = $request->validate([
            'approve' => ['required', 'boolean'],

            /*
            | Số phút NGƯỜI DUYỆT chốt — không phải số người nộp xin.
            |
            | Giao diện điền sẵn `requested_minutes` cho tiện, nhưng cái đi vào
            | bảng công là cái người duyệt gửi lên. Nếu không thì "duyệt" chỉ
            | còn nghĩa là "đồng ý với mọi con số nhân viên tự khai".
            |
            | Để trống nghĩa là bỏ qua ngày này mà không ấn định số nào — số hệ
            | thống đo được giữ nguyên, chỉ là ngày đó thôi không bị coi là bất
            | thường nữa.
            */
            'minutes' => ['nullable', 'integer', 'min:1', 'max:'.self::PHUT_TOI_DA],

            // Từ chối bắt buộc có lý do; duyệt thì không — cùng lý do đã ghi ở
            // ReviewLeaveController.
            'note' => [
                $request->boolean('approve') ? 'nullable' : 'required',
                'string',
                'min:5',
                'max:500',
            ],
        ], [
            'note.required' => 'Từ chối thì phải ghi lý do — người nộp cần biết vì sao.',
            'note.min' => 'Lý do quá ngắn để người khác hiểu được.',
            'minutes.max' => 'Một ngày không có quá 24 giờ công.',
        ]);

        $this->guardPeriodOpen($adjustment->work_date, 'work_date');

        $dongY = (bool) $duLieu['approve'];
        $soPhut = $dongY && isset($duLieu['minutes']) ? (int) $duLieu['minutes'] : null;
        $ghiChu = isset($duLieu['note']) ? (string) $duLieu['note'] : null;

        $moi = $action->execute($adjustment, $actor, $dongY, $soPhut, $ghiChu);

        /*
        | Báo cho người nộp. Trường hợp quan trọng nhất là BỊ TỪ CHỐI: không báo
        | thì người ta đinh ninh đã giải trình xong, và chỉ phát hiện ra khi
        | bảng lương tháng đó về — lúc kỳ công đã chốt và không sửa được nữa.
        */
        $nguoiNop = $moi->user;

        if ($nguoiNop instanceof User) {
            Notification::send($nguoiNop, new AdjustmentReviewedNotification(
                $dongY,
                $moi->work_date,
                $soPhut,
                $ghiChu,
            ));
        }

        return new JsonResponse([
            'data' => $this->presentAdjustment(
                $moi->load(['user.department', 'reviewer']),
                kemNguoiNop: true,
            ),
        ]);
    }

    private function trongPhamVi(User $actor, AttendanceAdjustment $don): bool
    {
        if ($actor->can(Permission::ViewAllAttendance->value)) {
            return true;
        }

        $phamVi = $actor->department?->subtreeIds() ?? [];

        return in_array($don->user?->department_id, $phamVi, true);
    }
}
