<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Reports;

use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Models\User;
use App\Domain\Report\Models\DailyReport;
use App\Http\Concerns\PresentsDailyReports;
use App\Support\Time\WorkDate;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Date;

/**
 * Báo cáo của cả phòng trong một ngày.
 *
 * Đây là màn hình quản lý mở mỗi sáng, nên nó phải trả lời được **hai** câu chứ
 * không phải một: *ai đã báo cáo gì* và **ai chưa báo cáo**. Câu thứ hai mới là
 * câu khó — và là lý do báo cáo gắn vào ngày chứ không gắn vào từng task: người
 * họp cả ngày không có task nào để gắn, nhưng vẫn phải xuất hiện trong danh
 * sách "chưa báo cáo".
 *
 * Nên phản hồi liệt kê **mọi nhân sự trong phạm vi**, người chưa nộp thì
 * `report` là null.
 */
final class TeamReportsController
{
    use PresentsDailyReports;

    public function __invoke(Request $request): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        $toanCongTy = $actor->can(Permission::ViewAllAttendance->value)
            || $actor->can(Permission::ViewAllTasks->value);

        abort_unless(
            $toanCongTy || $actor->can(Permission::ViewTeamReports->value),
            Response::HTTP_FORBIDDEN,
        );

        $ngay = $this->ngay($request);

        $nhanSu = User::query()
            ->where('is_active', true)
            ->when(
                ! $toanCongTy,
                fn (Builder $q) => $q->whereIn(
                    'department_id',
                    $actor->department?->subtreeIds() ?? [],
                ),
            )
            ->with('department')
            ->orderBy('name')
            ->get();

        // Một truy vấn cho mọi báo cáo của ngày đó, không phải một truy vấn mỗi
        // người — đây là chỗ dễ thành N+1 nhất của màn này.
        $baoCao = DailyReport::query()
            ->whereIn('user_id', $nhanSu->pluck('id'))
            ->where('report_date', $ngay)
            ->with(['tasks', 'user.department', 'reviewer'])
            ->get()
            ->keyBy('user_id');

        $tenTask = $this->taskTitles($baoCao->values());

        $rows = $nhanSu->map(function (User $u) use ($baoCao, $tenTask): array {
            $r = $baoCao->get($u->id);

            return [
                'user' => [
                    'id' => $u->uuid,
                    'name' => $u->name,
                    'department' => $u->department?->name,
                ],
                // Bản nháp coi như CHƯA nộp với quản lý: nó là chỗ nhân viên
                // viết dở, không phải thứ chờ ai đọc.
                'report' => $r !== null && $r->status->isSubmitted()
                    ? $this->presentReport($r, $tenTask)
                    : null,
                'has_draft' => $r !== null && ! $r->status->isSubmitted(),
            ];
        });

        return new JsonResponse([
            'data' => [
                'date' => $ngay,
                'rows' => $rows->values()->all(),
                'submitted' => $rows->filter(
                    fn (array $d): bool => $d['report'] !== null,
                )->count(),
                'total' => $rows->count(),
                'can_review' => $actor->can(Permission::ReviewReports->value),
            ],
        ]);
    }

    /**
     * Ngày cần xem, mặc định là hôm nay **theo giờ Việt Nam**.
     *
     * Không dùng `now()->toDateString()`: máy chủ đặt UTC sẽ sang ngày mới trễ
     * bảy tiếng, nên từ 00:00 tới 07:00 giờ Việt Nam màn hình này hiện báo cáo
     * của hôm qua mà không ai hiểu vì sao.
     */
    private function ngay(Request $request): string
    {
        $ngay = (string) $request->string('date');

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $ngay) === 1) {
            return $ngay;
        }

        /** @var CarbonImmutable $bayGio */
        $bayGio = Date::now();

        return WorkDate::from($bayGio);
    }
}
