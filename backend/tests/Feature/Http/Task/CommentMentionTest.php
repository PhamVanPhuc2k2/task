<?php

declare(strict_types=1);

use App\Domain\Identity\Models\User;
use App\Domain\Task\Models\Task;
use App\Domain\Task\Models\TaskComment;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->withHeader('Origin', 'http://localhost:3000');
});

/** Dấu nhắc do ô soạn thảo chèn khi người dùng chọn trong danh sách gợi ý. */
function nhac(User $user): string
{
    return "@[{$user->name}]({$user->uuid})";
}

it('ghi lại người được nhắc tên trong bình luận', function (): void {
    [$sep, $nhanVien] = sepVaNhanVien();
    $task = Task::factory()->for($nhanVien, 'assignee')->create();

    $this->actingAs($nhanVien)->postJson("/api/v1/tasks/{$task->uuid}/comments", [
        'body' => 'Nhờ '.nhac($sep).' duyệt giúp phần này.',
    ])->assertCreated();

    $comment = TaskComment::query()->latest('id')->firstOrFail();

    expect($comment->mentions->pluck('id')->all())->toBe([$sep->id]);
});

it('nhắc tên đồng nghĩa với chia sẻ: người được nhắc thấy được task', function (): void {
    // Chủ ý, không phải sơ suất. Kéo đồng nghiệp vào một cuộc trao đổi mà họ
    // không mở được task thì tính năng vô nghĩa. Đổi lại, mọi lần nhắc đều lưu
    // vết nên luôn tra được ai đã kéo ai vào.
    [, $nhanVien] = sepVaNhanVien();
    $nguoiPhongKhac = nguoiNgoai();
    $task = Task::factory()->for($nhanVien, 'assignee')->create();

    $this->actingAs($nguoiPhongKhac)->getJson("/api/v1/tasks/{$task->uuid}")->assertStatus(403);

    $this->actingAs($nhanVien)->postJson("/api/v1/tasks/{$task->uuid}/comments", [
        'body' => 'Nhờ '.nhac($nguoiPhongKhac).' xem giúp.',
    ])->assertCreated();

    $this->actingAs($nguoiPhongKhac)->getJson("/api/v1/tasks/{$task->uuid}")->assertOk();
});

it('không nhắc chính mình', function (): void {
    [, $nhanVien] = sepVaNhanVien();
    $task = Task::factory()->for($nhanVien, 'assignee')->create();

    $this->actingAs($nhanVien)->postJson("/api/v1/tasks/{$task->uuid}/comments", [
        'body' => 'Ghi chú cho '.nhac($nhanVien),
    ])->assertCreated();

    expect(TaskComment::query()->latest('id')->firstOrFail()->mentions)->toBeEmpty();
});

it('không nhắc tài khoản đã vô hiệu hoá', function (): void {
    // Nhắc người đã nghỉ việc chỉ tạo ra một thông báo không ai đọc.
    [, $nhanVien] = sepVaNhanVien();
    $daNghi = User::factory()->create(['is_active' => false]);
    $task = Task::factory()->for($nhanVien, 'assignee')->create();

    $this->actingAs($nhanVien)->postJson("/api/v1/tasks/{$task->uuid}/comments", [
        'body' => 'Nhờ '.nhac($daNghi).' bàn giao lại.',
    ])->assertCreated();

    expect(TaskComment::query()->latest('id')->firstOrFail()->mentions)->toBeEmpty();
});

it('nhắc cùng một người hai lần chỉ tính một', function (): void {
    // Nếu không, mục 1.6 sẽ gửi hai thông báo giống hệt nhau.
    [$sep, $nhanVien] = sepVaNhanVien();
    $task = Task::factory()->for($nhanVien, 'assignee')->create();

    $this->actingAs($nhanVien)->postJson("/api/v1/tasks/{$task->uuid}/comments", [
        'body' => nhac($sep).' và '.nhac($sep).' cùng xem nhé.',
    ])->assertCreated();

    expect(TaskComment::query()->latest('id')->firstOrFail()->mentions)->toHaveCount(1);
});

it('bỏ qua chuỗi trông giống dấu nhắc nhưng uuid không có thật', function (): void {
    [, $nhanVien] = sepVaNhanVien();
    $task = Task::factory()->for($nhanVien, 'assignee')->create();

    $this->actingAs($nhanVien)->postJson("/api/v1/tasks/{$task->uuid}/comments", [
        'body' => 'Nhờ @[Ai Đó](00000000-0000-0000-0000-000000000000) xem giúp.',
    ])->assertCreated();

    expect(TaskComment::query()->latest('id')->firstOrFail()->mentions)->toBeEmpty();
});

it('sửa bình luận thì cập nhật lại danh sách người được nhắc', function (): void {
    [$sep, $nhanVien] = sepVaNhanVien();
    $task = Task::factory()->for($nhanVien, 'assignee')->create();

    $tao = $this->actingAs($nhanVien)->postJson("/api/v1/tasks/{$task->uuid}/comments", [
        'body' => 'Nhờ '.nhac($sep).' duyệt.',
    ])->assertCreated();

    $uuid = $tao->json('data.id');

    $this->actingAs($nhanVien)->patchJson("/api/v1/comments/{$uuid}", [
        'body' => 'Thôi tôi tự làm được.',
    ])->assertOk();

    expect(TaskComment::query()->where('uuid', $uuid)->firstOrFail()->mentions)->toBeEmpty();
});
