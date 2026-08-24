<?php

declare(strict_types=1);

use App\Domain\Task\Models\Task;
use App\Domain\Task\Models\TaskComment;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->withHeader('Origin', 'http://localhost:3000');
});

// sepVaNhanVien() và nguoiNgoai() khai báo ở tests/Pest.php.

/*
|--------------------------------------------------------------------------
| Đọc và viết
|--------------------------------------------------------------------------
*/

it('liệt kê bình luận theo thứ tự cũ đến mới', function (): void {
    // Ngược với nhật ký hoạt động: người ta đọc một cuộc trao đổi theo thứ tự
    // nó diễn ra, không đọc ngược.
    [, $nhanVien] = sepVaNhanVien();
    $task = Task::factory()->for($nhanVien, 'assignee')->create();

    foreach (['Câu đầu', 'Câu giữa', 'Câu cuối'] as $noiDung) {
        TaskComment::factory()->for($task)->for($nhanVien, 'author')->create(['body' => $noiDung]);
    }

    expect($this->actingAs($nhanVien)->getJson("/api/v1/tasks/{$task->uuid}/comments")
        ->assertOk()->json('data.*.body'))
        ->toBe(['Câu đầu', 'Câu giữa', 'Câu cuối']);
});

it('viết được bình luận trên task mình thấy', function (): void {
    [, $nhanVien] = sepVaNhanVien();
    $task = Task::factory()->for($nhanVien, 'assignee')->create();

    $this->actingAs($nhanVien)->postJson("/api/v1/tasks/{$task->uuid}/comments", [
        'body' => 'Tôi làm xong phần giao diện rồi.',
    ])->assertCreated()->assertJsonPath('data.author.name', $nhanVien->name);

    expect(TaskComment::query()->where('task_id', $task->id)->count())->toBe(1);
});

it('không đọc được bình luận của task ngoài phạm vi', function (): void {
    // Bình luận lộ nội dung trao đổi nội bộ — cùng mức nhạy cảm với task.
    $task = Task::factory()->create();

    $this->actingAs(nguoiNgoai())
        ->getJson("/api/v1/tasks/{$task->uuid}/comments")
        ->assertStatus(403);
});

it('không viết được bình luận trên task ngoài phạm vi', function (): void {
    $task = Task::factory()->create();

    $this->actingAs(nguoiNgoai())
        ->postJson("/api/v1/tasks/{$task->uuid}/comments", ['body' => 'Chen ngang'])
        ->assertStatus(403);
});

it('từ chối bình luận rỗng', function (): void {
    [, $nhanVien] = sepVaNhanVien();
    $task = Task::factory()->for($nhanVien, 'assignee')->create();

    $this->actingAs($nhanVien)->postJson("/api/v1/tasks/{$task->uuid}/comments", ['body' => ''])
        ->assertStatus(422)
        ->assertJsonValidationErrors('body');
});

/*
|--------------------------------------------------------------------------
| Trả lời
|--------------------------------------------------------------------------
*/

it('trả lời được một bình luận', function (): void {
    [, $nhanVien] = sepVaNhanVien();
    $task = Task::factory()->for($nhanVien, 'assignee')->create();
    $goc = TaskComment::factory()->for($task)->for($nhanVien, 'author')->create();

    $this->actingAs($nhanVien)->postJson("/api/v1/tasks/{$task->uuid}/comments", [
        'body' => 'Đồng ý',
        'parent_id' => $goc->uuid,
    ])->assertCreated();

    expect(TaskComment::query()->where('parent_id', $goc->id)->count())->toBe(1);
});

it('chỉ cho lồng một cấp trả lời', function (): void {
    // Lồng sâu tuỳ ý thì trên điện thoại thụt lề tới mức không đọc nổi, mà
    // cũng không ai cần tới cấp thứ ba.
    [, $nhanVien] = sepVaNhanVien();
    $task = Task::factory()->for($nhanVien, 'assignee')->create();
    $goc = TaskComment::factory()->for($task)->for($nhanVien, 'author')->create();
    $traLoi = TaskComment::factory()->for($task)->for($nhanVien, 'author')
        ->create(['parent_id' => $goc->id]);

    $this->actingAs($nhanVien)->postJson("/api/v1/tasks/{$task->uuid}/comments", [
        'body' => 'Trả lời của trả lời',
        'parent_id' => $traLoi->uuid,
    ])->assertCreated();

    // Bị kéo về làm con của bình luận gốc, không thành cháu.
    expect(TaskComment::query()->where('body', 'Trả lời của trả lời')->firstOrFail()->parent_id)
        ->toBe($goc->id);
});

