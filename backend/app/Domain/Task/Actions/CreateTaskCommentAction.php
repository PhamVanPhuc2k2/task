<?php

declare(strict_types=1);

namespace App\Domain\Task\Actions;

use App\Domain\Task\Data\CreateCommentData;
use App\Domain\Task\Events\CommentPosted;
use App\Domain\Task\Models\TaskComment;
use Illuminate\Support\Facades\DB;

final class CreateTaskCommentAction
{
    public function __construct(
        private readonly SyncCommentMentionsAction $nhacTen,
    ) {}

    public function execute(CreateCommentData $data): TaskComment
    {
        return DB::transaction(function () use ($data): TaskComment {
            $comment = new TaskComment;

            $comment->fill([
                'task_id' => $data->taskId,
                'parent_id' => $data->parentId,
                'user_id' => $data->authorId,
                'body' => $data->body,
            ]);

            $comment->save();

            $duocNhac = $this->nhacTen->execute($comment);

            // Ai đã tham gia trao đổi thì mặc định theo dõi task, để còn nhận
            // thông báo khi có bình luận tiếp theo hoặc task trễ hạn. Người
            // được nhắc cũng vào danh sách này — xem SyncCommentMentionsAction
            // để biết vì sao nhắc tên đồng nghĩa với chia sẻ quyền xem.
            $comment->task->watchers()->syncWithoutDetaching(
                [$data->authorId, ...$duocNhac],
            );

            CommentPosted::dispatch($comment, $duocNhac);

            return $comment;
        });
    }
}
