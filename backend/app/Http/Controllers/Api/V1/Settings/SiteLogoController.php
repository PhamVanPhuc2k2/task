<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Settings;

use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Models\User;
use App\Support\Settings\BrandingAssetStore;
use App\Support\Settings\SettingKey;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;

/**
 * Tải logo lên, hoặc xoá để quay về dấu cộng vẽ tay.
 *
 * Logo là ảnh **ngang**, hiện ở đầu trang và trên trang đăng nhập. Biểu tượng
 * trên tab trình duyệt là thứ khác, đi qua `SiteIconController` — xem lý do
 * tách ở đó.
 *
 * Vòng đời tệp nằm ở `BrandingAssetStore`; lớp này chỉ lo quyền và kiểm dữ liệu.
 *
 * ## Không nhận SVG, có chủ ý
 *
 * SVG là XML và chạy được script. Tệp này sẽ được phục vụ từ chính tên miền của
 * ứng dụng, nên một SVG có mã nhúng là lỗ hổng XSS ngay trong trang đăng nhập.
 */
final class SiteLogoController
{
    public function store(Request $request, BrandingAssetStore $kho): JsonResponse
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

        /** @var UploadedFile $tep */
        $tep = $request->file('logo');

        return new JsonResponse([
            'data' => ['logo_url' => $kho->luu($tep, SettingKey::LogoPath, $actor->id)],
        ]);
    }

    public function destroy(Request $request, BrandingAssetStore $kho): JsonResponse
    {
        $this->duocPhep($request);

        $kho->xoa(SettingKey::LogoPath);

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
