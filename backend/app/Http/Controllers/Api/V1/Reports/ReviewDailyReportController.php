<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Reports;

use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Models\User;
use App\Domain\Report\Actions\ReviewDailyReportAction;
use App\Domain\Report\Models\DailyReport;
use App\Http\Concerns\PresentsDailyReports;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;

/**
 * Quản lý đánh dấu đã đọc báo cáo, kèm nhận xét nếu có.
 *
 * Không phải "duyệt / từ chối" — báo cáo ngày là thứ nhân viên kể lại việc mình
 * đã làm, không có gì để phê duyệt. Dựng thành luồng duyệt sẽ biến nó thành thủ
 * tục xin phép, và người ta sẽ viết cho qua chứ không viết thật.
 *
 * **Không tự đọc báo cáo của chính mình.** Cùng họ với luật chặn tự duyệt ngày
 * công và tự đặt lương cho mình.
 */
final class ReviewDailyReportController
{
    use PresentsDailyReports;

    public function __invoke(
        Request $request,
        DailyReport $report,
        ReviewDailyReportAction $action,
    ): JsonResponse {
        /** @var User $actor */
        $actor = $request->user();

        abort_unless(
            $actor->can(Permission::ReviewReports->value),
            Response::HTTP_FORBIDDEN,
        );

        abort_if(
            $actor->id === $report->user_id,
            Response::HTTP_FORBIDDEN,
            'Không tự đánh dấu đã đọc báo cáo của chính mình.',
        );

        abort_unless($this->trongPhamVi($actor, $report), Response::HTTP_FORBIDDEN);

        $validated = $request->validate([
            // Không bắt buộc: đánh dấu đã đọc mà không có gì để nói là trường
            // hợp thường gặp nhất. Bắt ghi nhận xét mỗi ngày cho mỗi người là
            // cách nhanh nhất khiến quản lý bỏ luôn việc đọc.
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $daDoc = $action->execute(
            $report,
            $actor,
            isset($validated['note']) && $validated['note'] !== ''
                ? (string) $validated['note']
                : null,
        );

        $daDoc->load(['tasks', 'user.department', 'reviewer']);

        return new JsonResponse([
            'data' => $this->presentReport(
                $daDoc,
                $this->taskTitles(new Collection([$daDoc])),
            ),
        ]);
    }

    private function trongPhamVi(User $actor, DailyReport $report): bool
    {
        if ($actor->can(Permission::ViewAllTasks->value)) {
            return true;
        }

        $phamVi = $actor->department?->subtreeIds() ?? [];

        return $report->user !== null
            && $report->user->department_id !== null
            && in_array($report->user->department_id, $phamVi, strict: true);
    }
}
