<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Leave;

use App\Domain\Identity\Models\User;
use App\Domain\Leave\Actions\ResolveLeaveBalanceAction;
use App\Http\Concerns\PresentsLeaveBalances;
use App\Http\Concerns\ResolvesLeaveYear;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Quỹ phép năm của chính người đang đăng nhập.
 *
 * Không cần quyền gì thêm: ai cũng xem được quỹ phép của mình — cùng khuôn với
 * chấm công, báo cáo và lương.
 *
 * Trả kèm số dư năm trước để người dùng tự thấy mình còn tồn bao nhiêu và đi
 * hỏi nhân sự nếu chưa được chuyển sang — chuyển phép tồn không tự động.
 */
final class MyLeaveBalanceController
{
    use PresentsLeaveBalances;
    use ResolvesLeaveYear;

    public function __invoke(
        Request $request,
        ResolveLeaveBalanceAction $action,
    ): JsonResponse {
        /** @var User $actor */
        $actor = $request->user();

        $nam = $this->namQuyPhep($request);

        return new JsonResponse([
            'data' => $this->presentBalance(
                $action->execute($actor, $nam),
                conLaiNamTruoc: $action->execute($actor, $nam - 1)->remainingDays(),
            ),
        ]);
    }
}
