<?php

declare(strict_types=1);

use App\Http\ApiExceptionRenderer;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api/v1',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Sanctum xác thực SPA bằng cookie phiên, không bằng token trong header.
        // Cookie httpOnly nên JavaScript không đọc được — xem README mục 1.9.
        $middleware->statefulApi();

        // Giới hạn tần suất cho TOÀN BỘ API, kể cả đường chưa đăng nhập.
        // Limiter tên `api` tự chọn hạn mức theo phương thức HTTP — xem
        // AppServiceProvider::configureRateLimiting().
        //
        // Header bảo mật đặt ở đây để không endpoint nào sót: gắn theo từng
        // route thì chỉ cần một route mới quên là hở.
        $middleware->api(prepend: [
            SecurityHeaders::class,
            'throttle:api',
        ]);

        $middleware->alias([
            'active' => EnsureUserIsActive::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        // Không đưa dữ liệu nhạy cảm vào session flash hay báo cáo lỗi.
        // Mặc định của Laravel đã có 'password'; thêm mã OTP và mã khôi phục
        // vì đây là hệ thống bắt buộc xác thực hai lớp — xem README mục 1.9.
        $exceptions->dontFlash([
            'current_password',
            'password',
            'password_confirmation',
            'code',
            'recovery_code',
        ]);

        // Mọi lỗi của API đi ra theo đúng một dạng JSON. Xem ApiExceptionRenderer.
        $exceptions->render(
            fn (Throwable $e, Request $request) => app(ApiExceptionRenderer::class)->render($e, $request),
        );
    })->create();
