<?php

declare(strict_types=1);

namespace App\Domain\Report\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Một task được nhắc tới trong báo cáo ngày.
 *
 * Model riêng thay vì quan hệ `belongsToMany` trên `DailyReport`: quan hệ
 * nhiều-nhiều của Eloquent cần model đích, mà `Task` thuộc miền Task và
 * `Report` không được tham chiếu tới đó. Ở đây chỉ giữ `task_id` dạng số; tầng
 * Http tra tên task.
 *
 * @property int $id
 * @property int $daily_report_id
 * @property int $task_id
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['daily_report_id', 'task_id'])]
final class DailyReportTask extends Model
{
    /** @return BelongsTo<DailyReport, $this> */
    public function report(): BelongsTo
    {
        return $this->belongsTo(DailyReport::class, 'daily_report_id');
    }
}
