<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Tasks;

use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Models\User;
use App\Domain\Task\Enums\TaskStatus;
use App\Domain\Task\Models\Task;
use App\Http\Concerns\ResolvesUuids;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

/**
 * Bàn giao toàn bộ việc đang tồn của một người sang người khác.
 *
 * Dùng khi nhân viên nghỉ việc hoặc nghỉ dài ngày. Không có đường này thì task
 * treo lơ lửng và không ai biết ai đang làm.
 *
 * Chỉ chuyển task CHƯA kết thúc. Task đã hoàn thành hoặc đã huỷ giữ nguyên
 * người làm — đó là lịch sử công việc của họ, không phải việc tồn cần bàn giao.
 */
final class BulkReassignController
{
    use ResolvesUuids;

    public function __invoke(Request $request): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        abort_unless($actor->can(Permission::AssignTask->value), Response::HTTP_FORBIDDEN);

        $request->validate([
            'from_user_id' => ['required', 'uuid', Rule::exists('users', 'uuid')],
            'to_user_id' => ['required', 'uuid', 'different:from_user_id', Rule::exists('users', 'uuid')],
        ]);

        $tuNguoi = $this->resolveId(User::class, $request->input('from_user_id'));
        $sangNguoi = $this->resolveId(User::class, $request->input('to_user_id'));

        $soTask = Task::query()
            ->where('assignee_id', $tuNguoi)
            ->whereNotIn('status', [TaskStatus::Done->value, TaskStatus::Cancelled->value])
            ->update([
                'assignee_id' => $sangNguoi,
                'assigner_id' => $actor->id,
                'updated_by' => $actor->id,
            ]);

        return new JsonResponse(['data' => ['reassigned' => $soTask]]);
    }
}
