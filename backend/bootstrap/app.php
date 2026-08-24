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
        /*
        | Tin proxy nội bộ, và CHỈ proxy nội bộ.
        |
        | Trên production một request đi qua ba chặng trước khi tới PHP:
        |
        |   Trình duyệt → Cloudflare → nginx máy chủ → nginx Docker → php-fpm
        |
        | Không khai ở đây thì `$request->ip()` trả về địa chỉ của **chặng cuối
        | cùng** — tức là mọi người dùng trên đời đều mang chung một IP. Mà
        | `$request->ip()` đang đỡ bốn giới hạn tần suất và một nhật ký bảo mật:
        |
        |   - Quên mật khẩu: 10 lần/IP/15 phút → thành 10 lần cho CẢ CÔNG TY
        |   - Nhập mã 2FA, gửi lại mã 2FA, đăng nhập
        |   - `login_attempts.ip_address`: ghi IP của proxy, vô dụng khi truy vết
        |
        | Hệ thống tự chặn chính mình, và nhật ký mất giá trị — cả hai đều im
        | lặng, không có gì báo lỗi.
        |
        | ## Vì sao chỉ dải nội bộ, không dùng `at: '*'`
        |
        | Tin mọi proxy thì bất kỳ ai cũng tự khai IP của mình bằng cách gửi kèm
        | `X-Forwarded-For` — và mọi giới hạn theo IP thành vô dụng, vì kẻ dò
        | mật khẩu chỉ cần đổi con số trong header sau mỗi lần thử.
        |
        | Dải nội bộ là an toàn vì php-fpm không mở ra internet: muốn gửi được
        | request tới nó thì đã phải ở trong mạng Docker rồi.
        |
        | ## Chuỗi phải đúng ở CẢ BA chặng
        |
        | Dòng này chỉ lo chặng cuối. nginx máy chủ phải lấy IP thật từ header
        | `CF-Connecting-IP` của Cloudflare và truyền xuống, nếu không thứ tới
        | đây vẫn là IP của Cloudflare.
        */
        $middleware->trustProxies(
            at: [
                '10.0.0.0/8',
                '172.16.0.0/12',
                '192.168.0.0/16',
                '127.0.0.1',
                '::1',
            ],
            headers: Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_HOST
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PROTO,
        );

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
