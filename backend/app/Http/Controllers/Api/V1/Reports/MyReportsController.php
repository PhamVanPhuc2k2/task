<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Reports;

use App\Domain\Identity\Models\User;
use App\Domain\Report\Data\ReportWindow;
use App\Domain\Report\Models\DailyReport;
use App\Http\Concerns\PresentsDailyReports;
use App\Http\Concerns\ResolvesAttendanceMonth;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Báo cáo ngày của chính người đang đăng nhập.
 *
 * Không cần quyền gì thêm — báo cáo của mình thì mình đọc được, kể cả bản nháp.
 *
 * Dùng lại `ResolvesAttendanceMonth`: tháng ở đây cũng là **tháng theo lịch
 * Việt Nam**, cùng lý do và cùng cách tính với bảng công. Hai màn hình này đứng
 * cạnh nhau trong ngày làm việc của nhân viên nên lệch nhau một ngày là lỗi
 * nhìn thấy ngay.
 */
final class MyReportsController
{
    use PresentsDailyReports;
    use ResolvesAttendanceMonth;

    public function __invoke(Request $request): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        [$thang, $tuNgay, $denNgay] = $this->resolveMonth($request);

        $baoCao = DailyReport::query()
            ->where('user_id', $actor->id)
            ->whereBetween('report_date', [$tuNgay, $denNgay])
            ->with(['tasks', 'reviewer'])
            ->orderByDesc('report_date')
            ->get();

        $tenTask = $this->taskTitles($baoCao);
        $khoang = ReportWindow::current();

        return new JsonResponse([
            'data' => [
                'month' => $thang,
                'days' => $this->daysOfMonth($tuNgay, $denNgay),
                'reports' => $baoCao
                    ->map(fn (DailyReport $r): array => $this->presentReport($r, $tenTask))
                    ->all(),
                'submitted_count' => $baoCao
                    ->filter(fn (DailyReport $r): bool => $r->status->isSubmitted())
                    ->count(),

                /*
                | Khoảng ngày còn nộp được, do server nói ra.
                |
                | Không để frontend tự tính từ `new Date()`: đồng hồ máy người
                | dùng có thể lệch, và múi giờ trình duyệt có thể không phải giờ
                | Việt Nam (nhân viên đi công tác). Tự tính thì giao diện mở ô
                | soạn cho một ngày mà API sẽ từ chối — người dùng viết xong mới
                | biết là không nộp được.
                */
                'window' => [
                    'earliest' => $khoang->earliest,
                    'latest' => $khoang->latest,
                ],
            ],
        ]);
    }
}
