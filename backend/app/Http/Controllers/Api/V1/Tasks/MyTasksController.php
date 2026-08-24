<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Tasks;

use App\Domain\Identity\Models\User;
use App\Domain\Task\Enums\TaskStatus;
use App\Domain\Task\Models\Task;
use App\Http\Resources\TaskResource;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * "Việc của tôi" — màn hình mặc định mỗi sáng.
 *
 * Gom theo hạn chứ không theo trạng thái: điều nhân viên cần biết ngay khi mở
 * máy là *việc nào đã trễ* và *việc nào phải xong hôm nay*, không phải có bao
 * nhiêu việc đang ở trạng thái nào.
 *
 * Chỉ lấy task mình LÀM, không lấy task mình giao cho người khác — đó là màn
 * hình khác (`/tasks/team`).
 */
final class MyTasksController
{
    public function __invoke(Request $request): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        $tasks = Task::query()
            ->where('assignee_id', $actor->id)
            ->whereNotIn('status', [TaskStatus::Done->value, TaskStatus::Cancelled->value])
            ->with(['project', 'assigner', 'labels'])
            ->orderByRaw('due_date IS NULL, due_date')
            ->get();

        $cuoiHomNay = now()->endOfDay();
        $cuoiTuan = now()->endOfWeek();

        return new JsonResponse([
            'data' => [
                'overdue' => $this->dong($tasks->filter(
                    fn (Task $t): bool => $t->due_date !== null && $t->due_date->isPast(),
                )),
                'today' => $this->dong($tasks->filter(
                    fn (Task $t): bool => $t->due_date !== null
                        && ! $t->due_date->isPast()
                        && $t->due_date->lessThanOrEqualTo($cuoiHomNay),
                )),
                'this_week' => $this->dong($tasks->filter(
                    fn (Task $t): bool => $t->due_date !== null
                        && $t->due_date->greaterThan($cuoiHomNay)
                        && $t->due_date->lessThanOrEqualTo($cuoiTuan),
                )),
                // Việc chưa có hạn xếp chung với việc xa — chúng không gấp.
                'later' => $this->dong($tasks->filter(
                    fn (Task $t): bool => $t->due_date === null
                        || $t->due_date->greaterThan($cuoiTuan),
                )),
            ],
        ]);
    }

    /**
     * @param  Collection<int, Task>  $tasks
     * @return array<int, mixed>
     */
    private function dong(Collection $tasks): array
    {
        return TaskResource::collection($tasks->values())->resolve();
    }
}
