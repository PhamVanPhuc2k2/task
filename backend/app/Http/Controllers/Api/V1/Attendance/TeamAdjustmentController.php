<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Attendance;

use App\Domain\Attendance\Enums\RequestStatus;
use App\Domain\Attendance\Models\AttendanceAdjustment;
use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Models\User;
use App\Http\Concerns\PresentsAdjustments;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Hộp duyệt: đơn giải trình công của người trong phạm vi quản lý.
 *
 * Phạm vi giống hệt màn bảng công, và cố ý như vậy: `attendance.view.all` thấy
 * cả công ty, trưởng phòng chỉ thấy cây phòng ban của mình. Người duyệt một đơn
 * giải trình cần nhìn được ngày công mà đơn nói tới — hai màn hình phải cùng
 * một tập người, nếu không thì có đơn hiện ra mà không tra được số liệu.
 */
final class TeamAdjustmentController
{
    use PresentsAdjustments;

    private const int TRAN = 100;

    public function __invoke(Request $request): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        $toanBo = $actor->can(Permission::ViewAllAttendance->value);

        abort_unless(
            $toanBo || $actor->can(Permission::ViewTeamAttendance->value),
            Response::HTTP_FORBIDDEN,
        );

        $truyVan = AttendanceAdjustment::query()
            // Nạp sẵn quan hệ: danh sách trăm đơn mà tra tên từng người là trăm
            // câu SQL, và Model::preventLazyLoading() ở dev sẽ ném lỗi.
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
            ->orderByDesc('work_date')
            ->limit(self::TRAN)
            ->get();

        /*
        | Bọc trong `data` như ba đường anh em, KHÔNG dùng `data` + `meta`.
        |
        | `/late-arrivals/team` từng trả `data` là một MẢNG kèm `meta` riêng
        | trong khi các đường cùng họ theo dạng `data: { requests, ... }`. Hậu
        | quả: `undefined.length` làm sập cả tab — nhưng CHỈ với người có quyền
        | duyệt, nên lỗi sống sót tới lúc có người duyệt mở nó ra.
        */
        return new JsonResponse([
            'data' => [
                'requests' => $ds->map(
                    fn (AttendanceAdjustment $d): array => $this->presentAdjustment($d, kemNguoiNop: true),
                )->all(),

                // Trả tổng kèm trần: cắt im lặng thì người có 120 đơn tưởng
                // mình chỉ từng nộp 100. Quy ước chung của cả dự án.
                'total' => $tong,
                'limit' => self::TRAN,

                // Đếm trên TRUY VẤN, không đếm trên trang đã lấy về: đơn chờ
                // duyệt được sắp lên đầu, nên khi số đơn chờ vượt trần thì viên
                // nhãn sẽ đứng im ở đúng con số trần và người duyệt tưởng mình
                // đã xử lý gần hết.
                'pending' => (clone $truyVan)
                    ->where('status', RequestStatus::Pending->value)
                    ->count(),
            ],
        ]);
    }
}
