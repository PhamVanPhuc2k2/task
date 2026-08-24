<?php

declare(strict_types=1);

use App\Domain\Identity\Models\User;
use App\Domain\Task\Models\Task;
use App\Domain\Task\Models\TaskComment;

it('gắn bình luận vào task kèm người viết', function (): void {
    $nguoiViet = User::factory()->create(['name' => 'Người viết']);
    $task = Task::factory()->create();

    $binhLuan = TaskComment::factory()->for($task)->for($nguoiViet, 'author')->create([
        'body' => 'Phần này làm xong rồi anh nhé',
    ]);

    expect($binhLuan->author?->name)->toBe('Người viết')
        ->and($task->comments->pluck('id')->all())->toBe([$binhLuan->id]);
});

it('cho trả lời lồng vào một bình luận khác', function (): void {
    $task = Task::factory()->create();
    $goc = TaskComment::factory()->for($task)->create(['body' => 'Câu hỏi của sếp']);
    $traLoi = TaskComment::factory()->for($task)->for($goc, 'parent')->create(['body' => 'Trả lời']);

    expect($traLoi->parent?->body)->toBe('Câu hỏi của sếp')
        ->and($goc->replies->pluck('body')->all())->toBe(['Trả lời']);
});

it('xoá mềm bình luận để giữ vết cuộc trao đổi', function (): void {
    $binhLuan = TaskComment::factory()->create();
    $binhLuan->delete();

    expect(TaskComment::query()->count())->toBe(0)
        ->and(TaskComment::withTrashed()->count())->toBe(1);
});

it('đánh dấu thời điểm bình luận bị sửa', function (): void {
    $binhLuan = TaskComment::factory()->create();

    expect($binhLuan->edited_at)->toBeNull();

    $binhLuan->update(['body' => 'Sửa lại', 'edited_at' => now()]);

    expect($binhLuan->refresh()->edited_at)->not->toBeNull();
});

it('xoá cứng task thì kéo theo bình luận, không để lại rác', function (): void {
    $task = Task::factory()->create();
    TaskComment::factory()->count(3)->for($task)->create();

    $task->forceDelete();

    expect(TaskComment::withTrashed()->count())->toBe(0);
});
