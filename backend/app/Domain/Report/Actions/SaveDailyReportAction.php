<?php

declare(strict_types=1);

namespace App\Domain\Report\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Report\Data\ReportWindow;
use App\Domain\Report\Enums\DailyReportStatus;
use App\Domain\Report\Models\DailyReport;
use App\Domain\Report\Models\DailyReportTask;
use App\Support\Exceptions\ReportDateOutOfWindowException;
use App\Support\Exceptions\ReportNotEditableException;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

/**
 * Lưu báo cáo ngày — tạo mới hoặc sửa bản đã có.
 *
 * Một người một ngày một báo cáo (ràng buộc `unique` ở migration), nên đây vừa
 * là tạo vừa là cập nhật.
 *
 * `$taskIds` nhận khoá chính dạng số chứ không nhận model `Task`: miền Report
 * không được tham chiếu tới miền Task. Tầng Http đã đổi uuid sang id và kiểm
 * người nộp có quyền xem những task đó chưa.
 */
final class SaveDailyReportAction
{
    /**
     * @param  list<int>  $taskIds
     */
    public function execute(
        User $nguoiViet,
        string $reportDate,
        string $content,
        array $taskIds,
        bool $nop,
    ): DailyReport {
        // Chặn trước khi mở giao dịch: ngày ngoài khoảng thì không có gì để ghi.
        // Đây mới là chỗ luật có hiệu lực thật — FormRequest chỉ lo câu chữ.
        $khoang = ReportWindow::current();

        if (! $khoang->allows($reportDate)) {
            throw new ReportDateOutOfWindowException($khoang->message());
        }

        return DB::transaction(function () use ($nguoiViet, $reportDate, $content, $taskIds, $nop): DailyReport {
            $baoCao = DailyReport::query()->firstOrNew([
                'user_id' => $nguoiViet->id,
                'report_date' => $reportDate,
            ]);

            if ($baoCao->exists && ! $baoCao->status->isEditable()) {
                throw new ReportNotEditableException($baoCao->status->label());
            }

            $baoCao->fill([
                'content' => $content,
                'status' => $nop
                    ? DailyReportStatus::Submitted
                    : DailyReportStatus::Draft,
            ]);

            // Giữ mốc nộp đầu tiên. Sửa lại sau khi nộp không làm mốc này lùi
            // tới — câu hỏi "nộp muộn không" phải trả lời bằng lần nộp đầu.
            if ($nop && $baoCao->submitted_at === null) {
                $baoCao->submitted_at = Date::now();
            }

            $baoCao->save();

            // Thay thế toàn bộ danh sách task. Cập nhật từng dòng sẽ để sót
            // task người dùng vừa bỏ tích.
            DailyReportTask::query()->where('daily_report_id', $baoCao->id)->delete();

            foreach (array_unique($taskIds) as $taskId) {
                DailyReportTask::query()->create([
                    'daily_report_id' => $baoCao->id,
                    'task_id' => $taskId,
                ]);
            }

            return $baoCao->load('tasks');
        });
    }
}
