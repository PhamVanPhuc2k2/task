<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Payroll;

use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Models\User;
use App\Domain\Payroll\Enums\BonusPoolStatus;
use App\Domain\Payroll\Models\BonusAllocation;
use App\Domain\Task\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Thưởng dự án của chính người đang đăng nhập.
 *
 * **Chỉ hiện quỹ đã chốt.** Bản nháp có con số còn đổi, mà đã cho xem một lần
 * thì mọi lần đổi sau đều bị đọc thành "bị cắt bớt" — kể cả khi con số tăng lên.
 *
 * Trả kèm **lý do**, có chủ ý: quỹ thưởng bí mật là nguồn nghi ngờ lớn nhất.
 * Nhưng chỉ phần của chính mình, không kèm phần của người khác — đó là thu nhập
 * của họ.
 */
final class MyBonusController
{
    public function __invoke(Request $request): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        abort_unless($actor->can(Permission::ViewOwnBonus->value), Response::HTTP_FORBIDDEN);

        $phan = BonusAllocation::query()
            ->where('user_id', $actor->id)
            ->whereHas(
                'pool',
                fn ($q) => $q->whereIn('status', [
                    BonusPoolStatus::Locked->value,
                    BonusPoolStatus::Distributed->value,
                ]),
            )
            ->with(['pool', 'decider'])
            ->get();

        // Tên dự án nằm ở miền Task, phần thưởng ở miền Payroll — hai miền
        // không gọi thẳng nhau được. Tầng Http ghép lại, trong một truy vấn.
        $tenDuAn = Project::query()
            ->whereIn('id', $phan->pluck('pool.project_id')->filter()->all())
            ->pluck('name', 'id');

        $tong = '0.00';

        foreach ($phan as $p) {
            /** @var numeric-string $so */
            $so = $p->amount;
            /** @var numeric-string $tong */
            $tong = bcadd($tong, $so, 2);
        }

        return new JsonResponse([
            'data' => [
                'total' => $tong,
                'items' => $phan
                    ->map(fn (BonusAllocation $p): array => [
                        'id' => $p->uuid,
                        'project' => $tenDuAn->get($p->pool?->project_id) ?? '—',
                        'amount' => $p->amount,
                        'reason' => $p->reason,
                        'status' => $p->pool?->status->value,
                        'status_label' => $p->pool?->status->label(),
                        'decided_by' => $p->decider?->name,
                    ])
                    ->values()
                    ->all(),
            ],
        ]);
    }
}
