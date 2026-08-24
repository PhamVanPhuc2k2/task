<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Tasks;

use App\Domain\Identity\Models\User;
use App\Domain\Task\Actions\CreateTaskCommentAction;
use App\Domain\Task\Actions\SyncCommentMentionsAction;
use App\Domain\Task\Data\CreateCommentData;
use App\Domain\Task\Models\Task;
use App\Domain\Task\Models\TaskComment;
use App\Http\Requests\Task\StoreCommentRequest;
use App\Http\Resources\TaskCommentResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Routing\Attributes\Controllers\Authorize;

/**
 * Bình luận trên một task.
 *
 * Đọc và viết đi theo quyền xem task — xem `TaskCommentPolicy`. Sửa và xoá
 * nằm ở controller riêng vì tham số route là `{comment}` chứ không phải
 * `{task}`, và Laravel không ràng buộc được hai model lồng nhau trong cùng một
 * resource controller mà không sinh ra đường dẫn thừa.
 */
final class TaskCommentController
{
    /**
     * Danh sách bình luận gốc, mới nhất ở cuối.
     *
     * Xếp cũ → mới, ngược với nhật ký hoạt động: người ta đọc một cuộc trao
     * đổi theo thứ tự nó diễn ra, không đọc ngược.
     */
    #[Authorize('view', 'task')]
    public function index(Request $request, Task $task): AnonymousResourceCollection
    {
        $comments = $task->comments()
            ->whereNull('parent_id')
            ->with([
                'author',
                'mentions',
                'media',
                'replies' => fn ($q) => $q->with(['author', 'mentions', 'media'])->oldest('id'),
            ])
            ->withCount('replies')
            ->oldest('id')
            ->paginate(perPage: min((int) $request->integer('per_page', 20), 100));

        return TaskCommentResource::collection($comments);
    }

    #[Authorize('view', 'task')]
    public function store(
        StoreCommentRequest $request,
        Task $task,
        CreateTaskCommentAction $action,
    ): JsonResponse {
        /** @var User $actor */
        $actor = $request->user();

        $comment = $action->execute(new CreateCommentData(
            taskId: $task->id,
            authorId: $actor->id,
            body: (string) $request->string('body'),
            parentId: $this->binhLuanCha($request, $task),
        ));

        return TaskCommentResource::make($comment->load(['author', 'mentions', 'media']))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    #[Authorize('update', 'comment')]
    public function update(
        StoreCommentRequest $request,
        TaskComment $comment,
        SyncCommentMentionsAction $nhacTen,
    ): TaskCommentResource {
        $comment->body = (string) $request->string('body');
        // Đánh dấu đã sửa và hiển thị công khai: một dòng trao đổi sửa được
        // trong im lặng thì không dùng làm bằng chứng được nữa.
        $comment->edited_at = now();
        $comment->save();

        $nhacTen->execute($comment);

        return TaskCommentResource::make($comment->load(['author', 'mentions', 'media']));
    }

    #[Authorize('delete', 'comment')]
    public function destroy(TaskComment $comment): JsonResponse
    {
        // Xoá mềm: giữ vết để còn tra lại khi có tranh chấp.
        $comment->delete();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Bình luận cha phải thuộc đúng task này.
     *
     * Form Request chỉ kiểm uuid có tồn tại; không kiểm thêm ở đây thì trả lời
     * được vào một bình luận của task khác, và câu trả lời sẽ hiện ở nơi người
     * viết không hề nhìn thấy.
     */
    private function binhLuanCha(Request $request, Task $task): ?int
    {
        if (! $request->filled('parent_id')) {
            return null;
        }

        $cha = TaskComment::query()
            ->where('uuid', $request->string('parent_id'))
            ->where('task_id', $task->id)
            ->first();

        abort_if($cha === null, Response::HTTP_UNPROCESSABLE_ENTITY, 'Bình luận được trả lời không thuộc công việc này.');

        // Chỉ một cấp trả lời. Cho lồng sâu tuỳ ý thì màn hình trên điện thoại
        // thụt lề tới mức không đọc nổi, mà cũng không ai cần tới cấp thứ ba.
        return $cha->parent_id ?? $cha->id;
    }
}
