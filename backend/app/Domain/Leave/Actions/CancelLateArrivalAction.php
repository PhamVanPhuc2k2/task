<?php

declare(strict_types=1);

namespace App\Domain\Leave\Actions;

use App\Domain\Leave\Enums\LeaveStatus;
use App\Domain\Leave\Models\LateArrivalRequest;
use App\Support\Exceptions\LeaveNotEditableException;

/**
 * Người nộp tự rút đơn.
 *
 * Chỉ rút được đơn còn đang chờ. Đơn đã duyệt mà rút được thì bảng công đổi
 * ngược lại sau khi quản lý đã quyết — và không còn dấu vết vì sao.
 */
final class CancelLateArrivalAction
{
    public function execute(LateArrivalRequest $don): LateArrivalRequest
    {
        if ($don->status !== LeaveStatus::Pending) {
            throw new LeaveNotEditableException($don->status->label());
        }

        $don->forceFill(['status' => LeaveStatus::Cancelled])->save();

        return $don->refresh();
    }
}
