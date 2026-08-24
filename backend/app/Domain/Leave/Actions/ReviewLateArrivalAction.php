<?php

declare(strict_types=1);

namespace App\Domain\Leave\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Leave\Enums\LeaveStatus;
use App\Domain\Leave\Models\LateArrivalRequest;
use App\Support\Exceptions\LeaveNotEditableException;
use Illuminate\Support\Facades\DB;

/**
 * Duyệt hoặc từ chối một đơn xin đi làm muộn.
 *
 * Khoá dòng rồi đọc lại trạng thái trước khi ghi: hai quản lý cùng mở hộp duyệt
 * và cùng bấm thì người thứ hai phải nhận lỗi, không được ghi đè quyết định của
 * người thứ nhất một cách im lặng.
 */
final class ReviewLateArrivalAction
{
    public function execute(
        LateArrivalRequest $don,
        User $nguoiDuyet,
        bool $dongY,
        ?string $ghiChu,
    ): LateArrivalRequest {
        return DB::transaction(function () use ($don, $nguoiDuyet, $dongY, $ghiChu): LateArrivalRequest {
            /** @var LateArrivalRequest $khoa */
            $khoa = LateArrivalRequest::query()
                ->whereKey($don->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($khoa->status !== LeaveStatus::Pending) {
                throw new LeaveNotEditableException($khoa->status->label());
            }

            $khoa->forceFill([
                'status' => $dongY ? LeaveStatus::Approved : LeaveStatus::Rejected,
                'reviewed_by' => $nguoiDuyet->id,
                'reviewed_at' => now(),
                'review_note' => $ghiChu,
            ])->save();

            return $khoa->refresh();
        });
    }
}
