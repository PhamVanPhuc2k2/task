<?php

declare(strict_types=1);

use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Enums\Role;
use App\Domain\Identity\Enums\UserActivityEvent;
use App\Domain\Identity\Models\Department;
use App\Domain\Identity\Models\Position;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Models\UserActivity;
use App\Domain\Task\Models\Task;
use Database\Seeders\RolePermissionSeeder;

/*
|--------------------------------------------------------------------------
| Sửa hồ sơ nhân viên, kích hoạt lại, và nhật ký nhân sự
|--------------------------------------------------------------------------
|
| `quanTri()` dùng chung với EmployeeDirectoryTest nên khai ở tests/Pest.php,
| không khai trong file test — hàm khai trong file test chỉ tồn tại khi file đó
| được nạp, nên chạy riêng file này sẽ đỏ với "undefined function".
|
*/

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->withHeader('Origin', 'http://localhost:3000');
});

/**
 * Hồ sơ đầy đủ để gửi lên PUT.
 *
 * PUT là thay thế toàn bộ, nên mọi test phải gửi đủ trường — thiếu một trường
 * nghĩa là xoá trắng nó, không phải giữ nguyên.
 *
 * @param  array<string, mixed>  $ghiDe
 * @return array<string, mixed>
 */
function hoSo(User $user, array $ghiDe = []): array
{
    return array_merge([
        'name' => $user->name,
        'email' => $user->email,
        'employee_code' => $user->employee_code,
        'role' => $user->getRoleNames()->first() ?? Role::NhanVien->value,
        'phone' => $user->phone,
        'joined_at' => $user->joined_at?->toDateString(),
        'department_id' => $user->department?->uuid,
        'position_id' => $user->position?->uuid,
        'manager_id' => $user->manager?->uuid,
    ], $ghiDe);
}

/*
|--------------------------------------------------------------------------
| Đổi phòng ban
|--------------------------------------------------------------------------
*/

it('đổi được phòng ban của nhân viên', function (): void {
    $cu = Department::factory()->create(['name' => 'Kinh doanh']);
    $moi = Department::factory()->create(['name' => 'Marketing']);

    $nv = User::factory()->for($cu, 'department')->create();
    $nv->assignRole(Role::NhanVien->value);

    $this->actingAs(quanTri())
        ->putJson("/api/v1/users/{$nv->uuid}", hoSo($nv, ['department_id' => $moi->uuid]))
        ->assertOk()
        ->assertJsonPath('data.department.name', 'Marketing');

    expect($nv->refresh()->department_id)->toBe($moi->id);
});

it('đổi phòng ban làm đổi luôn phạm vi task nhìn thấy, không cần sửa gì thêm', function (): void {
    // Test này là chốt chặn cho một giả định của UpdateUserAction: phạm vi task
    // được tính NGAY LÚC TRUY VẤN từ users.department_id, nên đổi cột là xong.
    // Nếu sau này ai đó thêm một bảng đệm phạm vi, test này sẽ đỏ và bắt phải
    // xử lý việc cập nhật bảng đó khi đổi phòng ban.
    $phongA = Department::factory()->create();
    $phongB = Department::factory()->create();

    $sep = User::factory()->for($phongA, 'department')->create();
    $sep->assignRole(Role::TruongPhong->value);

    $nvA = User::factory()->for($phongA, 'department')->create();
    $nvB = User::factory()->for($phongB, 'department')->create();

    $taskA = Task::factory()->create(['assignee_id' => $nvA->id]);
    $taskB = Task::factory()->create(['assignee_id' => $nvB->id]);

    expect($taskA->isVisibleTo($sep))->toBeTrue()
        ->and($taskB->isVisibleTo($sep))->toBeFalse();

    $this->actingAs(quanTri())
        ->putJson("/api/v1/users/{$sep->uuid}", hoSo($sep, ['department_id' => $phongB->uuid]))
        ->assertOk();

    $sepMoi = $sep->fresh();
    expect($sepMoi)->not->toBeNull();
    assert($sepMoi instanceof User);

    expect($taskA->isVisibleTo($sepMoi))->toBeFalse()
        ->and($taskB->isVisibleTo($sepMoi))->toBeTrue();
});

