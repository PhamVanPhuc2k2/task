<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Settings;

use App\Support\Settings\BrandingAssetStore;
use App\Support\Settings\SettingKey;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;

/**
 * Biểu tượng của trang — đường **công khai**, luôn trả về một ảnh.
 *
 * ## Vì sao cần đường này, thay vì để frontend tự hỏi rồi tự chèn
 *
 * `NEXT_PUBLIC_API_URL` ở production là đường dẫn **tương đối** (`/api/v1`), và
 * đó là chủ ý: ảnh Docker build một lần phải chạy được ở mọi tên miền. Nhưng
 * `generateMetadata()` của Next chạy trên **máy chủ**, nơi một đường dẫn tương
 * đối không có origin để phân giải — nên nó không gọi được API này.
 *
 * Cách vá sẽ là thêm một biến môi trường chỉ dành cho server trỏ vào backend.
 * Thêm biến đó là thêm một thứ phải khai đúng lúc deploy, mà khai sai thì
 * favicon **âm thầm** rơi về mặc định — không lỗi, không log, chỉ là sai.
 *
 * Đường này gỡ bỏ cả hai: frontend khai một URL tĩnh, trình duyệt tự phân giải
 * theo origin đang mở, và backend quyết định trả ảnh nào.
 *
 * ## Luôn chuyển hướng, không bao giờ trả 404
 *
 * Chưa ai đặt biểu tượng thì trỏ về dấu cộng Explus của frontend. Một favicon
 * trả 404 làm trình duyệt hiện biểu tượng trang trắng và **nhớ điều đó rất
 * lâu** — tệ hơn hẳn việc trả về ảnh mặc định.
 */
final class SiteFaviconController
{
    /**
     * Năm phút.
     *
     * Trình duyệt cache favicon rất dai, nên con số này là thứ quyết định giám
     * đốc đổi biểu tượng xong bao lâu thì thấy. Để quá dài thì họ tưởng chức
     * năng hỏng; để quá ngắn thì mỗi lần mở tab là một lượt gọi cho một ảnh gần
     * như không bao giờ đổi.
     */
    private const CACHE_GIAY = 300;

    public function __invoke(BrandingAssetStore $kho): RedirectResponse
    {
        $url = $kho->url(SettingKey::IconPath) ?? $this->macDinh();

        return redirect()
            ->away($url, Response::HTTP_FOUND)
            ->header('Cache-Control', 'public, max-age='.self::CACHE_GIAY);
    }

    /**
     * Dấu cộng Explus, lấy từ frontend.
     *
     * Cố ý KHÔNG giữ một bản sao của tệp này ở backend: hai bản sao của cùng một
     * dấu nhận diện là hai bản sẽ lệch nhau sau lần đổi thương hiệu đầu tiên.
     * `public/icon.svg` của frontend là bản duy nhất, và manifest PWA cũng trỏ
     * về đây.
     */
    private function macDinh(): string
    {
        return rtrim((string) config('app.frontend_url'), '/').'/icon.svg';
    }
}
