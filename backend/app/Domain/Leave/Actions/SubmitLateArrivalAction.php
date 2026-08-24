<?php

declare(strict_types=1);

namespace App\Domain\Leave\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Leave\Enums\LeaveStatus;
use App\Domain\Leave\Models\LateArrivalRequest;
use App\Support\Exceptions\LateArrivalAlreadyRequestedException;
use Illuminate\Support\Facades\DB;

/**
 * Nộp đơn xin đi làm muộn.
 *
 * Kiểm trùng bên trong giao dịch và có khoá dòng, cùng lý do với đơn nghỉ: hai
 * lần bấm nút liên tiếp — hoặc một cú double-click — chạy song song thì cả hai
 * đều thấy "chưa có đơn nào" và cả hai đều ghi. Validate ở tầng HTTP bắt được
 * trường hợp thường gặp; chỗ này bắt trường hợp đua nhau.
 */
final class SubmitLateArrivalAction
{
    public function execute(
        User $nguoiNop,
        string $ngay,
        string $gioDuKien,
        string $lyDo,
    ): LateArrivalRequest {
        return DB::transaction(function () use ($nguoiNop, $ngay, $gioDuKien, $lyDo): LateArrivalRequest {
            $daCo = LateArrivalRequest::query()
                ->where('user_id', $nguoiNop->id)
                ->where('date', $ngay)
                ->blocking()
                ->lockForUpdate()
                ->exists();

            if ($daCo) {
                throw new LateArrivalAlreadyRequestedException($ngay);
            }

            return LateArrivalRequest::query()->create([
                'user_id' => $nguoiNop->id,
                'date' => $ngay,
                'expected_arrival' => $gioDuKien,
                'reason' => $lyDo,
                'status' => LeaveStatus::Pending,
            ]);
        });
    }
}
