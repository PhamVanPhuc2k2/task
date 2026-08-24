<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Chia sẻ tài nguyên giữa các nguồn (CORS)
    |--------------------------------------------------------------------------
    |
    | Frontend Next.js chạy ở cổng khác backend, nên mọi lời gọi API đều là
    | cross-origin và phải qua CORS.
    |
    | Hai điều bắt buộc phải đúng, nếu sai thì trình duyệt CHẶN phản hồi trong
    | khi curl vẫn chạy bình thường — loại lỗi chỉ lộ ra khi mở trình duyệt:
    |
    |   1. `supports_credentials` phải là true. Xác thực bằng cookie phiên
    |      dùng `credentials: "include"`; thiếu header này là trình duyệt bỏ
    |      phản hồi, dù server đã trả 200.
    |
    |   2. `allowed_origins` phải liệt kê đúng địa chỉ, KHÔNG được để `*`.
    |      Chuẩn CORS cấm dùng `*` cùng lúc với credentials.
    |
    | Khi deploy lên domain thật, nhớ đổi FRONTEND_URL — cùng chỗ với
    | SANCTUM_STATEFUL_DOMAINS và SESSION_DOMAIN.
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_filter([
        env('FRONTEND_URL', 'http://localhost:3000'),
    ]),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,

];
