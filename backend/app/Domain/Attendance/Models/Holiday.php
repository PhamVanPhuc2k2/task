<?php

declare(strict_types=1);

namespace App\Domain\Attendance\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Ngày nghỉ lễ.
 *
 * `date` là ngày lễ trên giấy, `observed_date` là ngày thực nghỉ. Hai cột khác
 * nhau vì Điều 112 Bộ luật Lao động 2019 cho nghỉ bù khi ngày lễ trùng ngày
 * nghỉ hằng tuần — bảng công phải đếm theo ngày thực nghỉ.
 *
 * Không hardcode trong mã: Tết âm lịch trôi theo năm dương lịch.
 *
 * @property int $id
 * @property string $date
 * @property string $observed_date
 * @property string $name
 * @property bool $is_paid
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['date', 'observed_date', 'name', 'is_paid'])]
final class Holiday extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'string',
            'observed_date' => 'string',
            'is_paid' => 'boolean',
        ];
    }
}
