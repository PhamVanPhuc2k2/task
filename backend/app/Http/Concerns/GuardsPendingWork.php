<?php

declare(strict_types=1);

namespace App\Http\Concerns;

use App\Domain\Attendance\Enums\AdjustmentStatus;
use App\Domain\Attendance\Models\AttendanceAdjustment;
use App\Domain\Leave\Enums\LeaveStatus;
use App\Domain\Leave\Models\LateArrivalRequest;
use App\Domain\Leave\Models\LeaveRequest;
use App\Support\Exceptions\PeriodHasPendingWorkException;
use Carbon\CarbonImmutable;

/**
 * Không chốt sổ một kỳ còn đơn chờ duyệt.
 *
 * ## Vì sao luật này tồn tại
 *
 * Chốt sổ khoá cả giờ công lẫn đơn từ. Một đơn còn treo qua ngày chốt là một
 * đơn **không ai duyệt được nữa** — kể cả giám đốc, trừ khi mở khoá lại cả kỳ.
 * Nhân viên đã làm đúng phần việc của mình; thứ biến mất là câu trả lời.
 *
 * Không có phép kiểm này thì cách hỏng điển hình là: giám đốc chốt tháng 8 vào
 * ngày 02/09, ba đơn giải trình nộp hôm 31/08 chết theo, và ba người đó phát
 * hiện ra khi bảng lương về.
 *
 * ## Vì sao nằm ở tầng Http chứ không trong `ClosePeriodAction`
 *
 * Phép kiểm đọc ba bảng thuộc **hai miền** — `attendance_adjustments` ở
 * Attendance, `leave_requests` và `late_arrival_requests` ở Leave. Miền không
 * được gọi miền, còn Http là một trong hai tầng được phép biết nhiều miền. Cùng
 * lý do đã ghi ở `GuardsClosedPeriods` và `ResolvesApprovedLeave`.
 *
 * ## Ba loại đơn, không phải một
 *
 * Đơn giải trình là loại rõ ràng nhất. Nhưng đơn nghỉ và đơn đi muộn còn chờ
 * cũng đổi số ngày công nếu được duyệt sau đó — mà duyệt sau khi chốt thì đã bị
 * `GuardsClosedPeriods` chặn. Nên chúng cũng mắc kẹt y hệt.
 */
trait GuardsPendingWork
{
    /**
     * Ném lỗi nếu kỳ còn bất kỳ đơn nào chờ duyệt.
     *
     * @param  string  $ky  dạng `YYYY-MM`
     */
    protected function guardNoPendingWork(string $ky): void
    {
        $conTreo = array_filter($this->demDonConTreo($ky));

        if ($conTreo !== []) {
            throw new PeriodHasPendingWorkException($ky, $conTreo);
        }
    }

    /**
     * Số đơn còn chờ duyệt trong kỳ, theo từng loại.
     *
     * Trả cả những loại bằng 0 để giao diện vẽ được bảng đầy đủ — biết "0 đơn
     * nghỉ chờ duyệt" khác với không biết gì về đơn nghỉ.
     *
     * @return array<string, int> nhãn loại đơn => số đơn
     */
    protected function demDonConTreo(string $ky): array
    {
        [$tu, $den] = $this->khoangNgay($ky);

        return [
            'đơn giải trình công' => AttendanceAdjustment::query()
                ->where('status', AdjustmentStatus::Pending->value)
                ->whereBetween('work_date', [$tu, $den])
                ->count(),

            /*
            | Đơn nghỉ tính theo GIAO NHAU, không theo ngày bắt đầu.
            |
            | Một đơn từ 30/08 sang 02/09 vẫn đổi số ngày công của tháng 8. Chỉ
            | lọc `start_date` trong kỳ thì đơn vắt hai kỳ lọt qua — đúng chỗ
            | dễ lọt nhất, cùng cái bẫy đã bịt ở `GuardsClosedPeriods`.
            */
            'đơn nghỉ' => LeaveRequest::query()
                ->where('status', LeaveStatus::Pending->value)
                ->where('start_date', '<=', $den)
                ->where('end_date', '>=', $tu)
                ->count(),

            'đơn xin đi muộn / về sớm' => LateArrivalRequest::query()
                ->where('status', LeaveStatus::Pending->value)
                ->whereBetween('date', [$tu, $den])
                ->count(),
        ];
    }

    /**
     * Ngày đầu và ngày cuối của kỳ, dạng chuỗi `Y-m-d`.
     *
     * So sánh chuỗi chứ không dựng khoảng thời gian: mọi cột ngày ở đây đều là
     * nhãn ngày theo lịch Việt Nam, không phải mốc trên trục thời gian.
     *
     * @return array{string, string}
     */
    private function khoangNgay(string $ky): array
    {
        $dau = CarbonImmutable::parse($ky.'-01');

        return [$dau->toDateString(), $dau->endOfMonth()->toDateString()];
    }
}
