<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Attendance;

use App\Domain\Attendance\Models\WorkSession;
use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Models\User;
use App\Domain\Report\Models\DailyReport;
use App\Domain\Task\Models\TaskActivity;
use App\Support\Time\WorkDate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Chi tiết một ngày công: các phiên làm việc, và việc gì đã đụng tới trong ngày.
 *
 * Đây là màn hình quan trọng nhất của cả tính năng, và là lý do nó tốt hơn cách
 * "treo web đếm giờ": số giờ đứng **cạnh** dấu vết công việc thật. Sáu tiếng
 * online mà không đụng vào việc nào thì nhìn phát ra ngay — mà đó cũng chính là
 * thứ con số giờ đơn độc không bao giờ nói được.
 *
 * Ai cũng xem được ngày của chính mình. Xem của người khác thì theo đúng phạm
 * vi của bảng công tháng.
 */
final class WorkDayDetailController
{
    public function __invoke(Request $request, User $user, string $date): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        abort_unless($this->duocXem($actor, $user), Response::HTTP_FORBIDDEN);

        abort_unless(
            preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1,
            Response::HTTP_NOT_FOUND,
        );

        $phien = WorkSession::query()
            ->where('user_id', $user->id)
            ->where('work_date', $date)
            ->orderBy('started_at')
            ->get();

        return new JsonResponse([
            'data' => [
                'work_date' => $date,
                'user' => ['id' => $user->uuid, 'name' => $user->name],
                'sessions' => $phien->map(fn (WorkSession $p): array => [
                    'started_at' => $p->started_at->toIso8601String(),
                    'ended_at' => $p->ended_at->toIso8601String(),
                    'minutes' => $p->minutes(),
                    'source' => $p->source,
                ])->all(),
                'task_activity_count' => $this->soLanDungViec($user, $date),

                /*
                | Mảnh còn thiếu từ đợt 3, nay đã có: báo cáo ngày.
                |
                | Số giờ đứng một mình không nói được gì. Đứng cạnh "đã đụng
                | vào mấy việc" và "có báo cáo chưa" thì mới đọc ra được tình
                | hình: sáu tiếng online, không đụng việc nào, không báo cáo —
                | nhìn phát ra ngay.
                */
                'daily_report' => $this->baoCaoNgay($user, $date),
            ],
        ]);
    }

    /**
     * Trạng thái báo cáo ngày của người này.
     *
     * `null` = chưa có báo cáo nào, kể cả bản nháp.
     *
     * @return array{status: string, status_label: string, content: string}|null
     */
    private function baoCaoNgay(User $user, string $date): ?array
    {
        $r = DailyReport::query()
            ->where('user_id', $user->id)
            ->where('report_date', $date)
            ->first();

        if (! $r instanceof DailyReport) {
            return null;
        }

        return [
            'status' => $r->status->value,
            'status_label' => $r->status->label(),
            // Cắt ngắn: đây là màn chấm công, không phải màn đọc báo cáo. Ai
            // muốn đọc đủ thì sang trang Báo cáo.
            'content' => mb_substr($r->content, 0, 200),
        ];
    }

    /**
     * Số lần người này đụng vào công việc trong ngày.
     *
     * Lọc theo khoảng UTC tương ứng với ngày công theo giờ Việt Nam, không lọc
     * bằng `DATE(created_at)` — `task_activities` không có cột `work_date`, nên
     * ranh giới ngày phải tự dựng. Xem App\Support\Time\WorkDate.
     */
    private function soLanDungViec(User $user, string $date): int
    {
        return TaskActivity::query()
            ->where('causer_id', $user->id)
            ->whereBetween('created_at', [
                WorkDate::startOfDayUtc($date),
                WorkDate::endOfDayUtc($date),
            ])
            ->count();
    }

    private function duocXem(User $actor, User $target): bool
    {
        if ($actor->is($target)) {
            return true;
        }

        if ($actor->can(Permission::ViewAllAttendance->value)) {
            return true;
        }

        if (! $actor->can(Permission::ViewTeamAttendance->value)) {
            return false;
        }

        $phamVi = $actor->department?->subtreeIds() ?? [];

        return $target->department_id !== null
            && in_array($target->department_id, $phamVi, strict: true);
    }
}
