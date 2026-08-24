<?php

declare(strict_types=1);

namespace App\Domain\Leave\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Leave\Enums\LeaveStatus;
use App\Domain\Leave\Models\LeaveRequest;
use App\Support\Exceptions\LeaveNotEditableException;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

/**
 * Quản lý duyệt hoặc từ chối đơn nghỉ.
 *
 * Duyệt là thao tác **có hệ quả lên bảng công**: từ lúc đó, những ngày trong
 * đơn được miễn chấm công. Nên nó chỉ đi một chiều — đơn đã xử lý không quay
 * về `pending` được nữa, cùng nguyên tắc với quỹ thưởng đã chốt.
 *
 * Từ chối **bắt buộc có lý do**. Duyệt thì không: "đồng ý" đã là câu trả lời
 * đầy đủ, còn bắt gõ lý do cho mọi lượt duyệt chỉ sinh ra những dòng "ok".
 */
final class ReviewLeaveRequestAction
{
    public function execute(
        LeaveRequest $don,
        User $nguoiDuyet,
        bool $dongY,
        ?string $ghiChu = null,
    ): LeaveRequest {
        return DB::transaction(function () use ($don, $nguoiDuyet, $dongY, $ghiChu): LeaveRequest {
            // Khoá rồi đọc lại: hai trưởng phòng cùng mở hộp duyệt và cùng bấm
            // thì người thứ hai phải thấy đơn đã được xử lý, không ghi đè.
            $moi = LeaveRequest::query()->whereKey($don->getKey())->lockForUpdate()->firstOrFail();

            if (! $moi->status->isEditable()) {
                throw new LeaveNotEditableException($moi->status->label());
            }

            $moi->forceFill([
                'status' => $dongY ? LeaveStatus::Approved : LeaveStatus::Rejected,
                'reviewed_by' => $nguoiDuyet->id,
                'reviewed_at' => Date::now(),
                'review_note' => $ghiChu,
            ])->save();

            return $moi->refresh();
        });
    }
}
