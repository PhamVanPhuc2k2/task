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
 * Chốt sổ một kỳ công.
 *
 * Sau khi chốt, **không ai sửa được số liệu của kỳ đó** — kể cả admin. Đây là
 * nền móng của mọi phép tính tiền: trả lương từ những con số còn sửa được nghĩa
 * là không bao giờ trả lời được câu "phiếu lương này tính từ đâu ra".
 *
 * ## Không chốt được kỳ chưa kết thúc
 *
 * Chốt giữa kỳ là khoá luôn những ngày chưa ai đi làm. Người ta sẽ phát hiện ra
 * vào sáng hôm sau, khi nhịp tim chấm công không ghi được gì và không có lời
 * giải thích nào trên màn hình.
 *
 * Phép so sánh nằm ở `AttendancePeriod::daKetThuc()` vì tầng Http cũng cần đúng
 * câu trả lời đó — xem chú thích ở đấy, kể cả cái bẫy múi giờ.
 *
 * ## Ghi nhật ký, và nhật ký nằm ở `payroll_audits`
 *
 * Cùng chỗ với việc đổi mức lương, vì cùng một họ: hành vi quyết định số liệu
 * dùng để trả lương. Bảng đó chỉ ghi thêm — không sửa, không xoá.
 */
final class ClosePeriodAction
{
    public function execute(string $ky, User $actor): AttendancePeriod
    {
        $this->kiemKyDaKetThuc($ky);

        return DB::transaction(function () use ($ky, $actor): AttendancePeriod {
            /*
            | Khoá dòng trong lúc kiểm.
            |
            | Hai người cùng bấm Chốt sổ thì cả hai đều thấy "chưa chốt" rồi cả
            | hai cùng ghi — và `unique` trên `period` biến cái thứ hai thành
            | một lỗi database thô, không phải câu tiếng Việt.
            */
            $ky_ = AttendancePeriod::query()
                ->where('period', $ky)
                ->lockForUpdate()
                ->first();

            if ($ky_ instanceof AttendancePeriod && $ky_->isLocked()) {
                throw PeriodLockException::daChotRoi($ky);
            }

            $thuoc = [
                'status' => PeriodStatus::Closed,
                'closed_at' => Date::now(),
                'closed_by' => $actor->id,
            ];

            if ($ky_ instanceof AttendancePeriod) {
                // Chốt lại sau khi đã mở khoá. Giữ nguyên vết mở khoá lần trước
                // — xoá đi thì bảng chỉ còn nói "đã chốt", và lịch sử đóng mở
                // biến mất khỏi chỗ người ta nhìn đầu tiên.
                $ky_->forceFill($thuoc)->save();

                return $ky_;
            }

            return AttendancePeriod::query()->create(['period' => $ky] + $thuoc);
        });
    }

    private function kiemKyDaKetThuc(string $ky): void
    {
        if (! AttendancePeriod::daKetThuc($ky)) {
            throw PeriodLockException::chuaKetThuc($ky);
        }
    }
}
