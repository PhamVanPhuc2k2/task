<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Settings;

use App\Support\Settings\BrandingAssetStore;
use App\Support\Settings\SettingKey;
use App\Support\Settings\SiteSettings;
use Illuminate\Http\JsonResponse;

/**
 * Nhận diện công ty — đường **công khai**, không cần đăng nhập.
 *
 * Trang đăng nhập cần tên và logo, mà lúc đó chưa có phiên nào. Nên phải có một
 * đường riêng cho việc đó.
 *
 * ## Chỉ trả nhận diện, KHÔNG trả chính sách
 *
 * Đây là ranh giới quan trọng nhất của lớp này. Trả cả `values` như màn quản
 * trị là phơi giờ làm, cửa sổ nộp đơn và cấu hình nội bộ cho bất kỳ ai gọi tới
 * — không phải rò rỉ nghiêm trọng, nhưng là thứ không có lý do gì để công khai.
 *
 * Có test khoá riêng cho đúng điều này.
 *
 * ## `icon_url` ở đây chỉ để màn cài đặt xem trước
 *
 * Tab trình duyệt KHÔNG đọc giá trị này — nó trỏ thẳng vào `/site/icon`, vì
 * `generateMetadata()` của Next chạy trên máy chủ và không phân giải được
 * đường dẫn API tương đối. Xem `SiteFaviconController`.
 */
final class SiteBrandingController
{
    public function __invoke(SiteSettings $settings, BrandingAssetStore $kho): JsonResponse
    {
        return new JsonResponse([
            'data' => [
                'company_name' => $settings->get(SettingKey::CompanyName),
                'company_short_name' => $settings->get(SettingKey::CompanyShortName),
                // `null` khi chưa đặt — giao diện tự quay về dấu cộng vẽ tay.
                // Không trả chuỗi rỗng: chuỗi rỗng làm `<img src="">` và trình
                // duyệt tải lại chính trang hiện tại.
                'logo_url' => $kho->url(SettingKey::LogoPath),
                'icon_url' => $kho->url(SettingKey::IconPath),
            ],
        ]);
    }
}
