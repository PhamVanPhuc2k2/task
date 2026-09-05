<?php

declare(strict_types=1);

namespace App\Domain\Leave\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Leave\Data\AnnualLeavePolicy;
use App\Domain\Leave\Models\LeaveBalance;
use App\Support\Exceptions\CarryOverExceedsCapException;
use Illuminate\Support\Facades\DB;

/**
 * Nhân sự can thiệp vào quỹ phép của một người: chuyển phép tồn, thưởng thêm
 * ngày, hoặc ghi đè hẳn con số.
 *
 * ## Không có dòng thì quay về tự tính
 *
 * Đặt tất cả về 0 và xoá ghi chú thì **xoá dòng**, không lưu một dòng toàn số
 * 0. Bảng thưa chỉ giữ được ý nghĩa "chưa ai động tới" nếu nó thật sự thưa; một
 * dòng rỗng làm màn hình hiện "đã điều chỉnh" cho người chưa ai đụng vào, và
 * nhân sự không có cách nào gỡ nhãn đó ra.
 *
 * ## Trần phép tồn kiểm ở ĐÂY, không chỉ ở FormRequest
 *
 * Nó là chính sách nghiệp vụ, không phải luật định dạng dữ liệu — cùng lý do đã
 * ghi ở `SubmitLeaveRequestAction`. Chặn ở tầng nhận request thì bất kỳ đường
 * nào khác gọi tới Action sau này (một lệnh nhập liệu, một job đồng bộ) đều đi
 * vòng qua được mà không ai nhận ra.
 */
final class SaveLeaveBalanceAction
{
    /**
     * @return LeaveBalance|null `null` nghĩa là đã xoá dòng — quay về tự tính.
     */
    public function execute(
        User $nhanVien,
        User $actor,
        int $nam,
        ?float $ghiDe,
        float $phepTon,
        float $dieuChinh,
        ?string $ghiChu,
    ): ?LeaveBalance {
        $tran = AnnualLeavePolicy::fromConfig()->carryOverMaxDays;

        if ($phepTon > $tran) {
            throw new CarryOverExceedsCapException($tran);
        }

        return DB::transaction(function () use (
            $nhanVien, $actor, $nam, $ghiDe, $phepTon, $dieuChinh, $ghiChu,
        ): ?LeaveBalance {
            /*
            | Khoá dòng trong lúc kiểm.
            |
            | Hai người cùng mở màn quỹ phép và cùng bấm Lưu thì `unique` trên
            | (user_id, year) biến cái thứ hai thành một lỗi database thô, chứ
            | không phải câu tiếng Việt. `updateOrCreate` cũng không cứu được:
            | nó đọc rồi ghi, và khoảng giữa hai bước là chỗ đua nhau.
            */
            $dong = LeaveBalance::query()
                ->where('user_id', $nhanVien->id)
                ->where('year', $nam)
                ->lockForUpdate()
                ->first();

            $trong = $ghiDe === null
                && $phepTon === 0.0
                && $dieuChinh === 0.0
                && ($ghiChu === null || $ghiChu === '');

            if ($trong) {
                $dong?->delete();

                return null;
            }

            $thuoc = [
                'entitled_days_override' => $ghiDe,
                'carried_over_days' => $phepTon,
                'adjustment_days' => $dieuChinh,
                'note' => $ghiChu,
                'updated_by' => $actor->id,
            ];

            if ($dong instanceof LeaveBalance) {
                $dong->forceFill($thuoc)->save();

                return $dong->refresh();
            }

            return LeaveBalance::query()->create(
                ['user_id' => $nhanVien->id, 'year' => $nam] + $thuoc,
            );
        });
    }
}
