<?php

declare(strict_types=1);

namespace App\Domain\Task\Events;

use App\Domain\Task\Models\TaskComment;
use Illuminate\Foundation\Events\Dispatchable;

final class CommentPosted
{
    use Dispatchable;

    /**
     * @param  list<int>  $duocNhac  id những người được nhắc tên trong bình luận
     */
    public function __construct(
        public readonly TaskComment $comment,
        public readonly array $duocNhac,
    ) {}
}
