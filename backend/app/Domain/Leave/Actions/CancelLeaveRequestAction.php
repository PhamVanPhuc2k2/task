<?php

declare(strict_types=1);

namespace App\Domain\Leave\Actions;

use App\Domain\Leave\Enums\LeaveStatus;
use App\Domain\Leave\Models\LeaveRequest;
use App\Support\Exceptions\LeaveNotEditableException;

/**
 * Người nộp tự rút đơn của mình.
 *
 * Chỉ rút được khi còn `pending`. Đơn đã duyệt là căn cứ miễn chấm công cho
 * những ngày đã qua — rút ngược lại là đổi nghĩa bảng công của quá khứ mà
 * không ai biết. Xem LeaveStatus.
 */
final class CancelLeaveRequestAction
{
    public function execute(LeaveRequest $don): LeaveRequest
    {
        if (! $don->status->isEditable()) {
            throw new LeaveNotEditableException($don->status->label());
        }

        $don->forceFill(['status' => LeaveStatus::Cancelled])->save();

        return $don->refresh();
    }
}
