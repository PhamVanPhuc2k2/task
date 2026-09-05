<?php

declare(strict_types=1);

namespace App\Domain\Attendance\Actions;

use App\Domain\Attendance\Enums\AdjustmentStatus;
use App\Domain\Attendance\Models\AttendanceAdjustment;
use App\Domain\Identity\Models\User;
use App\Support\Exceptions\AdjustmentAlreadyRequestedException;
use Illuminate\Support\Facades\DB;

/**
 * Nhân viên nộp đơn giải trình cho một ngày công.
 *
 * Kiểm trùng bên trong giao dịch và có khoá dòng, cùng lý do với đơn nghỉ và
 * đơn đi muộn: hai lần bấm nút liên tiếp — hoặc một cú double-click — chạy song
 * song thì cả hai đều thấy "chưa có đơn nào" và cả hai đều ghi. Validate ở tầng
 * HTTP bắt được trường hợp thường gặp; chỗ này bắt trường hợp đua nhau.
 *
 * Không có hạn mức số đơn mỗi tháng, có chủ ý. Đơn giải trình không phải một
 * đặc quyền được tiêu — nó là lời khai về một ngày đã xảy ra, và người duyệt
 * mới là chỗ chặn. Đặt trần ở đây thì người thứ tư trong tháng phải im lặng
 * chịu một ngày công sai.
 */
final class SubmitAdjustmentAction
{
    public function execute(
        User $nguoiNop,
        string $ngay,
        string $lyDo,
        ?int $soPhutDeNghi,
    ): AttendanceAdjustment {
        return DB::transaction(function () use ($nguoiNop, $ngay, $lyDo, $soPhutDeNghi): AttendanceAdjustment {
            $daCo = AttendanceAdjustment::query()
                ->where('user_id', $nguoiNop->id)
                ->where('work_date', $ngay)
                ->blocking()
                ->lockForUpdate()
                ->exists();

            if ($daCo) {
                throw new AdjustmentAlreadyRequestedException($ngay);
            }

            return AttendanceAdjustment::query()->create([
                'user_id' => $nguoiNop->id,
                'work_date' => $ngay,
                'reason' => $lyDo,
                'requested_minutes' => $soPhutDeNghi,
                'status' => AdjustmentStatus::Pending,
            ]);
        });
    }
}
