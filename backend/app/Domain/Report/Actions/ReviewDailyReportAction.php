<?php

declare(strict_types=1);

namespace App\Domain\Report\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Report\Enums\DailyReportStatus;
use App\Domain\Report\Models\DailyReport;
use App\Domain\Report\Notifications\ReportReviewedNotification;
use App\Support\Exceptions\ReportNotSubmittedException;
use Illuminate\Support\Facades\Date;

/**
 * Quản lý đánh dấu đã đọc báo cáo, kèm nhận xét nếu có.
 *
 * Không có "duyệt / từ chối". Báo cáo ngày là thứ nhân viên kể lại việc mình đã
 * làm — không có gì để phê duyệt. Cái quản lý làm là **đọc**, và **hỏi lại** khi
 * cần. Dựng thành luồng duyệt sẽ biến nó thành thủ tục xin phép.
 */
final class ReviewDailyReportAction
{
    public function execute(
        DailyReport $baoCao,
        User $actor,
        ?string $nhanXet,
    ): DailyReport {
        // Bản nháp không phải thứ để đọc — nó là chỗ người dùng viết dở.
        if (! $baoCao->status->isSubmitted()) {
            throw new ReportNotSubmittedException;
        }

        $baoCao->forceFill([
            'status' => DailyReportStatus::Reviewed,
            'reviewed_by' => $actor->id,
            'reviewed_at' => Date::now(),
            'review_note' => $nhanXet,
        ])->save();

        // Chỉ báo khi có nhận xét. Đánh dấu đã đọc mà cũng gửi thông báo thì
        // mỗi sáng nhân viên nhận một thông báo không cần hành động gì — cách
        // nhanh nhất để họ tắt hết thông báo của hệ thống.
        if ($nhanXet !== null && $nhanXet !== '') {
            $baoCao->user?->notify(
                new ReportReviewedNotification($baoCao->report_date, $nhanXet),
            );
        }

        return $baoCao;
    }
}
