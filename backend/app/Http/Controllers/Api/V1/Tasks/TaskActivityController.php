<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Tasks;

use App\Domain\Task\Models\Task;
use App\Http\Resources\TaskActivityResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Attributes\Controllers\Authorize;

/**
 * Dòng thời gian hoạt động của một task.
 *
 * Cùng mức quyền với xem chi tiết task: thấy task thì thấy được lịch sử của nó.
 * Mới nhất lên đầu — người đọc quan tâm "vừa có gì thay đổi", không phải task
 * được tạo ra thế nào từ ba tuần trước.
 */
final class TaskActivityController
{
    #[Authorize('view', 'task')]
    public function index(Request $request, Task $task): AnonymousResourceCollection
    {
        $activities = $task->activities()
            ->with('causer')
            ->latest('id')
            ->paginate(perPage: min((int) $request->integer('per_page', 30), 100));

        return TaskActivityResource::collection($activities);
    }
}
