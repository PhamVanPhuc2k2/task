<?php

declare(strict_types=1);

namespace App\Domain\Payroll\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Payroll\Enums\BonusPoolStatus;
use App\Domain\Payroll\Models\BonusPool;
use App\Support\Exceptions\BonusExceedsPoolException;
use App\Support\Exceptions\BonusPoolNotEditableException;

/**
 * Lập hoặc sửa quỹ thưởng của một dự án.
 *
 * Một dự án một quỹ (ràng buộc `unique` ở migration), nên đây vừa là tạo mới
 * vừa là cập nhật.
 */
final class SaveBonusPoolAction
{
    public function execute(
        int $projectId,
        string $totalAmount,
        string $conditionNote,
        User $actor,
    ): BonusPool {
        $quy = BonusPool::query()->where('project_id', $projectId)->first();

        if ($quy instanceof BonusPool) {
            if (! $quy->status->isEditable()) {
                throw new BonusPoolNotEditableException($quy->status->label());
            }

            // Hạ quỹ xuống dưới tổng đã chia sẽ để lại một quỹ chia vượt. Chặn
            // ngay đây thay vì đợi tới lúc chốt — người dùng cần biết ngay lúc
            // gõ số, không phải sau khi đã chia xong cho hai mươi người.
            /** @var numeric-string $daChia */
            $daChia = $quy->loadMissing('allocations')->allocatedTotal();

            /** @var numeric-string $moi */
            $moi = $totalAmount;

            if (bccomp($moi, $daChia, 2) < 0) {
                throw new BonusExceedsPoolException($daChia, $moi);
            }

            $quy->forceFill([
                'total_amount' => $totalAmount,
                'condition_note' => $conditionNote,
            ])->save();

            return $quy;
        }

        return BonusPool::query()->create([
            'project_id' => $projectId,
            'total_amount' => $totalAmount,
            'condition_note' => $conditionNote,
            'status' => BonusPoolStatus::Draft,
            'created_by' => $actor->id,
        ]);
    }
}
