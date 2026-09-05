<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Attendance;

use App\Domain\Attendance\Enums\RequestStatus;
use App\Domain\Attendance\Models\OvertimeRequest;
use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Models\User;
use App\Http\Concerns\PresentsOvertime;
use App\Support\Contracts\WorkCalendar;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Hộp duyệt: đăng ký làm thêm giờ của người trong phạm vi quản lý.
 *
 * Phạm vi giống màn bảng công và màn giải trình, cố ý như vậy: người duyệt một
 * đơn làm thêm cần nhìn được giờ công của người đó, nên ba màn phải cùng một
 * tập người — nếu không thì có đơn hiện ra mà không tra được số liệu.
 */
final class TeamOvertimeController
{
    use PresentsOvertime;

    private const int TRAN = 100;

    public function __invoke(Request $request, WorkCalendar $lich): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        $toanBo = $actor->can(Permission::ViewAllAttendance->value);

        abort_unless(
            $toanBo || $actor->can(Permission::ViewTeamAttendance->value),
            Response::HTTP_FORBIDDEN,
        );

        $truyVan = OvertimeRequest::query()
            // Nạp sẵn quan hệ: danh sách trăm đơn mà tra tên từng người là trăm
            // câu SQL, và Model::preventLazyLoading() ở dev sẽ ném lỗi.
            ->with(['user.department', 'reviewer']);

        if (! $toanBo) {
            $phamVi = $actor->department?->subtreeIds() ?? [];

            $truyVan->whereHas('user', fn ($q) => $q->whereIn('department_id', $phamVi));
        }

        $tong = (clone $truyVan)->count();

        $ds = $truyVan
            // Đơn đang chờ lên trước: đó là thứ người mở màn này cần làm gì đó,
            // và với làm thêm giờ thì nó còn gấp theo giờ.
            ->orderByRaw("FIELD(status, 'pending') DESC")
            ->orderByDesc('work_date')
            ->orderByDesc('start_time')
            ->limit(self::TRAN)
            ->get();

        return new JsonResponse([
            'data' => [
                'requests' => $ds->map(
                    fn (OvertimeRequest $d): array => $this->presentOvertime($d, $lich, kemNguoiNop: true),
                )->all(),

                'total' => $tong,
                'limit' => self::TRAN,

                // Đếm trên TRUY VẤN, không đếm trên trang đã lấy về: đơn chờ
                // duyệt được sắp lên đầu, nên khi số đơn chờ vượt trần thì viên
                // nhãn đứng im ở đúng con số trần và người duyệt tưởng mình đã
                // xử lý gần hết.
                'pending' => (clone $truyVan)
                    ->where('status', RequestStatus::Pending->value)
                    ->count(),
            ],
        ]);
    }
}
