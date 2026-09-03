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
 * Tải biểu tượng lên — ảnh hiện trên tab trình duyệt và màn hình chính điện thoại.
 *
 * ## Vì sao KHÔNG dùng thẳng logo
 *
 * Logo của công ty thường nằm ngang: biểu tượng cộng với tên viết bằng chữ. Ảnh
 * đó co xuống 16×16 pixel trên tab trình duyệt thì thành một vệt mờ không đọc
 * được — chữ biến mất trước tiên. Chính vì vậy `logo` chỉ ràng buộc cạnh tối đa
 * mà không ép tỉ lệ, và giao diện phải dùng `object-contain` để không cắt hai
 * đầu của nó.
 *
 * Biểu tượng là bài toán khác: **vuông, một hình duy nhất, đọc được ở 16px**.
 * Ép hai yêu cầu đó vào một tệp là hỏng cả hai. Nên đây là ô tải riêng, và bỏ
 * trống thì quay về dấu cộng Explus.
 *
 * ## Chỉ nhận PNG và WebP
 *
 * Không nhận JPG vì JPG không có nền trong suốt: biểu tượng sẽ là một ô vuông
 * trắng trên thanh tab nền tối. Không nhận SVG vì lý do như logo — SVG chạy
 * được mã, mà tệp này phục vụ từ chính tên miền của ứng dụng.
 */
final class SiteIconController
{
    public function store(Request $request, BrandingAssetStore $kho): JsonResponse
    {
        $actor = $this->duocPhep($request);

        $request->validate([
            'icon' => [
                'required',
                'image',
                'mimes:png,webp',
                'max:512',
                // `ratio=1/1` là ràng buộc quan trọng nhất ở đây: trình duyệt
                // không cắt ảnh, nó bóp — một ảnh ngang thành biểu tượng méo.
                'dimensions:min_width=64,min_height=64,max_width=512,max_height=512,ratio=1/1',
            ],
        ], [
            'icon.mimes' => 'Chỉ nhận PNG hoặc WebP. JPG không có nền trong suốt nên sẽ thành ô vuông trắng trên tab nền tối.',
            'icon.max' => 'Ảnh tối đa 512KB — biểu tượng hiển thị ở 16–512px, không cần lớn hơn.',
            'icon.dimensions' => 'Biểu tượng phải vuông (tỉ lệ 1:1), cạnh từ 64px đến 512px.',
        ]);

        /** @var UploadedFile $tep */
        $tep = $request->file('icon');

        return new JsonResponse([
            'data' => ['icon_url' => $kho->luu($tep, SettingKey::IconPath, $actor->id)],
        ]);
    }

    public function destroy(Request $request, BrandingAssetStore $kho): JsonResponse
    {
        $this->duocPhep($request);

        $kho->xoa(SettingKey::IconPath);

        return new JsonResponse(['data' => ['icon_url' => null]]);
    }

    private function duocPhep(Request $request): User
    {
        /** @var User $actor */
        $actor = $request->user();

        abort_unless($actor->can(Permission::ManageSettings->value), Response::HTTP_FORBIDDEN);

        return $actor;
    }
}