it('không trả lời được bình luận thuộc task khác', function (): void {
    // Không chặn thì câu trả lời hiện ở nơi người viết không hề nhìn thấy.
    [, $nhanVien] = sepVaNhanVien();
    $task = Task::factory()->for($nhanVien, 'assignee')->create();
    $taskKhac = Task::factory()->for($nhanVien, 'assignee')->create();
    $lacCho = TaskComment::factory()->for($taskKhac)->for($nhanVien, 'author')->create();

    $this->actingAs($nhanVien)->postJson("/api/v1/tasks/{$task->uuid}/comments", [
        'body' => 'Lạc chỗ',
        'parent_id' => $lacCho->uuid,
    ])->assertStatus(422);
});

/*
|--------------------------------------------------------------------------
| Sửa và xoá
|--------------------------------------------------------------------------
*/

it('tác giả sửa được bình luận của mình và bị đánh dấu đã sửa', function (): void {
    [, $nhanVien] = sepVaNhanVien();
    $task = Task::factory()->for($nhanVien, 'assignee')->create();
    $comment = TaskComment::factory()->for($task)->for($nhanVien, 'author')->create();

    $this->actingAs($nhanVien)->patchJson("/api/v1/comments/{$comment->uuid}", [
        'body' => 'Nói lại cho rõ',
    ])->assertOk()->assertJsonPath('data.body', 'Nói lại cho rõ');

    expect($comment->refresh()->edited_at)->not->toBeNull();
});

it('không ai sửa được lời của người khác, kể cả cấp trên', function (): void {
    // Sửa được lời người khác thì cả dòng trao đổi mất giá trị làm bằng chứng
    // — trong hệ thống có thưởng phạt theo tiến độ, đó là chuyện lớn.
    [$sep, $nhanVien] = sepVaNhanVien();
    $task = Task::factory()->for($nhanVien, 'assignee')->create();
    $comment = TaskComment::factory()->for($task)->for($nhanVien, 'author')->create();

    $this->actingAs($sep)->patchJson("/api/v1/comments/{$comment->uuid}", ['body' => 'Sửa trộm'])
        ->assertStatus(403);
});

it('tác giả xoá được bình luận của mình, và xoá là xoá mềm', function (): void {
    [, $nhanVien] = sepVaNhanVien();
    $task = Task::factory()->for($nhanVien, 'assignee')->create();
    $comment = TaskComment::factory()->for($task)->for($nhanVien, 'author')->create();

    $this->actingAs($nhanVien)->deleteJson("/api/v1/comments/{$comment->uuid}")->assertNoContent();

    expect(TaskComment::withTrashed()->whereKey($comment->id)->first()?->trashed())->toBeTrue();
});

it('người có quyền xoá task cũng gỡ được bình luận không phù hợp', function (): void {
    [$sep, $nhanVien] = sepVaNhanVien();
    $task = Task::factory()->for($nhanVien, 'assignee')->create();
    $comment = TaskComment::factory()->for($task)->for($nhanVien, 'author')->create();

    $this->actingAs($sep)->deleteJson("/api/v1/comments/{$comment->uuid}")->assertNoContent();
});

/*
|--------------------------------------------------------------------------
| Tự theo dõi
|--------------------------------------------------------------------------
*/

it('người viết bình luận tự vào danh sách theo dõi task', function (): void {
    // Tham gia trao đổi rồi thì phải nhận được thông báo khi có câu tiếp theo.
    [$sep, $nhanVien] = sepVaNhanVien();
    $task = Task::factory()->for($nhanVien, 'assignee')->create();

    expect($task->watchers()->where('users.id', $sep->id)->exists())->toBeFalse();

    $this->actingAs($sep)->postJson("/api/v1/tasks/{$task->uuid}/comments", [
        'body' => 'Nhớ gửi tôi bản nháp trước thứ Sáu.',
    ])->assertCreated();

    expect($task->watchers()->where('users.id', $sep->id)->exists())->toBeTrue();
});
