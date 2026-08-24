<?php

declare(strict_types=1);

namespace App\Support\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Sinh uuid khi tạo bản ghi và dùng uuid làm khoá định tuyến.
 *
 * Khoá chính vẫn là BIGINT tự tăng để index gọn và join nhanh; uuid là thứ duy
 * nhất lộ ra API. ID tuần tự lộ ra ngoài cho biết công ty có bao nhiêu bản ghi
 * và cho phép dò tuần tự sang dữ liệu của người khác.
 *
 * Xem README, "Quy ước dữ liệu, thời gian & tiền tệ".
 */
trait HasUuid
{
    public static function bootHasUuid(): void
    {
        static::creating(static function (Model $model): void {
            if (blank($model->getAttribute('uuid'))) {
                $model->setAttribute('uuid', (string) Str::uuid());
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
