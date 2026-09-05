<?php

declare(strict_types=1);

namespace App\Domain\Attendance\Actions;

use App\Domain\Attendance\Enums\RequestStatus;
use App\Domain\Attendance\Models\OvertimeRequest;
use App\Support\Exceptions\RequestNotEditableException;
use Illuminate\Support\Facades\DB;

/**
 * Người nộp tự rút đơn đăng ký làm thêm giờ khi còn đang chờ duyệt.
 *
 * Đơn đã duyệt thì không rút được: đó là một khoản tiền công ty đã cam kết trả
 * cho công việc đã làm. Cần huỷ thì người duyệt xử lý, và vết cũ giữ nguyên.
 *
 * Khoá dòng vì người nộp bấm Rút đúng lúc quản lý bấm Duyệt là chuyện thật sự
 * xảy ra khi cả hai đang mở màn hình.
 */
final class CancelOvertimeAction
{
    public function execute(OvertimeRequest $don): OvertimeRequest
    {
        return DB::transaction(function () use ($don): OvertimeRequest {
            /** @var OvertimeRequest $khoa */
            $khoa = OvertimeRequest::query()
                ->whereKey($don->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (! $khoa->status->isEditable()) {
                throw new RequestNotEditableException($khoa->status->label());
            }

            $khoa->forceFill(['status' => RequestStatus::Cancelled])->save();

            return $khoa->refresh();
        });
    }
}
