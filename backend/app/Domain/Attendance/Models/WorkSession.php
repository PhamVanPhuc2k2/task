<?php

declare(strict_types=1);

namespace App\Domain\Attendance\Models;

use App\Domain\Identity\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Một quãng làm việc liên tục, suy ra từ tương tác thật với ứng dụng.
 *
 * Không phải "tab đang mở". Nhịp tim chỉ được gửi khi có tương tác thật trong
 * phút vừa rồi và tab đang hiển thị — treo máy đi ăn trưa thì phiên tự đóng.
 *
 * `@property` là bắt buộc theo quy ước dự án: thiếu thì Larastan suy kiểu từ
 * migration và hiểu sai mọi cột có cast (xem README mục 1.4).
 *
 * @property int $id
 * @property int $user_id
 * @property CarbonImmutable $started_at
 * @property CarbonImmutable $ended_at
 * @property string $work_date
 * @property string $source
 * @property bool $interactive Phút đó có bấm/gõ/cuộn, hay chỉ để tab mở.
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['user_id', 'started_at', 'ended_at', 'work_date', 'source', 'interactive'])]
final class WorkSession extends Model
{
    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Số phút của phiên.
     *
     * Làm tròn xuống: một phiên 59 giây không phải là một phút làm việc, và
     * làm tròn lên thì mỗi ngày cộng thêm vài phút không có thật.
     */
    public function minutes(): int
    {
        return (int) floor($this->started_at->diffInSeconds($this->ended_at) / 60);
    }

    /**
     * Phiên trong một khoảng ngày công.
     *
     * Lọc theo cột `work_date` chứ không theo `started_at`: xem
     * App\Support\Time\WorkDate để biết vì sao hai thứ đó khác nhau.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeBetweenWorkDates(Builder $query, string $tu, string $den): void
    {
        $query->whereBetween('work_date', [$tu, $den]);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            // KHÔNG cast sang date: đây là nhãn ngày công dạng chuỗi, không
            // phải một mốc thời gian. Cast thành Carbon sẽ gắn thêm giờ 00:00
            // theo múi giờ ứng dụng và mở lại đúng cái bẫy mà cột này sinh ra
            // để chặn.
            'work_date' => 'string',
            'interactive' => 'boolean',
        ];
    }
}
