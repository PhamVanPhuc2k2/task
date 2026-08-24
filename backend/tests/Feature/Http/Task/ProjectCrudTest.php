<?php

declare(strict_types=1);

use App\Domain\Identity\Models\User;
use App\Domain\Task\Enums\ProjectRole;
use App\Domain\Task\Enums\ProjectStatus;
use App\Domain\Task\Models\Project;
use App\Domain\Task\Models\Task;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->withHeader('Origin', 'http://localhost:3000');
});

// sepVaNhanVien() khai báo ở tests/Pest.php. Trưởng phòng có quyền
// project.manage, nhân viên thì không — đó là ranh giới các test dưới đây kiểm.

/*
|--------------------------------------------------------------------------
| Phạm vi xem dự án
|--------------------------------------------------------------------------
*/

it('nhân viên không thấy dự án mình không liên quan', function (): void {
    // Dự án lộ tên khách hàng và kế hoạch kinh doanh. Mặc định là không thấy,
    // phải có lý do cụ thể mới thấy.
    [, $nhanVien] = sepVaNhanVien();

    Project::factory()->create(['name' => 'Dự án bí mật']);

    expect($this->actingAs($nhanVien)->getJson('/api/v1/projects')->assertOk()->json('data'))
        ->toBeEmpty();
});

it('thành viên thấy dự án mình tham gia', function (): void {
    [, $nhanVien] = sepVaNhanVien();

    $duAn = Project::factory()->create(['name' => 'Dự án của tôi']);
    $duAn->members()->attach($nhanVien->id, ['role' => ProjectRole::Member->value]);

    expect($this->actingAs($nhanVien)->getJson('/api/v1/projects')->json('data.*.name'))
        ->toBe(['Dự án của tôi']);
});

it('người được giao task trong dự án thấy dự án đó', function (): void {
    // Không thấy dự án thì nhìn task cũng không hiểu mình đang làm cho việc gì.
    [, $nhanVien] = sepVaNhanVien();

    $duAn = Project::factory()->create(['name' => 'Dự án có việc của tôi']);
    Task::factory()->for($duAn, 'project')->for($nhanVien, 'assignee')->create();

    expect($this->actingAs($nhanVien)->getJson('/api/v1/projects')->json('data.*.name'))
        ->toBe(['Dự án có việc của tôi']);
});

it('người có quyền quản lý dự án thấy tất cả', function (): void {
    [$sep] = sepVaNhanVien();

    Project::factory()->count(3)->create();

    expect($this->actingAs($sep)->getJson('/api/v1/projects')->json('data'))
        ->toHaveCount(3);
});

it('không xem được chi tiết dự án ngoài phạm vi', function (): void {
    [, $nhanVien] = sepVaNhanVien();

    $duAn = Project::factory()->create();

    $this->actingAs($nhanVien)->getJson("/api/v1/projects/{$duAn->uuid}")->assertStatus(403);
});

it('lọc dự án theo trạng thái', function (): void {
    [$sep] = sepVaNhanVien();

    Project::factory()->create(['name' => 'Đang chạy', 'status' => ProjectStatus::Active]);
    Project::factory()->create(['name' => 'Đã xong', 'status' => ProjectStatus::Completed]);

    expect($this->actingAs($sep)->getJson('/api/v1/projects?status=active')->json('data.*.name'))
        ->toBe(['Đang chạy']);
});

/*
|--------------------------------------------------------------------------
| Tạo, sửa, xoá
|--------------------------------------------------------------------------
*/

it('nhân viên không tạo được dự án', function (): void {
    [, $nhanVien] = sepVaNhanVien();

    $this->actingAs($nhanVien)->postJson('/api/v1/projects', ['name' => 'Dự án lậu'])
        ->assertStatus(403);
});

it('người tạo mặc định là chủ dự án và là quản lý trong dự án', function (): void {
    // Dự án không có chủ là dự án không ai chịu trách nhiệm.
    [$sep] = sepVaNhanVien();

    $response = $this->actingAs($sep)->postJson('/api/v1/projects', [
        'name' => 'Website mới',
        'code' => 'WEB2026',
    ])->assertCreated();

    $duAn = Project::query()->where('code', 'WEB2026')->firstOrFail();

    expect($duAn->owner_id)->toBe($sep->id)
        ->and($duAn->created_by)->toBe($sep->id)
        ->and($duAn->status)->toBe(ProjectStatus::Planning)
        ->and($duAn->memberRoles()[$sep->id] ?? null)->toBe(ProjectRole::Manager->value)
        ->and($response->json('data.id'))->toBe($duAn->uuid);
});

