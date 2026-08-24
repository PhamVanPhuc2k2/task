<?php

declare(strict_types=1);

namespace App\Support\Settings;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Một dòng cài đặt. Cố ý mỏng — mọi ý nghĩa nằm ở `SettingKey`.
 *
 * `@property` là bắt buộc theo quy ước dự án. Xem README, "Khối @property trên
 * model": thiếu nó thì Larastan phải suy kiểu từ migration, và khi bộ quét
 * migration hỏng thì mọi chỗ đọc thuộc tính đều báo lỗi.
 *
 * @property int $id
 * @property string $key
 * @property string|null $value
 * @property int|null $updated_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['key', 'value', 'updated_by'])]
final class SiteSetting extends Model
{
    protected $table = 'site_settings';
}
