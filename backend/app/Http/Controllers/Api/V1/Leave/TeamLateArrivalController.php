<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Leave;

use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Models\User;
use App\Domain\Leave\Enums\LeaveStatus;
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

        /*
        | Bọc trong `data` như ba đường anh em, KHÔNG dùng `data` + `meta`.
        |
        | Đường này từng trả `data` là một MẢNG kèm `meta` riêng, trong khi
        | `/leave/team`, `/late-arrivals/me` và giao diện đều theo dạng
        | `data: { requests, ... }`. Hậu quả: `cuaDoi.data.requests` là
        | `undefined`, và `undefined.length` làm sập cả tab Đi muộn — nhưng CHỈ
        | với người có quyền duyệt, vì nhân viên thường không vẽ khối đó. Nên
        | lỗi sống sót từ lúc viết tính năng tới lúc có người duyệt mở nó ra.
        |
        | Bài học: một đường trả về lệch hình dạng so với các đường cùng họ là
        | thứ TypeScript không bắt được — kiểu ở frontend chỉ là lời khai, không
        | phải phép kiểm. Giờ đã có test khoá hình dạng.
        */
        return new JsonResponse([
            'data' => [
                'requests' => $ds->map(
                    fn (LateArrivalRequest $d): array => $this->presentLateArrival($d, kemNguoiNop: true),
                )->all(),

                // Trả tổng kèm trần: cắt im lặng thì người có 120 đơn tưởng
                // mình chỉ từng nộp 100. Quy ước chung của cả dự án.
                'total' => $tong,
                'limit' => self::TRAN,

                /*
                | Đếm trên TRUY VẤN, không đếm trên trang đã lấy về.
                |
                | Bản trước đếm `$ds` — tập đã bị `limit(TRAN)` cắt. Đơn chờ
                | duyệt được sắp lên đầu, nên khi số đơn chờ vượt trần thì viên
                | nhãn đứng im ở đúng con số trần và người duyệt tưởng mình đã
                | xử lý gần hết. Con số trên nhãn phải là con số thật.
                */
                'pending' => (clone $truyVan)
                    ->where('status', LeaveStatus::Pending->value)
                    ->count(),
            ],
        ]);
    }
}
