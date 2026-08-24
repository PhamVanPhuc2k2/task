<?php

declare(strict_types=1);

namespace App\Domain\Task\Data;

final readonly class CreateCommentData
{
    public function __construct(
        public int $taskId,
        public int $authorId,
        public string $body,
        /** Trả lời một bình luận khác. null là bình luận gốc. */
        public ?int $parentId = null,
    ) {}
}
