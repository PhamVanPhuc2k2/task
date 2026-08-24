<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Leave;

use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Models\User;
use App\Domain\Leave\Actions\ReviewLateArrivalAction;
use App\Domain\Leave\Models\LateArrivalRequest;
use App\Domain\Leave\Notifications\LateArrivalReviewedNotification;
use App\Http\Concerns\PresentsLateArrivals;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Notification;

/**
 * Quản lý duyệt hoặc từ chối một đơn xin đi muộn.
 *
 * Ba lớp kiểm quyền, giống hệt đơn nghỉ và vì đúng những lý do đó:
 *
 *   1. Có quyền `leave.approve` — được phép duyệt nói chung
 *   2. Người nộp nằm trong phạm vi mình quản lý — không duyệt được đơn của
 *      phòng khác
 *   3. Không tự duyệt đơn của chính mình
 *
 * Lớp thứ ba KHÔNG suy ra được từ lớp thứ hai: trưởng phòng luôn nằm trong
 * phòng của chính mình.
 *
 * Dùng chung quyền `leave.approve` với đơn nghỉ, có chủ ý: người duyệt đơn nghỉ
 * và người duyệt đơn đi muộn là cùng một người. Tách thành quyền riêng chỉ tạo
 * ra tình huống ai đó duyệt được loại này mà không duyệt được loại kia.
 */
final class ReviewLateArrivalController
{
    use PresentsLateArrivals;

    public function __invoke(
        Request $request,
        LateArrivalRequest $lateArrival,
        ReviewLateArrivalAction $action,
    ): JsonResponse {
        /** @var User $actor */
        $actor = $request->user();

        abort_unless($actor->can(Permission::ApproveLeave->value), Response::HTTP_FORBIDDEN);
        abort_unless($this->trongPhamVi($actor, $lateArrival), Response::HTTP_FORBIDDEN);
        abort_if($lateArrival->user_id === $actor->id, Response::HTTP_FORBIDDEN);

        $duLieu = $request->validate([
            'approve' => ['required', 'boolean'],
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
        ]);

        $moi = $action->execute(
            $lateArrival,
            $actor,
            (bool) $duLieu['approve'],
            isset($duLieu['note']) ? (string) $duLieu['note'] : null,
        );

        /*
        | Báo cho người nộp. Trường hợp quan trọng nhất là BỊ TỪ CHỐI: không
        | báo thì người ta đinh ninh mình đã xin phép xong rồi cứ thế đi muộn,
        | hôm sau mới biết ngày đó vẫn bị đánh dấu.
        */
        $nguoiNop = $moi->user;

        if ($nguoiNop instanceof User) {
            Notification::send($nguoiNop, new LateArrivalReviewedNotification(
                (bool) $duLieu['approve'],
                $moi->date,
                $moi->arrivalLabel(),
                isset($duLieu['note']) ? (string) $duLieu['note'] : null,
            ));
        }

        return new JsonResponse([
            'data' => $this->presentLateArrival(
                $moi->load(['user.department', 'reviewer']),
                kemNguoiNop: true,
            ),
        ]);
    }

    private function trongPhamVi(User $actor, LateArrivalRequest $don): bool
    {
        if ($actor->can(Permission::ViewAllLeave->value)) {
            return true;
        }

        $phamVi = $actor->department?->subtreeIds() ?? [];

        return in_array($don->user?->department_id, $phamVi, true);
    }
}
