<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Settings;

use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Models\User;
use App\Support\Settings\SettingKey;
use App\Support\Settings\SiteSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

/**
 * Tải logo lên, hoặc xoá để quay về dấu cộng vẽ tay.
 *
 * ## Vì sao logo nằm ở ổ CÔNG KHAI, không phải R2
 *
 * Logo hiện trên **trang đăng nhập** — tức là trước khi có ai xác thực. R2 của
 * dự án là bucket riêng tư, mọi đường dẫn đều được ký hạn 30 phút; trang đăng
 * nhập không gọi được API cần xác thực để lấy đường dẫn đó.
 *
 * Nên logo đi ổ `public`, có địa chỉ ổn định. Đây cũng là loại dữ liệu duy nhất
 * trong hệ thống **đáng** để công khai: nó là nhận diện thương hiệu, in trên
 * danh thiếp.
 *
 * ## Không nhận SVG, có chủ ý
 *
 * SVG là XML và chạy được script. Tệp này sẽ được phục vụ từ chính tên miền của
 * ứng dụng, nên một SVG có mã nhúng là lỗ hổng XSS ngay trong trang đăng nhập.
 */
final class SiteLogoController
{
    /** Một logo, một thư mục — không tích tệp cũ lại. */
    private const THU_MUC = 'branding';

    public function store(Request $request, SiteSettings $settings): JsonResponse
    {
        $actor = $this->duocPhep($request);

        $request->validate([
            'logo' => [
                'required',
                'image',
                'mimes:png,jpg,jpeg,webp',
                'max:1024',
                'dimensions:max_width=1024,max_height=1024',
            ],
        ], [
            'logo.mimes' => 'Chỉ nhận PNG, JPG hoặc WebP. Không nhận SVG vì tệp SVG chạy được mã.',
            'logo.max' => 'Ảnh tối đa 1MB — logo không cần lớn hơn thế.',
        ]);

        $cu = $settings->get(SettingKey::LogoPath);

        $duong = $request->file('logo')?->store(self::THU_MUC, 'public');

        abort_unless(is_string($duong), Response::HTTP_INTERNAL_SERVER_ERROR);

        $settings->set(SettingKey::LogoPath, $duong, $actor->id);

        // Xoá tệp cũ SAU khi đã ghi đường dẫn mới: đảo thứ tự thì lưu thất bại
        // sẽ để lại một cài đặt trỏ tới tệp vừa bị xoá.
        if (is_string($cu) && $cu !== '' && $cu !== $duong) {
            Storage::disk('public')->delete($cu);
        }

        return new JsonResponse([
            'data' => ['logo_url' => Storage::disk('public')->url($duong)],
        ]);
    }

    public function destroy(Request $request, SiteSettings $settings): JsonResponse
    {
        $this->duocPhep($request);

        $cu = $settings->get(SettingKey::LogoPath);

        $settings->forget(SettingKey::LogoPath);

        if (is_string($cu) && $cu !== '') {
            Storage::disk('public')->delete($cu);
        }

        return new JsonResponse(['data' => ['logo_url' => null]]);
    }

    private function duocPhep(Request $request): User
    {
        /** @var User $actor */
        $actor = $request->user();

        abort_unless($actor->can(Permission::ManageSettings->value), Response::HTTP_FORBIDDEN);

        return $actor;
    }
}
