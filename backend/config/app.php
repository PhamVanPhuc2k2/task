<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Application Name
    |--------------------------------------------------------------------------
    |
    | This value is the name of your application, which will be used when the
    | framework needs to place the application's name in a notification or
    | other UI elements where an application name needs to be displayed.
    |
    */

    'name' => env('APP_NAME', 'Laravel'),

    /*
    |--------------------------------------------------------------------------
    | Application Environment
    |--------------------------------------------------------------------------
    |
    | This value determines the "environment" your application is currently
    | running in. This may determine how you prefer to configure various
    | services the application utilizes. Set this in your ".env" file.
    |
    */

    'env' => env('APP_ENV', 'production'),

    /*
    |--------------------------------------------------------------------------
    | Application Debug Mode
    |--------------------------------------------------------------------------
    |
    | When your application is in debug mode, detailed error messages with
    | stack traces will be shown on every error that occurs within your
    | application. If disabled, a simple generic error page is shown.
    |
    */

    'debug' => (bool) env('APP_DEBUG', false),

    /*
    |--------------------------------------------------------------------------
    | Application URL
    |--------------------------------------------------------------------------
    |
    | This URL is used by the console to properly generate URLs when using
    | the Artisan command line tool. You should set this to the root of
    | the application so that it's available within Artisan commands.
    |
    */

    'url' => env('APP_URL', 'http://localhost'),

    /*
    |--------------------------------------------------------------------------
    | Địa chỉ giao diện
    |--------------------------------------------------------------------------
    |
    | Backend và frontend chạy trên hai địa chỉ khác nhau. `app.url` trỏ tới
    | API, còn đây là nơi người dùng thật sự mở — dùng để dựng đường dẫn trong
    | email thông báo. Lấy nhầm `app.url` thì mọi nút trong email đưa người ta
    | tới một địa chỉ chỉ trả về JSON.
    |
    | Cùng biến với FRONTEND_URL trong config/cors.php.
    |
    */

    'frontend_url' => env('FRONTEND_URL', 'http://localhost:3000'),

    /*
    |--------------------------------------------------------------------------
    | Application Timezone
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default timezone for your application, which
    | will be used by the PHP date and date-time functions. The timezone
    | is set to "UTC" by default as it is suitable for most use cases.
    |
    */

    'timezone' => env('APP_TIMEZONE', 'UTC'),

    /*
    |--------------------------------------------------------------------------
    | Múi giờ hiển thị
    |--------------------------------------------------------------------------
    |
    | Toàn bộ dữ liệu lưu ở UTC. Múi giờ này CHỈ dùng ở tầng trình bày và để
    | suy ra "ngày làm việc" (work_date) khi chấm công.
    |
    | Đừng đổi 'timezone' ở trên sang giờ Việt Nam: nhân viên bấm giờ lúc 00:30
    | sẽ bị tính công sang sai ngày, và mọi phép tính giờ công sẽ lệch khi
    | server đổi múi giờ hoặc khi deploy sang máy khác.
    |
    | Xem README, mục "Quy ước dữ liệu, thời gian & tiền tệ".
    |
    */

    'display_timezone' => env('APP_DISPLAY_TIMEZONE', 'Asia/Ho_Chi_Minh'),

    /*
    |--------------------------------------------------------------------------
    | Application Locale Configuration
    |--------------------------------------------------------------------------
    |
    | The application locale determines the default locale that will be used
    | by Laravel's translation / localization methods. This option can be
    | set to any locale for which you plan to have translation strings.
    |
    */

    /*
     * Mặc định là `vi`, không phải `en`.
     *
     * Toàn bộ người dùng hệ thống này là nhân viên Việt Nam. Để mặc định `en`
     * nghĩa là chỉ cần một môi trường thiếu biến `APP_LOCALE` — một container
     * mới, một lần deploy quên copy `.env` — là cả ứng dụng hiện lỗi bằng tiếng
     * Anh mà không có gì báo. Bản dịch ở `lang/vi`.
     */
    'locale' => env('APP_LOCALE', 'vi'),

    'fallback_locale' => env('APP_FALLBACK_LOCALE', 'en'),

    'faker_locale' => env('APP_FAKER_LOCALE', 'en_US'),

    /*
    |--------------------------------------------------------------------------
    | Encryption Key
    |--------------------------------------------------------------------------
    |
    | This key is utilized by Laravel's encryption services and should be set
    | to a random, 32 character string to ensure that all encrypted values
    | are secure. You should do this prior to deploying the application.
    |
    */

    'cipher' => 'AES-256-CBC',

    'key' => env('APP_KEY'),

    'previous_keys' => [
        ...array_filter(
            explode(',', (string) env('APP_PREVIOUS_KEYS', '')),
        ),
    ],

    /*
    |--------------------------------------------------------------------------
    | Maintenance Mode Driver
    |--------------------------------------------------------------------------
    |
    | These configuration options determine the driver used to determine and
    | manage Laravel's "maintenance mode" status. The "cache" driver will
    | allow maintenance mode to be controlled across multiple machines.
    |
    | Supported drivers: "file", "cache"
    |
    */

    'maintenance' => [
        'driver' => env('APP_MAINTENANCE_DRIVER', 'file'),
        'store' => env('APP_MAINTENANCE_STORE', 'database'),
    ],

];
