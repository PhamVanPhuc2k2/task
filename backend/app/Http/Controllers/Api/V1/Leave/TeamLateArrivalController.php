<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Leave;

use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Models\User;
use App\Domain\Leave\Models\LateArrivalRequest;
use App\Http\Concerns\PresentsLateArrivals;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Hộp duyệt: đơn xin đi muộn của người trong phạm vi quản lý.
 *
 * Người có `leave.view.all` thấy cả công ty; trưởng phòng chỉ thấy cây phòng
 * ban của mình. Không có quyền nào trong hai quyền đó thì không vào được.
 */
final class TeamLateArrivalController
{
    use PresentsLateArrivals;

    private const TRAN = 100;

    public function __invoke(Request $request): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        $toanBo = $actor->can(Permission::ViewAllLeave->value);

        abort_unless(
            $toanBo || $actor->can(Permission::ViewTeamLeave->value),
            Response::HTTP_FORBIDDEN,
        );

        $truyVan = LateArrivalRequest::query()
            // Nạp sẵn quan hệ: danh sách trăm đơn mà tra tên từng người là
            // trăm câu SQL, và Model::preventLazyLoading() ở dev sẽ ném lỗi.
            ->with(['user.department', 'reviewer']);

        if (! $toanBo) {
            $phamVi = $actor->department?->subtreeIds() ?? [];

            $truyVan->whereHas(
                'user',
                fn ($q) => $q->whereIn('department_id', $phamVi),
            );
        }

        $tong = (clone $truyVan)->count();

        $ds = $truyVan
            // Đơn đang chờ lên trước: đó là thứ người mở màn này cần làm gì đó.
            ->orderByRaw("FIELD(status, 'pending') DESC")
            ->orderByDesc('date')
            ->limit(self::TRAN)
            ->get();

        return new JsonResponse([
            'data' => $ds->map(
                fn (LateArrivalRequest $d): array => $this->presentLateArrival($d, kemNguoiNop: true),
            )->all(),
            'meta' => [
                'total' => $tong,
                'limit' => self::TRAN,
                'pending' => $ds->where('status.value', 'pending')->count(),
            ],
        ]);
    }
}
