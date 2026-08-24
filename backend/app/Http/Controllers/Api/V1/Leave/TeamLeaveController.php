<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Leave;

use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Models\User;
use App\Domain\Leave\Enums\LeaveStatus;
use App\Domain\Leave\Models\LeaveRequest;
use App\Http\Concerns\PresentsLeaveRequests;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Hộp duyệt đơn nghỉ của quản lý.
 *
 * **Phạm vi bám theo mô hình đã có ở Task và Chấm công**: `leave.view.team`
 * thấy phòng mình và mọi phòng trực thuộc bên dưới; `leave.view.all` thấy toàn
 * công ty.
 *
 * Mặc định chỉ hiện đơn **đang chờ** — đó là việc cần làm. Đơn đã xử lý xem
 * được bằng `?status=`, nhưng không trộn vào danh sách chính: một hộp duyệt
 * lẫn cả trăm đơn cũ là hộp duyệt không ai mở.
 */
final class TeamLeaveController
{
    use PresentsLeaveRequests;

    public function __invoke(Request $request): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        $toanCongTy = $actor->can(Permission::ViewAllLeave->value);

        abort_unless(
            $toanCongTy || $actor->can(Permission::ViewTeamLeave->value),
            Response::HTTP_FORBIDDEN,
        );

        $phamVi = $toanCongTy ? null : ($actor->department?->subtreeIds() ?? []);
        $trangThai = (string) $request->string('status', LeaveStatus::Pending->value);

        $co = fn (): Builder => LeaveRequest::query()
            ->when(
                $phamVi !== null,
                fn (Builder $q) => $q->whereHas(
                    'user',
                    fn (Builder $u) => $u->whereIn('department_id', $phamVi ?? []),
                ),
            );

        $don = (clone $co())
            ->where('status', $trangThai)
            ->with(['user.department', 'reviewer'])
            // Đơn sắp tới hạn nghỉ đứng trước: duyệt muộn một đơn bắt đầu ngày
            // mai gây hại hơn duyệt muộn một đơn của tháng sau.
            ->orderBy('start_date')
            ->limit(100)
            ->get();

        return new JsonResponse([
            'data' => [
                'requests' => $don->map(
                    fn (LeaveRequest $d): array => $this->presentLeave($d, kemNguoiNop: true),
                )->all(),

                'status' => $trangThai,

                // Số đơn đang chờ, luôn trả về bất kể đang lọc trạng thái nào —
                // đây là con số hiện lên thanh điều hướng, và nó phải đúng kể
                // cả khi người dùng đang xem tab "đã duyệt".
                'pending_count' => (clone $co())
                    ->where('status', LeaveStatus::Pending->value)
                    ->count(),

                // Tổng của trạng thái đang xem. Có `limit(100)` ở trên nên bắt
                // buộc phải trả tổng — quy ước "không cắt im lặng" của dự án.
                'total' => (clone $co())->where('status', $trangThai)->count(),

                'can_approve' => $actor->can(Permission::ApproveLeave->value),
            ],
        ]);
    }
}
