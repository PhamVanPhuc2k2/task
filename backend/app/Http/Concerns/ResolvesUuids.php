<?php

declare(strict_types=1);

namespace App\Http\Concerns;

use Illuminate\Database\Eloquent\Model;

/**
 * Đổi uuid nhận từ client sang khoá chính nội bộ.
 *
 * API chỉ lộ uuid ra ngoài — id tuần tự cho biết công ty có bao nhiêu bản ghi
 * và cho phép dò tuần tự sang dữ liệu của người khác. Xem README, "Quy ước dữ
 * liệu, thời gian & tiền tệ".
 */
trait ResolvesUuids
{
    /**
     * @param  class-string<Model>  $model
     */
    private function resolveId(string $model, mixed $uuid): ?int
    {
        if (! is_string($uuid) || $uuid === '') {
            return null;
        }

        $id = $model::query()->where('uuid', $uuid)->value('id');

        return $id === null ? null : (int) $id;
    }
}
