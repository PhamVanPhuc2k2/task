<?php

declare(strict_types=1);

namespace App\Providers;

use App\Support\Settings\SiteSettings;
use Illuminate\Support\ServiceProvider;
use Throwable;

/**
 * Nạp cài đặt trang vào `Config` lúc khởi động.
 *
 * ## Vì sao bọc trong try/catch
 *
 * Provider này chạy **trước khi** ứng dụng biết mình đang làm gì — kể cả khi
 * đang chạy `migrate` trên một database còn trống, hoặc `php artisan` trên máy
 * chưa có database. Không bọc thì bảng chưa tồn tại sẽ làm **mọi lệnh artisan
 * chết**, gồm cả chính lệnh migrate sinh ra bảng đó.
 *
 * Bỏ qua lỗi ở đây là an toàn: mất cài đặt thì hệ thống chạy theo mặc định của
 * config, đúng như trước khi có tính năng này.
 *
 * ## Vì sao nằm ở App\Providers, không nằm cạnh SiteSettings
 *
 * Luật kiến trúc của dự án (preset Laravel của Pest) đòi ServiceProvider nằm ở
 * `App\Providers`. Ban đầu tôi đặt nó cạnh `SiteSettings` trong `App\Support`
 * cho gần, và test kiến trúc bắt ngay.
 *
 * Luật đúng, và cách chia cũng rõ hơn: `App\Support\Settings` giữ **cơ chế**,
 * `App\Providers` giữ **dây nối**. Thêm một dòng vào danh sách miễn trừ của
 * luật đó sẽ dễ hơn nhưng không có lý do kiến trúc nào biện minh được.
 */
final class SettingsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SiteSettings::class);
    }

    public function boot(): void
    {
        try {
            $this->app->make(SiteSettings::class)->apDungVaoConfig();
        } catch (Throwable) {
            // Database chưa sẵn sàng. Dùng mặc định của config.
        }
    }
}
