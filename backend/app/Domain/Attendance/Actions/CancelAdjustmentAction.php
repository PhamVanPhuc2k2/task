<?php

declare(strict_types=1);

namespace App\Domain\Attendance\Actions;

use App\Domain\Attendance\Enums\AdjustmentStatus;
use App\Domain\Attendance\Models\AttendanceAdjustment;
use App\Support\Exceptions\AdjustmentNotEditableException;
use Illuminate\Support\Facades\DB;

/**
 * Người nộp tự rút đơn giải trình của mình.
 *
 * Chỉ rút được khi còn chờ duyệt. Đơn đã duyệt đã ghi một dòng `work_days` — rút
 * ngược lại là để số giờ của một ngày trong quá khứ đổi lần nữa mà không ai
 * biết, và con số đó có thể đã đi vào một kỳ đã chốt.
 *
 * Khoá dòng vì người nộp bấm Rút đúng lúc quản lý bấm Duyệt là chuyện thật sự
 * xảy ra khi cả hai đang mở màn hình.
 */
final class CancelAdjustmentAction
{
    public function execute(AttendanceAdjustment $don): AttendanceAdjustment
    {
        return DB::transaction(function () use ($don): AttendanceAdjustment {
            /** @var AttendanceAdjustment $khoa */
            $khoa = AttendanceAdjustment::query()
                ->whereKey($don->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (! $khoa->status->isEditable()) {
                throw new AdjustmentNotEditableException($khoa->status->label());
            }

            $khoa->forceFill(['status' => AdjustmentStatus::Cancelled])->save();

            return $khoa->refresh();
        });
    }
}
