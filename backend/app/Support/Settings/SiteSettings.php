<?php

declare(strict_types=1);

namespace App\Support\Settings;

use App\Support\Exceptions\UnknownSettingException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;

/**
 * Đọc và ghi cài đặt trang.
 *
 * ## Ghi đè config, không sửa mã miền
 *
 * Điểm quyết định của cả tính năng. `apDungVaoConfig()` được gọi một lần lúc
 * khởi động, nạp cài đặt từ database vào `Config` — nên `WorkShift::fromConfig()`
 * và mọi chỗ đọc config khác **không phải sửa một dòng nào**, và toàn bộ test
 * đã có vẫn đúng.
 *
 * Cách khác là để mỗi chỗ tự hỏi database. Cách đó buộc phải sửa mọi lớp đang
 * đọc config, và mỗi lớp lại thêm một truy vấn.
 *
 * ## Cache, và vì sao phải xoá đúng lúc
 *
 * Bảng này được đọc **mỗi request** nhưng gần như không bao giờ đổi, nên nó
 * nằm trong cache. Đổi cài đặt mà quên xoá cache thì giám đốc bấm lưu, thấy báo
 * thành công, và hệ thống vẫn chạy theo giá trị cũ cho tới khi cache hết hạn —
 * hỏng im lặng đúng kiểu tệ nhất. Nên `set()` và `forget()` tự xoá.
 */
final class SiteSettings
{
    private const CACHE_KEY = 'site_settings';

    private const CACHE_GIAY = 3600;

    /** @var array<string, string|null>|null Nhớ trong một request. */
    private ?array $daNap = null;

    public function get(SettingKey $khoa): string|int|bool|null
    {
        $tho = $this->tatCa()[$khoa->value] ?? null;

        if ($tho === null) {
            return $khoa->default();
        }

        return $khoa->type()->ep($tho);
    }

    public function set(SettingKey $khoa, string|int|bool|null $gia, ?int $nguoiSua = null): void
    {
        SiteSetting::query()->updateOrCreate(
            ['key' => $khoa->value],
            ['value' => $khoa->type()->raw($gia), 'updated_by' => $nguoiSua],
        );

        $this->xoaCache();
    }

    /**
     * Ghi theo khoá dạng chuỗi — dùng ở tầng HTTP, nơi khoá đến từ client.
     *
     * Tách khỏi `set()` để chỗ gọi buộc phải đi qua bước kiểm khoá. Không có
     * bước đó thì một khoá gõ sai vẫn ghi được vào database và không ảnh hưởng
     * gì tới hệ thống, im lặng.
     */
    public function setRaw(string $khoa, string|int|bool|null $gia, ?int $nguoiSua = null): void
    {
        $enum = SettingKey::tryFrom($khoa);

        if ($enum === null) {
            throw new UnknownSettingException($khoa);
        }

        $this->set($enum, $gia, $nguoiSua);
    }

    /** Xoá dòng = quay về mặc định trong config, không phải đặt về null. */
    public function forget(SettingKey $khoa): void
    {
        SiteSetting::query()->where('key', $khoa->value)->delete();

        $this->xoaCache();
    }

    /**
     * Nạp cài đặt vào `Config`.
     *
     * Gọi một lần lúc khởi động — xem SettingsServiceProvider. Chỉ ghi những
     * khoá **đã được đặt**: khoá chưa đặt thì để config giữ nguyên mặc định của
     * chính nó, đúng theo nguyên tắc "dòng vắng mặt nghĩa là dùng mặc định".
     */
    public function apDungVaoConfig(): void
    {
        foreach (SettingKey::cases() as $khoa) {
            $duong = $khoa->configPath();

            if ($duong === null) {
                continue;
            }

            $tho = $this->tatCa()[$khoa->value] ?? null;

            if ($tho === null) {
                continue;
            }

            Config::set($duong, $khoa->type()->ep($tho));
        }
    }

    /**
     * Mọi cài đặt, đã ép kiểu — dùng cho API và giao diện.
     *
     * @return array<string, string|int|bool|null>
     */
    public function all(): array
    {
        $ra = [];

        foreach (SettingKey::cases() as $khoa) {
            $ra[$khoa->value] = $this->get($khoa);
        }

        return $ra;
    }

    /** @return array<string, string|null> */
    private function tatCa(): array
    {
        if ($this->daNap !== null) {
            return $this->daNap;
        }

        /** @var array<string, string|null> $duLieu */
        $duLieu = Cache::remember(
            self::CACHE_KEY,
            self::CACHE_GIAY,
            static fn (): array => SiteSetting::query()
                ->pluck('value', 'key')
                ->all(),
        );

        return $this->daNap = $duLieu;
    }

    private function xoaCache(): void
    {
        Cache::forget(self::CACHE_KEY);
        $this->daNap = null;
    }
}
