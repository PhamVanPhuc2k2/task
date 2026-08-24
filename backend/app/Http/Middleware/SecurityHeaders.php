<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Header bảo mật cho mọi phản hồi của API.
 *
 * Gắn ở tầng middleware group chứ không theo từng route: chỉ cần một route mới
 * quên là hở, và không có gì báo.
 *
 * Đây là API trả JSON, không trả HTML — nên chính sách ở đây khắt khe hơn hẳn
 * một trang web thường: cấm sạch mọi thứ, vì không có gì hợp lệ cần chạy.
 */
final class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        // Trình duyệt không được tự đoán kiểu nội dung. Không có header này,
        // một tệp đính kèm text/plain chứa HTML có thể bị hiểu thành HTML và
        // chạy script trong ngữ cảnh tên miền của mình.
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // API không bao giờ được nhúng trong iframe.
        $response->headers->set('X-Frame-Options', 'DENY');

        // Không rò đường dẫn nội bộ sang trang khác qua Referer.
        $response->headers->set('Referrer-Policy', 'no-referrer');

        // Phản hồi JSON không cần script, style, ảnh hay form. `frame-ancestors`
        // là bản thay thế hiện đại của X-Frame-Options; giữ cả hai vì trình
        // duyệt cũ chỉ hiểu cái sau.
        $response->headers->set(
            'Content-Security-Policy',
            "default-src 'none'; frame-ancestors 'none'; base-uri 'none'; form-action 'none'",
        );

        // Trình duyệt không hỏi quyền thay mặt API.
        $response->headers->set(
            'Permissions-Policy',
            'camera=(), microphone=(), geolocation=(), interest-cohort=()',
        );

        // HSTS chỉ có nghĩa khi đã chạy HTTPS thật. Gắn lúc dev chạy http sẽ
        // khoá trình duyệt vào https://localhost và lập trình viên không vào
        // được nữa — mà xoá cũng rất phiền.
        if ($request->secure()) {
            $response->headers->set(
                'Strict-Transport-Security',
                'max-age=31536000; includeSubDomains',
            );
        }

        return $response;
    }
}
