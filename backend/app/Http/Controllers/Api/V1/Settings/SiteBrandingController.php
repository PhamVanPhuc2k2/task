<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Settings;

use App\Support\Settings\SettingKey;
use App\Support\Settings\SiteSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

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
 */
final class SiteBrandingController
{
    public function __invoke(SiteSettings $settings): JsonResponse
    {
        $duong = $settings->get(SettingKey::LogoPath);

        return new JsonResponse([
            'data' => [
                'company_name' => $settings->get(SettingKey::CompanyName),
                'company_short_name' => $settings->get(SettingKey::CompanyShortName),
                // `null` khi chưa đặt logo — giao diện tự quay về dấu cộng vẽ
                // tay. Không trả chuỗi rỗng: chuỗi rỗng làm `<img src="">` và
                // trình duyệt tải lại chính trang hiện tại.
                'logo_url' => is_string($duong) && $duong !== ''
                    ? Storage::disk('public')->url($duong)
                    : null,
            ],
        ]);
    }
}
