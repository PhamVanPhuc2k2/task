<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Payroll;

use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Models\User;
use App\Domain\Payroll\Actions\AllocateBonusAction;
use App\Domain\Payroll\Models\BonusPool;
use App\Domain\Task\Models\Project;
use App\Http\Requests\Payroll\AllocateBonusRequest;
use App\Http\Resources\BonusPoolResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

/**
 * Chia quỹ thưởng cho từng người.
 *
 * Controller một hành động vì đây không phải thao tác CRUD — quy ước của dự án,
 * và bộ kiểm thử kiến trúc bắt buộc.
 *
 * **Thay thế toàn bộ danh sách**, không sửa lẻ từng người: chia thưởng là một
 * quyết định trên cả nhóm, và trạng thái trung gian khi sửa lẻ có thể vượt quỹ.
 *
 * Không có đường nào ghi số âm. Ba lớp chặn cho cùng một luật — validate, Action
 * và ràng buộc `CHECK` của database — vì lý do là pháp lý chứ không phải kỹ
 * thuật: Điều 127 Bộ luật Lao động 2019 cấm phạt tiền, và một khoản "trừ
 * thưởng" mang số âm chính là khoản phạt trừ vào thu nhập.
 */
final class AllocateBonusController
{
    public function __invoke(
        AllocateBonusRequest $request,
        Project $project,
        AllocateBonusAction $action,
    ): JsonResponse {
        /** @var User $actor */
        $actor = $request->user();

        abort_unless($actor->can(Permission::ManageBonus->value), Response::HTTP_FORBIDDEN);

        $quy = BonusPool::query()
            ->where('project_id', $project->id)
            ->with('allocations')
            ->firstOrFail();

        /** @var list<array{user_id: string, amount: string, reason: string}> $gui */
        $gui = $request->array('allocations');

        // Đổi uuid sang model trong MỘT truy vấn. Tra từng người trong vòng lặp
        // là hai mươi truy vấn cho một lần chia thưởng hai mươi người.
        $nguoi = User::query()
            ->whereIn('uuid', array_column($gui, 'user_id'))
            ->get()
            ->keyBy('uuid');

        $phanChia = [];

        foreach ($gui as $i => $dong) {
            $u = $nguoi->get($dong['user_id']);

            if (! $u instanceof User) {
                throw ValidationException::withMessages([
                    "allocations.{$i}.user_id" => 'Không tìm thấy nhân viên này.',
                ]);
            }

            $phanChia[] = [
                'user' => $u,
                'amount' => $dong['amount'],
                'reason' => $dong['reason'],
            ];
        }

        $daChia = $action->execute($quy, $phanChia, $actor);

        return BonusPoolResource::make($daChia->load('allocations.user'))->response();
    }
}
