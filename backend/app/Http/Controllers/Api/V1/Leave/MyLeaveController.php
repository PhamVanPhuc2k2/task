<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Leave;

use App\Domain\Identity\Models\User;
use App\Domain\Leave\Data\LeaveWindow;
use App\Domain\Leave\Enums\LeaveType;
use App\Domain\Leave\Models\LeaveRequest;
use App\Http\Concerns\PresentsLeaveRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Đơn nghỉ của chính người đang đăng nhập.
 *
 * Không cần quyền gì thêm — cùng khuôn với chấm công, báo cáo và lương: màn
 * "của tôi" luôn mở cho mọi người, phần của người khác mới cần quyền.
 */
final class MyLeaveController
{
    use PresentsLeaveRequests;

    public function __invoke(Request $request): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        $don = LeaveRequest::query()
            ->where('user_id', $actor->id)
            ->with('reviewer')
            ->orderByDesc('start_date')
            // Giới hạn 100 đơn gần nhất. Đây là một `limit` nên nó phải đi kèm
            // tổng số — quy ước "không cắt im lặng" của dự án.
            ->limit(100)
            ->get();

        $khoang = LeaveWindow::current();

        return new JsonResponse([
            'data' => [
                'requests' => $don->map(
                    fn (LeaveRequest $d): array => $this->presentLeave($d),
                )->all(),

                'total' => LeaveRequest::query()->where('user_id', $actor->id)->count(),

                // Loại nghỉ do server khai, không hardcode ở frontend: thêm
                // loại mới thì giao diện tự có, không phải sửa hai chỗ.
                'types' => array_map(
                    fn (LeaveType $t): array => ['value' => $t->value, 'label' => $t->label()],
                    LeaveType::cases(),
                ),

                // Khoảng ngày nộp được, để giao diện chặn ngay ở ô chọn ngày
                // thay vì để người ta điền xong mới báo lỗi.
                'window' => [
                    'earliest' => $khoang->earliest,
                    'latest' => $khoang->latest,
                    'max_days' => $khoang->maxDays,
                ],
            ],
        ]);
    }
}
