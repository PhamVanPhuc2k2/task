<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Attendance;

use App\Domain\Attendance\Models\AttendanceAdjustment;
use App\Domain\Identity\Models\User;
use App\Http\Concerns\PresentsAdjustments;
use App\Support\Time\WorkDate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;

/**
 * Đơn giải trình công của chính người đang đăng nhập.
 *
 * Không cần quyền gì thêm: ai cũng xem được đơn của mình.
 *
 * Trả kèm **ngày muộn nhất nộp được** và **kỳ công đã chốt gần nhất** để giao
 * diện chặn ngay ở ô chọn ngày. Lý do quan trọng hơn vẻ ngoài: giao diện KHÔNG
 * được tự tính hai thứ này — đồng hồ máy người dùng có thể lệch, múi giờ trình
 * duyệt có thể không phải giờ Việt Nam khi nhân viên đi công tác, và danh sách
 * kỳ đã chốt thì trình duyệt không có cách nào biết.
 */
final class MyAdjustmentController
{
    use PresentsAdjustments;

    /** Trần số dòng. Luôn trả kèm tổng — xem chú thích bên dưới. */
    private const int TRAN = 100;

    public function __invoke(Request $request): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        $ds = AttendanceAdjustment::query()
            ->where('user_id', $actor->id)
            ->with('reviewer')
            ->orderByDesc('work_date')
            ->limit(self::TRAN)
            ->get();

        return new JsonResponse([
            'data' => [
                'requests' => $ds->map(
                    fn (AttendanceAdjustment $d): array => $this->presentAdjustment($d),
                )->all(),

                // Trả tổng kèm trần: cắt im lặng thì người có 120 đơn tưởng
                // mình chỉ từng nộp 100. Quy ước chung của cả dự án.
                'total' => AttendanceAdjustment::query()->where('user_id', $actor->id)->count(),
                'limit' => self::TRAN,

                // Hôm nay theo GIỜ VIỆT NAM, không phải `now()` ở UTC.
                'latest_date' => WorkDate::from(Date::now()),
            ],
        ]);
    }
}
