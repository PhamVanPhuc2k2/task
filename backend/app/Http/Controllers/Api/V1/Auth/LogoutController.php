<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Concerns\ManagesPendingLogin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

final class LogoutController
{
    use ManagesPendingLogin;

    public function __invoke(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        // Xoá cờ để frontend biết cần đưa về trang đăng nhập.
        $this->clearAuthenticatedMark();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
