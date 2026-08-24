<?php

declare(strict_types=1);

namespace App\Domain\Payroll\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Payroll\Models\BonusAllocation;
use App\Domain\Payroll\Models\BonusPool;
use App\Support\Exceptions\BonusExceedsPoolException;
use App\Support\Exceptions\BonusPoolNotEditableException;
use Illuminate\Support\Facades\DB;

/**
 * Chia quỹ thưởng cho từng người.
 *
 * Nhận **toàn bộ** danh sách phần chia và thay thế hết, không sửa từng dòng.
 * Chia thưởng là một quyết định trên cả nhóm — sửa lẻ từng người thì trạng thái
 * trung gian có thể vượt quỹ, và người dùng phải tự nhớ đã chia bao nhiêu.
 *
 * Không có đường nào ghi số âm: `amount` bị chặn ở tầng validate, ở đây, và ở
 * ràng buộc `CHECK` của database. Ba lớp cho cùng một luật vì đây là luật khiến
 * hệ thống không thể biến thành công cụ phạt tiền.
 */
final class AllocateBonusAction
{
    /**
     * @param  list<array{user: User, amount: string, reason: string}>  $phanChia
     */
    public function execute(BonusPool $quy, array $phanChia, User $actor): BonusPool
    {
        if (! $quy->status->isEditable()) {
            throw new BonusPoolNotEditableException($quy->status->label());
        }

        $tong = '0.00';

        foreach ($phanChia as $dong) {
            /** @var numeric-string $so */
            $so = $dong['amount'];
            /** @var numeric-string $tong */
            $tong = bcadd($tong, $so, 2);
        }

        /** @var numeric-string $quyTong */
        $quyTong = $quy->total_amount;

        if (bccomp($tong, $quyTong, 2) > 0) {
            throw new BonusExceedsPoolException($tong, $quyTong);
        }

        return DB::transaction(function () use ($quy, $phanChia, $actor): BonusPool {
            // Xoá rồi ghi lại: danh sách người nhận cũng thay đổi được, không
            // chỉ số tiền. Cập nhật từng dòng sẽ để sót người đã bị bỏ khỏi
            // danh sách và họ vẫn nhận thưởng.
            BonusAllocation::query()->where('pool_id', $quy->id)->delete();

            foreach ($phanChia as $dong) {
                BonusAllocation::query()->create([
                    'pool_id' => $quy->id,
                    'user_id' => $dong['user']->id,
                    'amount' => $dong['amount'],
                    'reason' => $dong['reason'],
                    'decided_by' => $actor->id,
                ]);
            }

            return $quy->load('allocations');
        });
    }
}