it('cảnh báo khi đổi phòng ban của người còn cấp dưới', function (): void {
    // Cảnh báo, KHÔNG chặn: thao tác vẫn đúng, chỉ là người bấm nút thường
    // không nghĩ tới việc cấp dưới vẫn trỏ về người vừa chuyển đi.
    $cu = Department::factory()->create();
    $moi = Department::factory()->create();

    $sep = User::factory()->for($cu, 'department')->create();
    $sep->assignRole(Role::TruongPhong->value);

    User::factory()->count(3)->create(['manager_id' => $sep->id, 'department_id' => $cu->id]);

    $response = $this->actingAs(quanTri())
        ->putJson("/api/v1/users/{$sep->uuid}", hoSo($sep, ['department_id' => $moi->uuid]))
        ->assertOk();

    $canhBao = $response->json('meta.warnings');

    expect($canhBao)->toBeArray()
        ->and(implode(' ', $canhBao))->toContain('3 nhân viên')
        ->and(implode(' ', $canhBao))->toContain('phạm vi công việc');
});

it('không cảnh báo khi chỉ đổi tên', function (): void {
    $nv = User::factory()->for(Department::factory(), 'department')->create();
    $nv->assignRole(Role::NhanVien->value);

    $this->actingAs(quanTri())
        ->putJson("/api/v1/users/{$nv->uuid}", hoSo($nv, ['name' => 'Tên Mới']))
        ->assertOk()
        ->assertJsonPath('meta.warnings', []);
});

/*
|--------------------------------------------------------------------------
| Vai trò
|--------------------------------------------------------------------------
*/

it('đổi được vai trò của người khác', function (): void {
    $nv = User::factory()->create();
    $nv->assignRole(Role::NhanVien->value);

    $this->actingAs(quanTri())
        ->putJson("/api/v1/users/{$nv->uuid}", hoSo($nv, ['role' => Role::TruongPhong->value]))
        ->assertOk();

    expect($nv->refresh()->getRoleNames()->all())->toBe([Role::TruongPhong->value])
        ->and($nv->can(Permission::ViewTeamTasks->value))->toBeTrue();
});

it('chặn quản trị viên tự đổi vai trò của chính mình', function (): void {
    // Quản trị viên cuối cùng tự hạ vai trò là khoá cả công ty ra ngoài phần
    // quản trị — không còn ai nâng lại được, phải sửa thẳng database.
    $admin = quanTri();

    $this->actingAs($admin)
        ->putJson("/api/v1/users/{$admin->uuid}", hoSo($admin, ['role' => Role::NhanVien->value]))
        ->assertStatus(422)
        ->assertJsonPath('code', 'CANNOT_CHANGE_OWN_ROLE');

    expect($admin->refresh()->getRoleNames()->all())->toBe([Role::Admin->value]);
});

it('cho phép tự sửa hồ sơ của mình miễn là không đổi vai trò', function (): void {
    // Chỉ chặn đúng cái nguy hiểm. Chặn cả việc tự sửa số điện thoại thì thành
    // phiền nhiễu vô cớ.
    $admin = quanTri();

    $this->actingAs($admin)
        ->putJson("/api/v1/users/{$admin->uuid}", hoSo($admin, ['phone' => '0901234567']))
        ->assertOk();

    expect($admin->refresh()->phone)->toBe('0901234567');
});

/*
|--------------------------------------------------------------------------
| Vòng lặp quản lý
|--------------------------------------------------------------------------
*/

