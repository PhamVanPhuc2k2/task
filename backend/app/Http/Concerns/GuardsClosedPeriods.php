<?php

declare(strict_types=1);

namespace App\Http\Concerns;

use App\Domain\Attendance\Models\AttendancePeriod;
use App\Support\Exceptions\PeriodLockException;

/**
 * Chặn mọi thao tác ghi vào một kỳ công đã chốt.
 *
 * ## Vì sao ở tầng Http chứ không ở Action
 *
 * Quy ước của dự án là luật nghiệp vụ phải có hiệu lực trong Action, không chỉ
 * ở FormRequest. Nhưng luật này **bắc qua ba miền**: Attendance giữ kỳ công,
 * còn thứ bị chặn nằm ở Report (báo cáo ngày) và Leave (đơn từ) — mà các miền
 * nghiệp vụ không được gọi nhau (README, "Quy tắc phụ thuộc").
 *
 * `Http` là một trong hai tầng được phép biết nhiều miền cùng lúc, cùng lý do
 * đã ghi ở `ResolvesApprovedLeave` và `RemindMissingReportsCommand`. Riêng
 * miền Attendance thì `ReviewWorkDayAction` kiểm thêm lần nữa ở trong — cùng
 * miền nên gọi được, và hai lớp là thói quen của dự án với mọi ràng buộc mà
 * hỏng sẽ hỏng im lặng.
 *
 * ## Một truy vấn cho mỗi request, không phải mỗi ngày
 *
 * Đơn nghỉ dài ba mươi ngày mà hỏi từng ngày là ba mươi câu SQL cho một câu trả
 * lời gần như luôn giống nhau. `guardPeriodRangeOpen()` gom về một truy vấn.
 */
trait GuardsClosedPeriods
{
    /**
     * Ngày này có thuộc kỳ đã chốt không.
     *
     * @param  string  $truong  Ô nhập để gắn câu lỗi vào. Người dùng phải biết
     *                          sửa chỗ nào, và một lỗi rơi xuống dải chung thì
     *                          họ chỉ đọc được "có gì đó sai".
     */
    protected function guardPeriodOpen(string $workDate, string $truong = 'date'): void
    {
        $this->guardPeriodRangeOpen($workDate, $workDate, $truong);
    }

    /** Khoảng ngày này có chạm vào kỳ đã chốt nào không. */
    protected function guardPeriodRangeOpen(
        string $tuNgay,
        string $denNgay,
        string $truong = 'start_date',
    ): void {
        $daChot = AttendancePeriod::query()
            ->whereBetween('period', [
                AttendancePeriod::periodOf($tuNgay),
                AttendancePeriod::periodOf($denNgay),
            ])
            ->locked()
            ->orderBy('period')
            ->value('period');

        if (is_string($daChot)) {
            throw PeriodLockException::daChot($daChot, $truong);
        }
    }
}
