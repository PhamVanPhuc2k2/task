<?php

declare(strict_types=1);

namespace App\Http\Concerns;

use App\Domain\Report\Models\DailyReport;
use App\Domain\Report\Models\DailyReportTask;
use App\Domain\Task\Models\Task;
use Illuminate\Support\Collection;

/**
 * Dựng phản hồi cho báo cáo ngày, kèm tên task.
 *
 * Nằm ở tầng Http vì đây là **chỗ duy nhất được biết cả hai miền**: `Task`
 * thuộc miền Task, `DailyReport` thuộc miền Report, và deptrac không cho hai
 * miền gọi thẳng nhau. Cùng cách đã dùng cho bảng lương ghép với `User` và quỹ
 * thưởng ghép với dự án.
 *
 * Không dùng `JsonResource` cho phần này: Resource không có đường sạch để nhận
 * thêm một bảng tra từ ngoài, và cách lách bằng `setRelation()` với một mảng
 * thường là lạm dụng API — nó chạy được nhưng người đọc sau sẽ mất thời gian
 * hiểu vì sao một "quan hệ" lại là mảng.
 */
trait PresentsDailyReports
{
    /**
     * Bảng tra tên task cho một tập báo cáo, lấy trong MỘT truy vấn.
     *
     * @param  Collection<int, DailyReport>  $baoCao
     * @return array<int, array{id: string, title: string}>
     */
    protected function taskTitles(Collection $baoCao): array
    {
        $ids = $baoCao
            ->flatMap(fn (DailyReport $r): array => $r->tasks
                ->map(fn (DailyReportTask $t): int => $t->task_id)
                ->all())
            ->unique()
            ->values()
            ->all();

        if ($ids === []) {
            return [];
        }

        return Task::query()
            ->whereIn('id', $ids)
            ->get(['id', 'uuid', 'title'])
            ->mapWithKeys(fn (Task $t): array => [
                $t->id => ['id' => (string) $t->uuid, 'title' => (string) $t->title],
            ])
            ->all();
    }

    /**
     * @param  array<int, array{id: string, title: string}>  $tenTask
     * @return array<string, mixed>
     */
    protected function presentReport(DailyReport $r, array $tenTask): array
    {
        return [
            'id' => $r->uuid,
            'report_date' => $r->report_date,
            'content' => $r->content,
            'status' => $r->status->value,
            'status_label' => $r->status->label(),
            'is_editable' => $r->status->isEditable(),
            'submitted_at' => $r->submitted_at?->toIso8601String(),

            'author' => $r->relationLoaded('user') && $r->user !== null ? [
                'id' => $r->user->uuid,
                'name' => $r->user->name,
                'department' => $r->user->department?->name,
            ] : null,

            // Task đã bị xoá cứng thì không còn trong bảng tra — bỏ qua thay vì
            // hiện một dòng trống. Bản ghi trong `daily_report_tasks` đã đi
            // theo nhờ `cascadeOnDelete`, nên trường hợp này chỉ xảy ra khi có
            // hai request chạy sát nhau.
            'tasks' => $r->tasks
                ->map(fn (DailyReportTask $t): ?array => $tenTask[$t->task_id] ?? null)
                ->filter()
                ->values()
                ->all(),

            'review' => $r->reviewed_at === null ? null : [
                'by' => $r->reviewer?->name,
                'at' => $r->reviewed_at->toIso8601String(),
                'note' => $r->review_note,
            ],
        ];
    }
}
