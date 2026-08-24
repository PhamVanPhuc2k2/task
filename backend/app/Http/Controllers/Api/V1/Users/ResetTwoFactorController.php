<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Users;

use App\Domain\Identity\Actions\RecordUserActivityAction;
use App\Domain\Identity\Enums\UserActivityEvent;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\TwoFactorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Attributes\Controllers\Authorize;

/**
 * Quản trị viên gỡ xác thực hai lớp cho nhân viên mất điện thoại.
 *
 * Không có đường này thì mất máy là mất tài khoản vĩnh viễn — và hệ thống bắt
 * buộc 2FA nên không có lối vòng nào khác.
 *
 * Sau khi gỡ, lần đăng nhập kế tiếp nhân viên buộc phải thiết lập lại từ đầu.
 */
final class ResetTwoFactorController
{
    #[Authorize('resetTwoFactor', 'user')]
    public function __invoke(
        Request $request,
        User $user,
        TwoFactorService $twoFactor,
        RecordUserActivityAction $ghiNhatKy,
    ): JsonResponse {
        /** @var User $actor */
        $actor = $request->user();

        $twoFactor->reset($user);

        // Gỡ lớp bảo vệ thứ hai của người khác là thao tác nhạy cảm nhất trong
        // phần nhân sự — bắt buộc phải có vết ai làm.
        $ghiNhatKy->execute(
            user: $user,
            event: UserActivityEvent::TwoFactorReset,
            causer: $actor,
        );

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