it('chặn tự làm quản lý của chính mình', function (): void {
    $nv = User::factory()->create();
    $nv->assignRole(Role::NhanVien->value);

    $this->actingAs(quanTri())
        ->putJson("/api/v1/users/{$nv->uuid}", hoSo($nv, ['manager_id' => $nv->uuid]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('manager_id');
});

it('chặn vòng lặp quản lý dài hơn một bước', function (): void {
    // A quản lý B, B quản lý C. Đặt quản lý của A là C thì sơ đồ tổ chức thành
    // vòng tròn không có người đứng đầu.
    $a = User::factory()->create();
    $a->assignRole(Role::TruongPhong->value);
    $b = User::factory()->create(['manager_id' => $a->id]);
    $c = User::factory()->create(['manager_id' => $b->id]);

    $this->actingAs(quanTri())
        ->putJson("/api/v1/users/{$a->uuid}", hoSo($a, ['manager_id' => $c->uuid]))
        ->assertStatus(422)
        ->assertJsonPath('code', 'MANAGER_CYCLE');

    expect($a->refresh()->manager_id)->toBeNull();
});

it('cho phép đặt quản lý hợp lệ', function (): void {
    $sep = User::factory()->create();
    $nv = User::factory()->create();
    $nv->assignRole(Role::NhanVien->value);

    $this->actingAs(quanTri())
        ->putJson("/api/v1/users/{$nv->uuid}", hoSo($nv, ['manager_id' => $sep->uuid]))
        ->assertOk()
        ->assertJsonPath('data.manager.name', $sep->name);

    expect($nv->refresh()->manager_id)->toBe($sep->id);
});

/*
|--------------------------------------------------------------------------
| Ràng buộc dữ liệu
|--------------------------------------------------------------------------
*/

it('lưu được khi không đổi email — không tự báo trùng với chính mình', function (): void {
    // Thiếu ignore() trong rule unique thì bấm Lưu mà không đổi gì cũng báo
    // "email đã tồn tại", và email đó là của chính người đang sửa.
    $nv = User::factory()->create(['email' => 'nv@congty.vn']);
    $nv->assignRole(Role::NhanVien->value);

    $this->actingAs(quanTri())
        ->putJson("/api/v1/users/{$nv->uuid}", hoSo($nv, ['name' => 'Tên Khác']))
        ->assertOk();
});

it('vẫn chặn email trùng với người khác', function (): void {
    $nguoiKhac = User::factory()->create(['email' => 'da.ton.tai@congty.vn']);
    $nv = User::factory()->create();
    $nv->assignRole(Role::NhanVien->value);

    $this->actingAs(quanTri())
        ->putJson("/api/v1/users/{$nv->uuid}", hoSo($nv, ['email' => $nguoiKhac->email]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('email');
});

it('không cho nhân viên thường sửa hồ sơ người khác', function (): void {
    $nv = User::factory()->create();
    $nv->assignRole(Role::NhanVien->value);

    $nanNhan = User::factory()->create();
    $nanNhan->assignRole(Role::NhanVien->value);

    $this->actingAs($nv)
        ->putJson("/api/v1/users/{$nanNhan->uuid}", hoSo($nanNhan, ['name' => 'Bị đổi']))
        ->assertForbidden();
});

/*
|--------------------------------------------------------------------------
| Kích hoạt lại
|--------------------------------------------------------------------------
*/

it('mở lại được tài khoản đã vô hiệu hoá', function (): void {
    $nv = User::factory()->create(['is_active' => false, 'terminated_at' => now()]);

    $this->actingAs(quanTri())
        ->postJson("/api/v1/users/{$nv->uuid}/activate")
        ->assertNoContent();

    $nv->refresh();

    expect($nv->is_active)->toBeTrue()
        // Giữ lại ngày nghỉ cũ sẽ khiến mọi báo cáo nhân sự về sau đọc sai.
        ->and($nv->terminated_at)->toBeNull();
});

it('người được mở lại đăng nhập vào hệ thống được ngay', function (): void {
    // Vô hiệu hoá chặn ở middleware `active`. Nếu mở lại mà middleware vẫn
    // chặn thì tính năng này vô nghĩa — kiểm bằng một request thật.
    $nv = User::factory()->create(['is_active' => false, 'terminated_at' => now()]);
    $nv->assignRole(Role::NhanVien->value);

    $this->actingAs($nv)->getJson('/api/v1/auth/me')->assertForbidden();

    $this->actingAs(quanTri())->postJson("/api/v1/users/{$nv->uuid}/activate")->assertNoContent();

    $this->actingAs($nv->fresh())->getJson('/api/v1/auth/me')->assertOk();
});

it('kích hoạt lại người đang hoạt động thì không ghi thêm nhật ký', function (): void {
    $nv = User::factory()->create(['is_active' => true]);

    $this->actingAs(quanTri())
        ->postJson("/api/v1/users/{$nv->uuid}/activate")
        ->assertNoContent();

    expect(UserActivity::query()->where('user_id', $nv->id)->count())->toBe(0);
});

it('không cho nhân viên thường kích hoạt lại người khác', function (): void {
    $nv = User::factory()->create();
    $nv->assignRole(Role::NhanVien->value);

    $nghi = User::factory()->create(['is_active' => false]);

    $this->actingAs($nv)
        ->postJson("/api/v1/users/{$nghi->uuid}/activate")
        ->assertForbidden();
});

/*
|--------------------------------------------------------------------------
| Nhật ký nhân sự
|--------------------------------------------------------------------------
*/

it('ghi nhật ký khi đổi phòng ban, kèm TÊN phòng chứ không phải id', function (): void {
    // Lưu id thì một năm sau, khi phòng ban đã đổi tên hoặc bị gộp, dòng
    // "department_id: 3 → 7" không còn nói lên điều gì.
    $cu = Department::factory()->create(['name' => 'Kinh doanh']);
    $moi = Department::factory()->create(['name' => 'Marketing']);

    $nv = User::factory()->for($cu, 'department')->create();
    $nv->assignRole(Role::NhanVien->value);
    $admin = quanTri();

    $this->actingAs($admin)
        ->putJson("/api/v1/users/{$nv->uuid}", hoSo($nv, ['department_id' => $moi->uuid]))
        ->assertOk();

    $moc = UserActivity::query()
        ->where('user_id', $nv->id)
        ->where('event', UserActivityEvent::ProfileUpdated->value)
        ->sole();

    expect($moc->causer_id)->toBe($admin->id)
        ->and($moc->old_values)->toBe(['department' => 'Kinh doanh'])
        ->and($moc->new_values)->toBe(['department' => 'Marketing']);
});

it('ghi nhật ký riêng cho việc đổi vai trò', function (): void {
    $nv = User::factory()->create();
    $nv->assignRole(Role::NhanVien->value);

    $this->actingAs(quanTri())
        ->putJson("/api/v1/users/{$nv->uuid}", hoSo($nv, ['role' => Role::TruongPhong->value]))
        ->assertOk();

    $moc = UserActivity::query()
        ->where('user_id', $nv->id)
        ->where('event', UserActivityEvent::RoleChanged->value)
        ->sole();

    expect($moc->old_values)->toBe(['role' => Role::NhanVien->value])
        ->and($moc->new_values)->toBe(['role' => Role::TruongPhong->value]);
});

it('không ghi nhật ký khi bấm lưu mà không đổi gì', function (): void {
    // Ghi cả hồ sơ mỗi lần bấm lưu thì nhật ký đầy dòng vô nghĩa, và tới lúc
    // cần tra thì không ai đọc nữa.
    $nv = User::factory()->for(Department::factory(), 'department')->create();
    $nv->assignRole(Role::NhanVien->value);

    $this->actingAs(quanTri())
        ->putJson("/api/v1/users/{$nv->uuid}", hoSo($nv))
        ->assertOk();

    expect(UserActivity::query()->where('user_id', $nv->id)->count())->toBe(0);
});

it('chỉ ghi những trường thật sự đổi', function (): void {
    $nv = User::factory()->create(['name' => 'Tên Cũ', 'phone' => '0900000000']);
    $nv->assignRole(Role::NhanVien->value);

    $this->actingAs(quanTri())
        ->putJson("/api/v1/users/{$nv->uuid}", hoSo($nv, ['name' => 'Tên Mới']))
        ->assertOk();

    $moc = UserActivity::query()->where('user_id', $nv->id)->sole();

    expect(array_keys((array) $moc->new_values))->toBe(['name']);
});

it('ghi nhật ký khi tạo, vô hiệu hoá và kích hoạt lại', function (): void {
    $admin = quanTri();
    $phong = Department::factory()->create();

    $this->actingAs($admin)->postJson('/api/v1/users', [
        'name' => 'Trần Văn B',
        'email' => 'b.tran@congty.vn',
        'employee_code' => 'NV2026099',
        'role' => Role::NhanVien->value,
        'department_id' => $phong->uuid,
    ])->assertCreated();

    $nv = User::query()->where('email', 'b.tran@congty.vn')->sole();

    $this->actingAs($admin)->postJson("/api/v1/users/{$nv->uuid}/deactivate")->assertNoContent();
    $this->actingAs($admin)->postJson("/api/v1/users/{$nv->uuid}/activate")->assertNoContent();

    $bienCo = UserActivity::query()
        ->where('user_id', $nv->id)
        ->orderBy('id')
        ->pluck('event')
        ->map(fn (UserActivityEvent $e): string => $e->value)
        ->all();

    expect($bienCo)->toBe([
        UserActivityEvent::Created->value,
        UserActivityEvent::Deactivated->value,
        UserActivityEvent::Activated->value,
    ]);
});

it('ghi nhật ký khi đặt lại mật khẩu nhưng KHÔNG ghi mật khẩu', function (): void {
    // Nhật ký kiểm toán mà chứa thông tin xác thực thì bản thân nó thành chỗ
    // rò rỉ — ai đọc được nhật ký sẽ đăng nhập được thay người khác.
    $nv = User::factory()->create();
    $nv->assignRole(Role::NhanVien->value);

    $response = $this->actingAs(quanTri())
        ->postJson("/api/v1/users/{$nv->uuid}/reset-password")
        ->assertOk();

    $matKhau = (string) $response->json('data.temporary_password');
    $moc = UserActivity::query()->where('user_id', $nv->id)->sole();

    expect($moc->event)->toBe(UserActivityEvent::PasswordReset)
        ->and(json_encode([$moc->old_values, $moc->new_values]))->not->toContain($matKhau);
});

it('đọc được nhật ký qua API, mới nhất lên đầu', function (): void {
    $nv = User::factory()->create();
    $nv->assignRole(Role::NhanVien->value);
    $admin = quanTri();

    $this->actingAs($admin)
        ->putJson("/api/v1/users/{$nv->uuid}", hoSo($nv, ['name' => 'Tên Mới']))
        ->assertOk();
    $this->actingAs($admin)->postJson("/api/v1/users/{$nv->uuid}/deactivate")->assertNoContent();

    $this->actingAs($admin)
        ->getJson("/api/v1/users/{$nv->uuid}/activities")
        ->assertOk()
        ->assertJsonPath('data.0.event', UserActivityEvent::Deactivated->value)
        ->assertJsonPath('data.0.event_label', 'Vô hiệu hoá')
        ->assertJsonPath('data.0.causer.name', $admin->name)
        ->assertJsonPath('data.1.event', UserActivityEvent::ProfileUpdated->value);
});

it('nhân viên thường không đọc được nhật ký của chính mình', function (): void {
    // Khác với hồ sơ — nhật ký chứa tên người ra quyết định vô hiệu hoá hay hạ
    // vai trò. Đó là thông tin quản trị, không phải hồ sơ cá nhân.
    $nv = User::factory()->create();
    $nv->assignRole(Role::NhanVien->value);

    $this->actingAs($nv)
        ->getJson("/api/v1/users/{$nv->uuid}/activities")
        ->assertForbidden();
});

it('nhật ký không sửa và không xoá được qua API', function (): void {
    // Bản ghi kiểm toán mà sửa được thì không còn là kiểm toán. Không có route
    // nào ghi vào bảng này ngoài chính các Action.
    $nv = User::factory()->create();

    $this->actingAs(quanTri())
        ->putJson("/api/v1/users/{$nv->uuid}/activities", [])
        ->assertStatus(405);

    $this->actingAs(quanTri())
        ->deleteJson("/api/v1/users/{$nv->uuid}/activities")
        ->assertStatus(405);
});

/*
|--------------------------------------------------------------------------
| Chức vụ
|--------------------------------------------------------------------------
*/

it('đổi được chức vụ và ghi tên chức vụ vào nhật ký', function (): void {
    $cu = Position::factory()->create(['name' => 'Nhân viên']);
    $moi = Position::factory()->create(['name' => 'Trưởng nhóm']);

    $nv = User::factory()->for($cu, 'position')->create();
    $nv->assignRole(Role::NhanVien->value);

    $this->actingAs(quanTri())
        ->putJson("/api/v1/users/{$nv->uuid}", hoSo($nv, ['position_id' => $moi->uuid]))
        ->assertOk()
        ->assertJsonPath('data.position.name', 'Trưởng nhóm');

    $moc = UserActivity::query()->where('user_id', $nv->id)->sole();

    expect($moc->new_values)->toBe(['position' => 'Trưởng nhóm']);
});

it('xoá được phòng ban bằng cách gửi null', function (): void {
    // Ngữ nghĩa PUT: gửi null là bỏ trống, không phải "không đụng tới".
    $nv = User::factory()->for(Department::factory(), 'department')->create();
    $nv->assignRole(Role::NhanVien->value);

    $this->actingAs(quanTri())
        ->putJson("/api/v1/users/{$nv->uuid}", hoSo($nv, ['department_id' => null]))
        ->assertOk();

    expect($nv->refresh()->department_id)->toBeNull();
});
