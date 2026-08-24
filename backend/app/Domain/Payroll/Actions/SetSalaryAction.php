<?php

declare(strict_types=1);

namespace App\Domain\Payroll\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Payroll\Data\SetSalaryData;
use App\Domain\Payroll\Enums\PayrollAuditEvent;
use App\Domain\Payroll\Models\SalaryRecord;
use App\Support\Exceptions\SalaryPeriodOverlapException;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Đặt mức lương mới cho một nhân viên.
 *
 * Hai thao tác đi liền: **đóng** mức đang hiệu lực và **mở** mức mới. Phải nằm
 * trong cùng một giao dịch — đóng xong mà mở hỏng thì nhân viên không còn mức
 * lương nào, và đó là loại hỏng không ai phát hiện cho tới kỳ trả lương.
 */
final class SetSalaryAction
{
    public function __construct(
        private readonly RecordPayrollAuditAction $ghiNhatKy,
    ) {}

    public function execute(User $nhanVien, SetSalaryData $data, User $actor): SalaryRecord
    {
        $hieuLucTu = CarbonImmutable::parse($data->effectiveFrom)->startOfDay();

        return DB::transaction(function () use ($nhanVien, $data, $actor, $hieuLucTu): SalaryRecord {
            // Khoá dòng đang hiệu lực trong lúc giao dịch chạy. Không khoá thì
            // hai người cùng đặt lương một lúc sẽ đóng cùng một dòng và để lại
            // hai dòng cùng hiệu lực — đúng thứ ngoại lệ bên dưới đang chặn.
            $hienHanh = SalaryRecord::query()
                ->where('user_id', $nhanVien->id)
                ->current()
                ->lockForUpdate()
                ->first();

            if ($hienHanh instanceof SalaryRecord) {
                if ($hieuLucTu->lessThanOrEqualTo($hienHanh->effective_from)) {
                    throw new SalaryPeriodOverlapException(
                        $hienHanh->effective_from->toDateString(),
                    );
                }

                // Mức cũ kết thúc vào hôm trước ngày mức mới bắt đầu. Không trừ
                // một ngày thì có đúng một ngày hai mức cùng hiệu lực.
                $hienHanh->forceFill([
                    'effective_to' => $hieuLucTu->subDay()->toDateString(),
                ])->save();
            }

            $moi = SalaryRecord::query()->create([
                'user_id' => $nhanVien->id,
                'effective_from' => $hieuLucTu->toDateString(),
                'effective_to' => null,
                'base_salary' => $data->baseSalary,
                'allowance' => $data->allowance,
                'currency' => $data->currency,
                'reason' => $data->reason,
                'created_by' => $actor->id,
            ]);

            $this->ghiNhatKy->execute(
                event: PayrollAuditEvent::SalaryChanged,
                actor: $actor,
                subject: $nhanVien,
                // KHÔNG ghi số tiền vào nhật ký — xem PayrollAudit. Ghi ngày
                // hiệu lực là đủ để lần ra bản ghi tương ứng.
                context: ['effective_from' => $hieuLucTu->toDateString()],
            );

            return $moi;
        });
    }
}
