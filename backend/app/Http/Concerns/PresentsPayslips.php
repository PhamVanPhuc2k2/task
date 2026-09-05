<?php

declare(strict_types=1);

namespace App\Http\Concerns;

use App\Domain\Attendance\Models\AttendancePeriod;
use App\Domain\Identity\Models\User;
use App\Domain\Payroll\Data\OvertimeLine;
use App\Domain\Payroll\Data\Payslip;

/**
 * Hình dạng JSON của một phiếu lương.
 *
 * ## Trả CẢ ĐƯỜNG ĐI, không chỉ tổng cuối
 *
 * Câu hỏi thật của người nhận lương không phải *"tôi được bao nhiêu"* — con số
 * đó nằm trên tài khoản ngân hàng — mà là ***"vì sao tháng này ít hơn tháng
 * trước"***. Cộng tay các dòng phải ra đúng tổng; đó là phép kiểm mà bất kỳ ai
 * cũng làm được, và nó chỉ làm được nếu các dòng đều có mặt.
 *
 * ## `is_final` nói ra phiếu này đã chắc chưa
 *
 * Kỳ chưa chốt sổ thì mọi con số còn đổi được: một đơn giải trình được duyệt
 * chiều nay sẽ đổi số giờ thiếu của cả tháng. Không nói ra thì người ta chụp
 * màn hình một phiếu tạm rồi tháng sau đối chiếu với phiếu thật và không hiểu.
 */
trait PresentsPayslips
{
    /**
     * @return array<string, mixed>
     */
    protected function presentPayslip(
        Payslip $phieu,
        bool $daChotKy,
        ?User $nhanVien = null,
    ): array {
        $data = [
            'period' => $phieu->period,

            /*
            | Đã chốt sổ kỳ này chưa.
            |
            | Chốt sổ khoá giờ công, đơn từ và báo cáo ngày của kỳ — sau đó
            | không con số nào trên phiếu đổi được nữa. Trước đó thì phiếu là
            | bản tạm, và màn hình phải nói thẳng điều đó.
            */
            'is_final' => $daChotKy,

            'minutes' => [
                // Số phút chuẩn của kỳ THEO LỊCH THỰC TẾ — mẫu số của lương giờ.
                // Hiện ra ngay cạnh lương giờ để con số đó không đến từ hư không.
                'standard' => $phieu->standardMinutes,
                'required' => $phieu->requiredMinutes,
                'worked' => $phieu->workedMinutes,
                'paid_leave' => $phieu->paidLeaveMinutes,
                'unpaid_leave' => $phieu->unpaidLeaveMinutes,
                'shortfall' => $phieu->shortfallMinutes,
                'overtime' => $phieu->overtimeMinutes,
            ],

            'money' => [
                'base_salary' => $phieu->baseSalary,
                'allowance' => $phieu->allowance,
                'hourly_rate' => $phieu->hourlyRate,
                'shortfall_deduction' => $phieu->shortfallDeduction,
                'unpaid_leave_deduction' => $phieu->unpaidLeaveDeduction,
                'overtime_pay' => $phieu->overtimePay,
                'net_total' => $phieu->netTotal,
            ],

            // Gom theo hệ số, sắp từ thấp lên cao: phiếu của hai tháng phải đọc
            // giống nhau, chứ không chạy theo thứ tự đơn được duyệt.
            'overtime_lines' => array_map(
                static fn (OvertimeLine $d): array => [
                    'percent' => $d->percent,
                    'minutes' => $d->minutes,
                    'amount' => $d->amount,
                ],
                $phieu->overtimeLines,
            ),
        ];

        if ($nhanVien instanceof User) {
            $data['user'] = [
                'id' => $nhanVien->uuid,
                'name' => $nhanVien->name,
                'employee_code' => $nhanVien->employee_code,
                'department' => $nhanVien->department?->name,
            ];
        }

        return $data;
    }

    /** Kỳ này đã chốt sổ chưa — quyết định phiếu là bản chính hay bản tạm. */
    protected function kyDaChot(string $ky): bool
    {
        return AttendancePeriod::query()->where('period', $ky)->locked()->exists();
    }
}
