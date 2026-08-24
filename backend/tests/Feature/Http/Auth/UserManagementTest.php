<?php

declare(strict_types=1);

use App\Domain\Identity\Enums\Role;
use App\Domain\Identity\Models\Department;
use App\Domain\Identity\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Hash;

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->withHeader('Origin', config('app.url'));
});

function admin(): User
{
    $admin = User::factory()->create(['password' => Hash::make('MatKhauCu@2026xyz')]);
    $admin->assignRole(Role::Admin->value);

    return $admin;
}

/*
|--------------------------------------------------------------------------
| Người dùng tự đổi mật khẩu
|--------------------------------------------------------------------------
*/

it('cho người dùng tự đổi mật khẩu khi nhập đúng mật khẩu hiện tại', function (): void {
    $user = admin();

    $this->actingAs($user)->patchJson('/api/v1/auth/password', [
        'current_password' => 'MatKhauCu@2026xyz',
        'password' => 'MatKhauMoi@2026abc',
        'password_confirmation' => 'MatKhauMoi@2026abc',
    ])->assertNoContent();

    expect(Hash::check('MatKhauMoi@2026abc', (string) $user->refresh()->password))->toBeTrue();
});

it('từ chối đổi mật khẩu khi mật khẩu hiện tại sai', function (): void {
    $user = admin();

    $this->actingAs($user)->patchJson('/api/v1/auth/password', [
        'current_password' => 'nho-nham-roi',
        'password' => 'MatKhauMoi@2026abc',
        'password_confirmation' => 'MatKhauMoi@2026abc',
    ])->assertStatus(422)->assertJsonPath('code', 'VALIDATION_FAILED');

    expect(Hash::check('MatKhauCu@2026xyz', (string) $user->refresh()->password))->toBeTrue();
});

it('từ chối mật khẩu mới quá ngắn', function (): void {
    $user = admin();

    $this->actingAs($user)->patchJson('/api/v1/auth/password', [
        'current_password' => 'MatKhauCu@2026xyz',
        'password' => 'ngan',
        'password_confirmation' => 'ngan',
    ])->assertStatus(422)->assertJsonStructure(['errors' => ['password']]);
});

/*
|--------------------------------------------------------------------------
| Quản trị người dùng
|--------------------------------------------------------------------------
*/

it('nhân viên thường không được vào khu quản trị người dùng', function (): void {
    $nhanVien = User::factory()->create();
    $nhanVien->assignRole(Role::NhanVien->value);

    $this->actingAs($nhanVien)->getJson('/api/v1/users')
        ->assertStatus(403)
        ->assertJsonPath('code', 'FORBIDDEN');
});

it('quản trị viên xem được danh sách người dùng', function (): void {
    User::factory()->count(3)->create();

    $this->actingAs(admin())->getJson('/api/v1/users')
        ->assertOk()
        ->assertJsonCount(4, 'data');
});

it('quản trị viên tạo được tài khoản mới kèm vai trò', function (): void {
    $phong = Department::factory()->create();

    $this->actingAs(admin())->postJson('/api/v1/users', [
        'name' => 'Nguyễn Văn Bình',
        'email' => 'binh@congty.vn',
        'employee_code' => 'NV100',
        'department_id' => $phong->uuid,
        'role' => Role::TruongPhong->value,
    ])->assertCreated()->assertJsonPath('data.email', 'binh@congty.vn');

    $moi = User::query()->where('email', 'binh@congty.vn')->firstOrFail();

    expect($moi->hasRole(Role::TruongPhong->value))->toBeTrue()
        ->and($moi->department_id)->toBe($phong->id)
        ->and($moi->is_active)->toBeTrue();
});

it('không cho tạo trùng email', function (): void {
    User::factory()->create(['email' => 'trung@congty.vn']);

    $this->actingAs(admin())->postJson('/api/v1/users', [
        'name' => 'Trùng',
        'email' => 'trung@congty.vn',
        'employee_code' => 'NV101',
        'role' => Role::NhanVien->value,
    ])->assertStatus(422)->assertJsonStructure(['errors' => ['email']]);
});

it('vô hiệu hoá tài khoản thay vì xoá, giữ nguyên vết công việc', function (): void {
    $nghiViec = User::factory()->create();

    $this->actingAs(admin())
        ->postJson("/api/v1/users/{$nghiViec->uuid}/deactivate")
        ->assertNoContent();

    $nghiViec->refresh();

    expect($nghiViec->is_active)->toBeFalse()
        ->and($nghiViec->terminated_at)->not->toBeNull()
        // Bản ghi vẫn còn: task họ từng làm phải còn người đứng tên.
        ->and(User::query()->whereKey($nghiViec->id)->exists())->toBeTrue();
});

it('không cho quản trị viên tự vô hiệu hoá chính mình', function (): void {
    // Tự khoá mình là cách nhanh nhất để không còn ai vào được hệ thống.
    $admin = admin();

    $this->actingAs($admin)
        ->postJson("/api/v1/users/{$admin->uuid}/deactivate")
        ->assertStatus(422)
        ->assertJsonPath('code', 'CANNOT_DISABLE_SELF');

    expect($admin->refresh()->is_active)->toBeTrue();
});

it('quản trị viên đặt lại mật khẩu hộ và nhận về mật khẩu tạm', function (): void {
    $quenMatKhau = User::factory()->create();
    $matKhauCu = $quenMatKhau->password;

    $response = $this->actingAs(admin())
        ->postJson("/api/v1/users/{$quenMatKhau->uuid}/reset-password")
        ->assertOk()
        ->assertJsonStructure(['data' => ['temporary_password']]);

    $matKhauTam = $response->json('data.temporary_password');

    expect($quenMatKhau->refresh()->password)->not->toBe($matKhauCu)
        ->and(Hash::check($matKhauTam, (string) $quenMatKhau->password))->toBeTrue();
});

it('dùng uuid trên URL, không lộ id tuần tự', function (): void {
    $user = User::factory()->create();

    $this->actingAs(admin())
        ->postJson("/api/v1/users/{$user->id}/deactivate")
        ->assertStatus(404);
});
