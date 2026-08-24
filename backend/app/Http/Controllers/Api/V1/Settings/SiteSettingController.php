<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Settings;

use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Models\User;
use App\Http\Requests\Settings\UpdateSettingsRequest;
use App\Support\Settings\SettingKey;
use App\Support\Settings\SiteSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Đọc và ghi cài đặt trang.
 *
 * ## Vì sao có màn này
 *
 * Mười hai giá trị chính sách — ca làm, ân hạn đi muộn, giờ nhắc báo cáo, cửa
 * sổ nộp đơn — trước đây chỉ nằm trong `.env`. Đổi bất kỳ cái nào cũng phải sửa
 * file trên máy chủ rồi khởi động lại, tức là **mỗi lần công ty đổi giờ làm là
 * một lần cần lập trình viên**.
 *
 * Đây là thứ giám đốc sở hữu, không phải thứ kỹ thuật.
 *
 * ## Trả kèm mô tả các trường
 *
 * `fields` là nhãn, kiểu và nhóm của từng cài đặt, do **server** khai. Giao diện
 * dựng form từ đó thay vì tự viết lại danh sách — thêm một cài đặt mới thì giao
 * diện tự có, không phải sửa hai chỗ rồi quên một chỗ.
 */
final class SiteSettingController
{
    public function index(Request $request, SiteSettings $settings): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        abort_unless($actor->can(Permission::ManageSettings->value), Response::HTTP_FORBIDDEN);

        return new JsonResponse(['data' => $this->traVe($settings)]);
    }

    public function update(UpdateSettingsRequest $request, SiteSettings $settings): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        abort_unless($actor->can(Permission::ManageSettings->value), Response::HTTP_FORBIDDEN);

        /** @var array<string, string|int|bool|null> $gui */
        $gui = $request->validated()['values'];

        foreach ($gui as $khoa => $gt) {
            // `setRaw` chứ không `set`: khoá đến từ client nên phải đi qua bước
            // kiểm danh mục. FormRequest đã kiểm, đây là lớp thứ hai — cùng lý
            // do mọi ràng buộc quan trọng của dự án đều có hai lớp.
            $settings->setRaw((string) $khoa, $gt, $actor->id);
        }

        return new JsonResponse(['data' => $this->traVe($settings)]);
    }

    /**
     * @return array<string, mixed>
     */
    private function traVe(SiteSettings $settings): array
    {
        return [
            'values' => $settings->all(),
            'fields' => $this->moTaTruong(),
        ];
    }

    /**
     * Nhãn, kiểu và nhóm của từng cài đặt.
     *
     * @return list<array<string, mixed>>
     */
    private function moTaTruong(): array
    {
        return array_map(
            fn (SettingKey $k): array => [
                'key' => $k->value,
                'label' => $k->label(),
                'type' => $k->type()->value,
                'group' => $this->nhom($k),
                'default' => $k->default(),
            ],
            SettingKey::cases(),
        );
    }

    private function nhom(SettingKey $k): string
    {
        return match (true) {
            in_array($k, [
                SettingKey::CompanyName,
                SettingKey::CompanyShortName,
                SettingKey::LogoPath,
            ], true) => 'branding',

            str_starts_with($k->value, 'shift_'),
            $k === SettingKey::MinWorkedMinutes => 'attendance',

            str_starts_with($k->value, 'report_') => 'report',

            default => 'leave',
        };
    }
}
