<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Leave;

use App\Domain\Attendance\Data\WorkShift;
use App\Domain\Identity\Models\User;
use App\Domain\Leave\Data\LeaveWindow;
use App\Domain\Leave\Models\LateArrivalRequest;
use App\Http\Concerns\PresentsLateArrivals;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Đơn xin đi muộn của chính người đang đăng nhập.
 *
 * Không cần quyền gì thêm: ai cũng xem được đơn của mình.
 *
 * Trả kèm **khoảng ngày nộp được** và **giờ vào làm** để giao diện chặn ngay ở
 * ô nhập. Cùng nguyên tắc đã áp cho đơn nghỉ, và lý do quan trọng hơn vẻ ngoài:
 * giao diện KHÔNG được tự tính hai thứ này từ `new Date()` — đồng hồ máy người
 * dùng có thể lệch, và múi giờ trình duyệt có thể không phải giờ Việt Nam khi
 * nhân viên đi công tác.
 */
final class MyLateArrivalController
{
    use PresentsLateArrivals;

    /** Trần số dòng. Luôn trả kèm tổng — xem chú thích bên dưới. */
    private const TRAN = 100;

    public function __invoke(Request $request): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        $ds = LateArrivalRequest::query()
            ->where('user_id', $actor->id)
            ->with('reviewer')
            ->orderByDesc('date')
            ->limit(self::TRAN)
            ->get();

        $khoang = LeaveWindow::current();
        $ca = WorkShift::fromConfig();

        return new JsonResponse([
            'data' => [
                'requests' => $ds->map(
                    fn (LateArrivalRequest $d): array => $this->presentLateArrival($d),
                )->all(),

                // Trả tổng kèm trần: cắt im lặng thì người có 120 đơn tưởng
                // mình chỉ từng nộp 100. Quy ước chung của cả dự án.
                'total' => LateArrivalRequest::query()->where('user_id', $actor->id)->count(),
                'limit' => self::TRAN,

                'window' => [
                    'earliest' => $khoang->earliest,
                    'latest' => $khoang->latest,
                ],

                // Giao diện hiện "ca bắt đầu 8h15" ngay cạnh ô chọn giờ, và
                // dùng đúng mốc này để chặn. Hardcode ở frontend là mở đường
                // cho hai nơi nói hai giờ khác nhau sau lần đổi ca đầu tiên.
                'shift' => [
                    'morning_start' => $ca->morningStart,
                    'end' => $ca->end,
                ],
            ],
        ]);
    }
}
