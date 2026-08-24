<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Leave;

use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Models\User;
use App\Domain\Leave\Actions\ReviewLeaveRequestAction;
use App\Domain\Leave\Models\LeaveRequest;
use App\Domain\Leave\Notifications\LeaveReviewedNotification;
use App\Http\Concerns\PresentsLeaveRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Notification;

/**
 * Quản lý duyệt hoặc từ chối một đơn nghỉ.
 *
 * Hai lớp kiểm quyền, và cả hai đều cần:
 *
 *   1. Có quyền `leave.approve` — được phép duyệt nói chung
 *   2. Người nộp nằm trong phạm vi mình quản lý — không duyệt được đơn của
 *      phòng khác
 *
 * Thiếu lớp thứ hai thì mọi trưởng phòng duyệt được đơn của cả công ty.
 */
final class ReviewLeaveController
{
    use PresentsLeaveRequests;

    public function __invoke(
        Request $request,
        LeaveRequest $leave,
        ReviewLeaveRequestAction $action,
    ): JsonResponse {
        /** @var User $actor */
        $actor = $request->user();

        abort_unless($actor->can(Permission::ApproveLeave->value), Response::HTTP_FORBIDDEN);
        abort_unless($this->trongPhamVi($actor, $leave), Response::HTTP_FORBIDDEN);

        // Không tự duyệt đơn của chính mình, kể cả khi có quyền. Đây là ràng
        // buộc nhân sự cơ bản, và nó KHÔNG suy ra được từ phạm vi phòng ban —
        // trưởng phòng luôn nằm trong phòng của chính mình.
        abort_if($leave->user_id === $actor->id, Response::HTTP_FORBIDDEN);

        $duLieu = $request->validate([
            'approve' => ['required', 'boolean'],
            // Từ chối BẮT BUỘC có lý do; duyệt thì không. "Đồng ý" đã là câu
            // trả lời đầy đủ, còn bắt gõ lý do cho mọi lượt duyệt chỉ sinh ra
            // những dòng "ok". Từ chối mà không nói vì sao thì người nộp không
            // biết phải sửa gì.
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

        $dongY = (bool) $duLieu['approve'];
        $ghiChu = isset($duLieu['note']) ? (string) $duLieu['note'] : null;

        $moi = $action->execute($leave, $actor, $dongY, $ghiChu);

        $nguoiNop = $moi->user;

        if ($nguoiNop instanceof User) {
            Notification::send($nguoiNop, new LeaveReviewedNotification(
                $dongY,
                $moi->start_date,
                $moi->end_date,
                $ghiChu,
            ));
        }

        return new JsonResponse([
            'data' => $this->presentLeave(
                $moi->load(['user.department', 'reviewer']),
                kemNguoiNop: true,
            ),
        ]);
    }

    private function trongPhamVi(User $actor, LeaveRequest $don): bool
    {
        if ($actor->can(Permission::ViewAllLeave->value)) {
            return true;
        }

        $phamVi = $actor->department?->subtreeIds() ?? [];

        return in_array($don->user?->department_id, $phamVi, true);
    }
}
