<?php

declare(strict_types=1);

namespace App\Domain\Task\Events;

use App\Domain\Task\Models\Task;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Một task vừa được giao cho ai đó.
 *
 * Dùng sự kiện chứ không gọi thẳng Notification trong Action: Action lo phần
 * nghiệp vụ "ai làm việc này", còn "báo cho ai" là chuyện khác và sẽ còn thêm
 * người nghe — đợt 2 phải đẩy sang Zalo, đợt 5 phải cộng vào chỉ số KPI. Mỗi
 * lần thêm một việc phụ mà lại sửa vào Action là dấu hiệu đặt sai chỗ.
 */
final class TaskAssigned
{
    use Dispatchable;

    public function __construct(
        public readonly Task $task,
        /** Người bấm nút giao việc. */
        public readonly string $nguoiGiao,
        /** Tránh báo cho chính người vừa tự nhận việc của mình. */
        public readonly int $nguoiGiaoId,
    ) {}
}
