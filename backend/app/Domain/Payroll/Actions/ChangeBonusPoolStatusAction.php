<?php

declare(strict_types=1);

namespace App\Domain\Payroll\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Payroll\Enums\BonusPoolStatus;
use App\Domain\Payroll\Models\BonusAllocation;
use App\Domain\Payroll\Models\BonusPool;
use App\Domain\Payroll\Notifications\BonusLockedNotification;
use App\Support\Exceptions\InvalidBonusPoolTransitionException;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Notification;

/**
 * Chốt quỹ, rồi đánh dấu đã chi.
 *
 * Chốt là mốc quan trọng nhất của cả tính năng: từ giây đó nhân viên xem được
 * phần của mình, không ai sửa được nữa, **và họ được báo**.
 *
 * Nhận `$tenDuAn` từ ngoài chứ không tự tra: `Project` thuộc miền Task, mà
 * deptrac chỉ cho `Payroll → Identity, Support`. Tầng Http là nơi duy nhất biết
 * cả hai miền — cùng cách đã dùng ở `SaveBonusPoolAction`.
 */
final class ChangeBonusPoolStatusAction
{
    public function execute(
        BonusPool $quy,
        BonusPoolStatus $dich,
        string $tenDuAn,
    ): BonusPool {
        if (! in_array($dich, $quy->status->allowedTransitions(), strict: true)) {
            throw new InvalidBonusPoolTransitionException(
                $quy->status->label(),
                $dich->label(),
            );
        }

        $quy->forceFill([
            'status' => $dich,
            // Ghi mốc thời gian tương ứng. Hai cột riêng chứ không một cột
            // `status_changed_at`: cần biết chốt lúc nào VÀ chi lúc nào, mà
            // một cột thì lần chuyển sau ghi đè mất lần trước.
            ...match ($dich) {
                BonusPoolStatus::Locked => ['locked_at' => Date::now()],
                BonusPoolStatus::Distributed => ['distributed_at' => Date::now()],
                BonusPoolStatus::Draft => [],
            },
        ])->save();

        if ($dich === BonusPoolStatus::Locked) {
            $this->baoChoNguoiNhan($quy, $tenDuAn);
        }

        return $quy;
    }

    /**
     * Báo cho mọi người trong danh sách chia.
     *
     * Chỉ ở bước **chốt**, không ở bước "đã chi": lúc chi thì nhân viên đã biết
     * số của mình từ lâu, báo thêm một lần nữa chỉ là nhiễu.
     *
     * `Notification::send` với cả tập người nhận thay vì lặp `->notify()` từng
     * người — một lần đẩy vào hàng đợi thay vì hai mươi lần.
     */
    private function baoChoNguoiNhan(BonusPool $quy, string $tenDuAn): void
    {
        $nguoiNhan = User::query()
            ->whereIn(
                'id',
                BonusAllocation::query()->where('pool_id', $quy->id)->pluck('user_id'),
            )
            // Người đã nghỉ việc không nhận thông báo. Họ vẫn giữ phần thưởng
            // trong bảng — kế toán vẫn phải chi — nhưng gửi thông báo vào một
            // tài khoản đã vô hiệu hoá thì không ai đọc.
            ->where('is_active', true)
            ->get();

        if ($nguoiNhan->isEmpty()) {
            return;
        }

        Notification::send($nguoiNhan, new BonusLockedNotification($tenDuAn));
    }
}