it('không cho trùng mã dự án', function (): void {
    [$sep] = sepVaNhanVien();

    Project::factory()->create(['code' => 'WEB2026']);

    $this->actingAs($sep)->postJson('/api/v1/projects', ['name' => 'Trùng mã', 'code' => 'WEB2026'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('code');
});

it('không cho ngày kết thúc trước ngày bắt đầu', function (): void {
    [$sep] = sepVaNhanVien();

    $this->actingAs($sep)->postJson('/api/v1/projects', [
        'name' => 'Ngược ngày',
        'start_date' => '2026-09-01',
        'end_date' => '2026-08-01',
    ])->assertStatus(422)->assertJsonValidationErrors('end_date');
});

it('sửa được tên và trạng thái dự án', function (): void {
    [$sep] = sepVaNhanVien();

    $duAn = Project::factory()->create(['owner_id' => $sep->id]);

    $this->actingAs($sep)->patchJson("/api/v1/projects/{$duAn->uuid}", [
        'name' => 'Tên mới',
        'status' => ProjectStatus::Active->value,
    ])->assertOk()->assertJsonPath('data.name', 'Tên mới');

    expect($duAn->refresh()->status)->toBe(ProjectStatus::Active)
        ->and($duAn->updated_by)->toBe($sep->id);
});

it('chủ dự án sửa được dù không có quyền quản lý dự án', function (): void {
    // Giao dự án cho một nhân viên phụ trách là chuyện bình thường; bắt họ xin
    // quyền toàn hệ thống chỉ để đổi mô tả dự án của chính mình là vô lý.
    [, $nhanVien] = sepVaNhanVien();

    $duAn = Project::factory()->create(['owner_id' => $nhanVien->id]);

    $this->actingAs($nhanVien)->patchJson("/api/v1/projects/{$duAn->uuid}", ['name' => 'Tôi tự sửa'])
        ->assertOk();
});

it('chỉ người có quyền quản lý mới xoá được dự án', function (): void {
    // Xoá là mất dấu vết cả một mảng công việc — nặng hơn sửa, nên chủ dự án
    // thôi chưa đủ.
    [$sep, $nhanVien] = sepVaNhanVien();

    $duAn = Project::factory()->create(['owner_id' => $nhanVien->id]);

    $this->actingAs($nhanVien)->deleteJson("/api/v1/projects/{$duAn->uuid}")->assertStatus(403);
    $this->actingAs($sep)->deleteJson("/api/v1/projects/{$duAn->uuid}")->assertNoContent();

    // Xoá mềm: dự án đã chạy là lịch sử công việc của cả đội.
    expect(Project::withTrashed()->whereKey($duAn->id)->first()?->trashed())->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| Thành viên dự án
|--------------------------------------------------------------------------
*/

it('thêm thành viên vào dự án kèm vai trò', function (): void {
    [$sep, $nhanVien] = sepVaNhanVien();

    $duAn = Project::factory()->create(['owner_id' => $sep->id]);

    $this->actingAs($sep)->postJson("/api/v1/projects/{$duAn->uuid}/members", [
        'user_id' => $nhanVien->uuid,
        'role' => ProjectRole::Member->value,
    ])->assertOk();

    expect($duAn->memberRoles()[$nhanVien->id] ?? null)->toBe(ProjectRole::Member->value);
});

it('thêm lại người đã có thì chỉ đổi vai trò, không nhân đôi', function (): void {
    [$sep, $nhanVien] = sepVaNhanVien();

    $duAn = Project::factory()->create(['owner_id' => $sep->id]);
    $duAn->members()->attach($nhanVien->id, ['role' => ProjectRole::Viewer->value]);

    $this->actingAs($sep)->postJson("/api/v1/projects/{$duAn->uuid}/members", [
        'user_id' => $nhanVien->uuid,
        'role' => ProjectRole::Manager->value,
    ])->assertOk();

    expect($duAn->members()->count())->toBe(1)
        ->and($duAn->memberRoles()[$nhanVien->id] ?? null)->toBe(ProjectRole::Manager->value);
});

it('gỡ thành viên khỏi dự án', function (): void {
    [$sep, $nhanVien] = sepVaNhanVien();

    $duAn = Project::factory()->create(['owner_id' => $sep->id]);
    $duAn->members()->attach($nhanVien->id, ['role' => ProjectRole::Member->value]);

    $this->actingAs($sep)->deleteJson("/api/v1/projects/{$duAn->uuid}/members/{$nhanVien->uuid}")
        ->assertNoContent();

    expect($duAn->members()->count())->toBe(0);
});

it('thành viên thường không thêm được người khác vào dự án', function (): void {
    [, $nhanVien] = sepVaNhanVien();
    $nguoiKhac = User::factory()->create();

    $duAn = Project::factory()->create();
    $duAn->members()->attach($nhanVien->id, ['role' => ProjectRole::Member->value]);

    $this->actingAs($nhanVien)->postJson("/api/v1/projects/{$duAn->uuid}/members", [
        'user_id' => $nguoiKhac->uuid,
        'role' => ProjectRole::Member->value,
    ])->assertStatus(403);
});

it('xem được danh sách thành viên của dự án mình tham gia', function (): void {
    [$sep, $nhanVien] = sepVaNhanVien();

    $duAn = Project::factory()->create(['owner_id' => $sep->id]);
    $duAn->members()->attach($nhanVien->id, ['role' => ProjectRole::Member->value]);

    expect($this->actingAs($nhanVien)->getJson("/api/v1/projects/{$duAn->uuid}/members")
        ->assertOk()->json('data.*.name'))
        ->toContain($nhanVien->name);
});

/*
|--------------------------------------------------------------------------
| Ràng buộc giữa task và dự án
|--------------------------------------------------------------------------
*/

it('không tạo task mới vào dự án đã đóng', function (): void {
    // Dự án đã hoàn thành hoặc đã huỷ mà vẫn nhận việc mới thì mọi báo cáo
    // tiến độ dự án đều sai.
    [$sep] = sepVaNhanVien();

    $duAn = Project::factory()->create(['status' => ProjectStatus::Completed]);

    $this->actingAs($sep)->postJson('/api/v1/tasks', [
        'title' => 'Việc muộn',
        'project_id' => $duAn->uuid,
    ])->assertStatus(422)->assertJsonValidationErrors('project_id');
});
