<?php

declare(strict_types=1);

use App\Domain\Task\Models\Project;
use App\Domain\Task\Models\Task;
use App\Domain\Task\Models\TaskComment;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Notifications\DatabaseNotification;

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->withHeader('Origin', 'http://localhost:3000');
});

/*
|--------------------------------------------------------------------------
| IDOR — đổi id trong URL sang bản ghi của người khác
|--------------------------------------------------------------------------
|
| Đây là lớp lỗi nguy hiểm nhất của một hệ thống nội bộ: mọi thứ trông đúng
| trên giao diện, nhưng chỉ cần sửa một uuid trong thanh địa chỉ là đọc được
| dữ liệu của phòng khác. Mỗi endpoint nhận id phải có một test ở đây.
|
| Quy ước mã trả về: **403** khi bản ghi có tồn tại nhưng ngoài phạm vi, và
| **404** khi bản ghi thuộc về một tài nguyên cha khác. Cả hai đều không nói
| cho người dò biết bản ghi có tồn tại hay không nhiều hơn mức cần thiết.
|
*/

it('không đọc được task của phòng khác', function (): void {
    $task = Task::factory()->create();

    $this->actingAs(nguoiNgoai())
        ->getJson("/api/v1/tasks/{$task->uuid}")
        ->assertStatus(403);
});

it('không sửa được task của phòng khác', function (): void {
    $task = Task::factory()->create();

    $this->actingAs(nguoiNgoai())
        ->patchJson("/api/v1/tasks/{$task->uuid}", ['title' => 'Chiếm quyền'])
        ->assertStatus(403);
});

it('không xoá được task của phòng khác', function (): void {
    $task = Task::factory()->create();

    $this->actingAs(nguoiNgoai())
        ->deleteJson("/api/v1/tasks/{$task->uuid}")
        ->assertStatus(403);
});

it('không đổi được trạng thái task của phòng khác', function (): void {
    $task = Task::factory()->create();

    $this->actingAs(nguoiNgoai())
        ->patchJson("/api/v1/tasks/{$task->uuid}/status", ['status' => 'in_progress'])
        ->assertStatus(403);
});

it('không dời được hạn task của phòng khác', function (): void {
    $task = Task::factory()->create();

    $this->actingAs(nguoiNgoai())
        ->patchJson("/api/v1/tasks/{$task->uuid}/due-date", [
            'due_date' => '2026-12-31T17:00',
            'reason' => 'Cố tình dời hạn việc của người khác.',
        ])
        ->assertStatus(403);
});

it('không giao lại được task của phòng khác', function (): void {
    [$sep] = sepVaNhanVien();
    $task = Task::factory()->create();

    $this->actingAs(nguoiNgoai())
        ->patchJson("/api/v1/tasks/{$task->uuid}/assign", ['assignee_id' => $sep->uuid])
        ->assertStatus(403);
});

it('không đọc được nhật ký hoạt động của task phòng khác', function (): void {
    $task = Task::factory()->create();

    $this->actingAs(nguoiNgoai())
        ->getJson("/api/v1/tasks/{$task->uuid}/activities")
        ->assertStatus(403);
});

it('không đọc được dự án ngoài phạm vi', function (): void {
    $duAn = Project::factory()->create();

    $this->actingAs(nguoiNgoai())
        ->getJson("/api/v1/projects/{$duAn->uuid}")
        ->assertStatus(403);
});

it('không đọc được thành viên của dự án ngoài phạm vi', function (): void {
    $duAn = Project::factory()->create();

    $this->actingAs(nguoiNgoai())
        ->getJson("/api/v1/projects/{$duAn->uuid}/members")
        ->assertStatus(403);
});

it('không thêm được thành viên vào dự án ngoài phạm vi', function (): void {
    [$sep] = sepVaNhanVien();
    $duAn = Project::factory()->create();

    $this->actingAs(nguoiNgoai())
        ->postJson("/api/v1/projects/{$duAn->uuid}/members", [
            'user_id' => $sep->uuid,
            'role' => 'manager',
        ])
        ->assertStatus(403);
});

it('không đọc được bình luận của task phòng khác', function (): void {
    $task = Task::factory()->create();

    $this->actingAs(nguoiNgoai())
        ->getJson("/api/v1/tasks/{$task->uuid}/comments")
        ->assertStatus(403);
});

it('không sửa được bình luận của người khác', function (): void {
    [, $nhanVien] = sepVaNhanVien();
    $task = Task::factory()->for($nhanVien, 'assignee')->create();
    $comment = TaskComment::factory()->for($task)->for($nhanVien, 'author')->create();

    $this->actingAs(nguoiNgoai())
        ->patchJson("/api/v1/comments/{$comment->uuid}", ['body' => 'Sửa trộm'])
        ->assertStatus(403);
});

it('không đính kèm được tệp vào bình luận của người khác', function (): void {
    [, $nhanVien] = sepVaNhanVien();
    $task = Task::factory()->for($nhanVien, 'assignee')->create();
    $comment = TaskComment::factory()->for($task)->for($nhanVien, 'author')->create();

    $this->actingAs(nguoiNgoai())
        ->postJson("/api/v1/comments/{$comment->uuid}/attachments", [])
        ->assertStatus(403);
});

it('không đọc được hồ sơ nhân sự khi không có quyền quản trị', function (): void {
    $this->actingAs(nguoiNgoai())->getJson('/api/v1/users')->assertStatus(403);
});

it('không vô hiệu hoá được tài khoản người khác', function (): void {
    [$sep] = sepVaNhanVien();

    $this->actingAs(nguoiNgoai())
        ->postJson("/api/v1/users/{$sep->uuid}/deactivate")
        ->assertStatus(403);
});

it('không đặt lại được mật khẩu người khác', function (): void {
    [$sep] = sepVaNhanVien();

    $this->actingAs(nguoiNgoai())
        ->postJson("/api/v1/users/{$sep->uuid}/reset-password")
        ->assertStatus(403);
});

it('không gỡ được xác thực hai lớp của người khác', function (): void {
    [$sep] = sepVaNhanVien();

    $this->actingAs(nguoiNgoai())
        ->postJson("/api/v1/users/{$sep->uuid}/reset-two-factor")
        ->assertStatus(403);
});

it('không đánh dấu đọc được thông báo của người khác', function (): void {
    [$sep, $nhanVien] = sepVaNhanVien();

    $this->actingAs($sep)->postJson('/api/v1/tasks', [
        'title' => 'Việc mới', 'assignee_id' => $nhanVien->uuid,
    ])->assertCreated();

    $id = DatabaseNotification::query()->firstOrFail()->id;

    $this->actingAs(nguoiNgoai())
        ->patchJson("/api/v1/notifications/{$id}/read")
        ->assertStatus(404);
});

it('mọi endpoint đều từ chối khi chưa đăng nhập', function (string $method, string $path): void {
    $this->json($method, $path)->assertStatus(401);
})->with([
    ['GET', '/api/v1/tasks'],
    ['POST', '/api/v1/tasks'],
    ['GET', '/api/v1/tasks/my'],
    ['GET', '/api/v1/tasks/team'],
    ['GET', '/api/v1/projects'],
    ['GET', '/api/v1/users'],
    ['GET', '/api/v1/users/assignable'],
    ['GET', '/api/v1/notifications'],
    ['GET', '/api/v1/notification-settings'],
    ['GET', '/api/v1/auth/me'],
]);
