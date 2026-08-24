<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Leave;

use App\Domain\Identity\Models\User;
use App\Domain\Leave\Actions\CancelLateArrivalAction;
use App\Domain\Leave\Models\LateArrivalRequest;
use App\Http\Concerns\PresentsLateArrivals;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Người nộp tự rút đơn xin đi muộn.
 *
 * Chỉ rút được đơn của CHÍNH MÌNH. Quản lý muốn huỷ thì dùng đường từ chối —
 * việc đó để lại người quyết định và lý do, còn rút đơn thì không.
 */
final class CancelLateArrivalController
{
    use PresentsLateArrivals;

    public function __invoke(
        Request $request,
        LateArrivalRequest $lateArrival,
        CancelLateArrivalAction $action,
    ): JsonResponse {
        /** @var User $actor */
        $actor = $request->user();

        abort_unless($lateArrival->user_id === $actor->id, Response::HTTP_FORBIDDEN);

        return new JsonResponse([
            'data' => $this->presentLateArrival($action->execute($lateArrival)),
        ]);
    }
}
