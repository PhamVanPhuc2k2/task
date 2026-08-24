<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Reports;

use App\Domain\Identity\Models\User;
use App\Domain\Report\Actions\SaveDailyReportAction;
use App\Domain\Task\Models\Task;
use App\Http\Concerns\PresentsDailyReports;
use App\Http\Requests\Report\SaveDailyReportRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Lưu nháp hoặc nộp báo cáo ngày của chính mình.
 *
 * Một endpoint cho cả hai vì đó là cùng một thao tác với một cờ khác nhau —
 * tách ra thì phía frontend phải nhớ gọi đúng đường, và "lưu nháp rồi nộp" trở
 * thành hai request liên tiếp ghi đè nhau.
 *
 * **Chỉ nộp cho chính mình.** Không có tham số người dùng trên đường này, nên
 * không có cách nào nộp hộ người khác.
 */
final class SaveDailyReportController
{
    use PresentsDailyReports;

    public function __invoke(
        SaveDailyReportRequest $request,
        SaveDailyReportAction $action,
    ): JsonResponse {
        /** @var User $actor */
        $actor = $request->user();

        /** @var list<string> $uuids */
        $uuids = $request->array('task_ids');

        $baoCao = $action->execute(
            nguoiViet: $actor,
            reportDate: (string) $request->string('report_date'),
            content: (string) $request->string('content'),
            taskIds: $this->doiSangId($uuids, $actor),
            nop: $request->boolean('submit'),
        );

        $baoCao->load(['tasks', 'reviewer']);

        return new JsonResponse([
            'data' => $this->presentReport(
                $baoCao,
                $this->taskTitles(new Collection([$baoCao])),
            ),
        ]);
    }

    /**
     * Đổi uuid task sang khoá chính, và **kiểm quyền xem** từng cái.
     *
     * Kiểm ở đây chứ không tin danh sách client gửi lên: không kiểm thì bất kỳ
     * ai cũng dò được tiêu đề task của phòng khác bằng cách nhét uuid vào báo
     * cáo rồi đọc lại phản hồi. Đường này ai đăng nhập cũng gọi được, nên nó là
     * bề mặt tấn công thật.
     *
     * Một truy vấn cho cả danh sách, không phải một truy vấn mỗi task.
     *
     * @param  list<string>  $uuids
     * @return list<int>
     */
    private function doiSangId(array $uuids, User $actor): array
    {
        if ($uuids === []) {
            return [];
        }

        $task = Task::query()
            ->whereIn('uuid', $uuids)
            ->visibleTo($actor)
            ->get(['id', 'uuid']);

        if ($task->count() !== count(array_unique($uuids))) {
            throw ValidationException::withMessages([
                'task_ids' => 'Danh sách có công việc bạn không xem được.',
            ]);
        }

        /** @var list<int> $ids */
        $ids = $task->pluck('id')->map(fn (mixed $id): int => (int) $id)->values()->all();

        return $ids;
    }
}
