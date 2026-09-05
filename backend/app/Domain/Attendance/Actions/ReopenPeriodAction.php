<?php

declare(strict_types=1);

namespace App\Domain\Attendance\Actions;

use App\Domain\Attendance\Enums\PeriodStatus;
use App\Domain\Attendance\Models\AttendancePeriod;
use App\Domain\Identity\Models\User;
use App\Support\Exceptions\PeriodLockException;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

/**
 * Mở khoá một kỳ công đã chốt.
 *
 * ## Lý do là BẮT BUỘC, và đó là toàn bộ điểm của tính năng này
 *
 * Mở khoá nghĩa là đổi số liệu đã dùng để trả lương. Không có lý do ghi lại thì
 * ba tháng sau không ai trả lời được *"vì sao giờ công tháng 9 khác con số trên
 * phiếu lương tháng 9"* — và câu hỏi đó sẽ được hỏi.
 *
 * Ràng buộc độ dài tối thiểu nằm ở FormRequest; ở đây chỉ kiểm không rỗng, vì
 * một đường ghi khác sau này (lệnh dòng lệnh, job) đi vòng qua tầng HTTP là đi
 * vòng qua luôn mọi phép kiểm ở đó.
 *
 * ## Quyền hẹp hơn quyền chốt, có chủ ý
 *
 * Admin chốt sổ được nhưng KHÔNG mở khoá được — xem `Permission::ReopenPeriod`
 * và ngoại lệ tương ứng trong `Role::defaultPermissions()`. Chốt là việc hành
 * chính cuối kỳ; mở khoá là việc đụng vào tiền đã trả.
 */
final class ReopenPeriodAction
{
    public function execute(string $ky, string $lyDo, User $actor): AttendancePeriod
    {
        $lyDo = trim($lyDo);

        if ($lyDo === '') {
            throw PeriodLockException::chuaChot($ky);
        }

        return DB::transaction(function () use ($ky, $lyDo, $actor): AttendancePeriod {
            $kyCong = AttendancePeriod::query()
                ->where('period', $ky)
                ->lockForUpdate()
                ->first();

            // Chưa từng chốt, hoặc đã mở khoá rồi — cả hai đều là "không có gì
            // để mở". Gộp lại vì với người dùng chúng là cùng một tình huống:
            // kỳ này đang mở.
            if (! $kyCong instanceof AttendancePeriod || ! $kyCong->isLocked()) {
                throw PeriodLockException::chuaChot($ky);
            }

            $kyCong->forceFill([
                'status' => PeriodStatus::Open,
                'reopened_at' => Date::now(),
                'reopened_by' => $actor->id,
                'reopen_reason' => $lyDo,
            ])->save();

            return $kyCong;
        });
    }
}
