<?php

declare(strict_types=1);

use App\Domain\Identity\Enums\Role;
use App\Domain\Identity\Models\Department;
use App\Domain\Identity\Models\Position;
use App\Domain\Identity\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Hash;

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->withHeader('Origin', 'http://localhost:3000');
});

/*
|--------------------------------------------------------------------------
| Tạo nhân viên
|--------------------------------------------------------------------------
*/

it('trả về mật khẩu tạm đúng một lần khi tạo nhân viên', function (): void {
    // Không trả ra đây thì không ai biết mật khẩu: database chỉ lưu bản băm.
    // Tài khoản tạo xong sẽ là tài khoản chết, phải bấm thêm "đặt lại mật
    // khẩu" mới dùng được.
    $phong = Department::factory()->create();

    $response = $this->actingAs(quanTri())->postJson('/api/v1/users', [
        'name' => 'Nguyễn Thị Mai',
        'email' => 'mai.nguyen@congty.vn',
        'employee_code' => 'NV2026001',
        'role' => Role::NhanVien->value,
        'department_id' => $phong->uuid,
    ])->assertCreated();

    $matKhauTam = $response->json('meta.temporary_password');

    expect($matKhauTam)->toBeString()->and(strlen((string) $matKhauTam))->toBe(16);

    // Mật khẩu trả ra phải dùng đăng nhập được thật, không phải chuỗi trang trí.
    $nhanVien = User::query()->where('email', 'mai.nguyen@congty.vn')->firstOrFail();

    expect(Hash::check((string) $matKhauTam, $nhanVien->password))->toBeTrue()
        ->and($nhanVien->getRoleNames()->all())->toBe([Role::NhanVien->value])
        ->and($nhanVien->is_active)->toBeTrue();
});

it('mỗi nhân viên một mật khẩu khác nhau', function (): void {
    // Mật khẩu mặc định dùng chung là mật khẩu cả công ty biết.
    $admin = quanTri();

    $mk = [];

    foreach (['a', 'b'] as $i => $ten) {
        $mk[] = $this->actingAs($admin)->postJson('/api/v1/users', [
            'name' => 'Người '.$ten,
            'email' => $ten.'@congty.vn',
            'employee_code' => 'NV'.$i,
            'role' => Role::NhanVien->value,
        ])->assertCreated()->json('meta.temporary_password');
    }

    expect($mk[0])->not->toBe($mk[1]);
});

it('nhân viên thường không tạo được tài khoản', function (): void {
    [, $nhanVien] = sepVaNhanVien();

    $this->actingAs($nhanVien)->postJson('/api/v1/users', [
        'name' => 'Người lạ',
        'email' => 'la@congty.vn',
        'employee_code' => 'NV999',
        'role' => Role::Admin->value,
    ])->assertStatus(403);
});

it('không cho trùng mã nhân viên', function (): void {
    User::factory()->create(['employee_code' => 'NV001']);

    $this->actingAs(quanTri())->postJson('/api/v1/users', [
        'name' => 'Trùng mã',
        'email' => 'trungma@congty.vn',
        'employee_code' => 'NV001',
        'role' => Role::NhanVien->value,
    ])->assertStatus(422)->assertJsonValidationErrors('employee_code');
});

/*
|--------------------------------------------------------------------------
| Danh sách và bộ lọc
|--------------------------------------------------------------------------
*/

it('mặc định chỉ hiện người đang làm việc', function (): void {
    // Trộn người đã nghỉ vào danh sách thì con số "công ty có bao nhiêu người"
    // luôn sai.
    $admin = quanTri();
    User::factory()->create(['name' => 'Đang làm', 'is_active' => true]);
    User::factory()->create(['name' => 'Đã nghỉ', 'is_active' => false]);

    $ten = $this->actingAs($admin)->getJson('/api/v1/users')->assertOk()->json('data.*.name');

    expect($ten)->toContain('Đang làm')->not->toContain('Đã nghỉ');
});

it('xem được cả người đã nghỉ khi yêu cầu', function (): void {
    $admin = quanTri();
    User::factory()->create(['name' => 'Đã nghỉ', 'is_active' => false]);

    $ten = $this->actingAs($admin)
        ->getJson('/api/v1/users?include_inactive=1')
        ->assertOk()->json('data.*.name');

    expect($ten)->toContain('Đã nghỉ');
});

it('tìm nhân viên theo tên, email hoặc mã nhân viên', function (string $tuKhoa): void {
    $admin = quanTri();
    User::factory()->create([
        'name' => 'Trần Quốc Bảo',
        'email' => 'bao.tran@congty.vn',
        'employee_code' => 'NV7788',
    ]);

    $ten = $this->actingAs($admin)
        ->getJson('/api/v1/users?search='.urlencode($tuKhoa))
        ->assertOk()->json('data.*.name');

    expect($ten)->toBe(['Trần Quốc Bảo']);
})->with(['Quốc Bảo', 'bao.tran', 'NV7788']);

it('lọc nhân viên theo phòng ban', function (): void {
    $admin = quanTri();
    $phongA = Department::factory()->create();
    $phongB = Department::factory()->create();

    User::factory()->create(['name' => 'Người phòng A', 'department_id' => $phongA->id]);
    User::factory()->create(['name' => 'Người phòng B', 'department_id' => $phongB->id]);

    $ten = $this->actingAs($admin)
        ->getJson("/api/v1/users?department_id={$phongA->uuid}")
        ->assertOk()->json('data.*.name');

    expect($ten)->toBe(['Người phòng A']);
});

it('lọc nhân viên theo vai trò', function (): void {
    $admin = quanTri();
    [$sep] = sepVaNhanVien();

    $ten = $this->actingAs($admin)
        ->getJson('/api/v1/users?role='.Role::TruongPhong->value)
        ->assertOk()->json('data.*.name');

    expect($ten)->toBe([$sep->name]);
});

/*
|--------------------------------------------------------------------------
| Cơ cấu tổ chức cho ô chọn
|--------------------------------------------------------------------------
*/

it('ai đăng nhập cũng đọc được danh sách phòng ban và chức vụ', function (): void {
    // Cơ cấu tổ chức là thông tin cả công ty vốn đã biết. Giấu nó chỉ làm form
    // không dùng được mà không thêm an toàn nào.
    [, $nhanVien] = sepVaNhanVien();

    Department::factory()->create(['name' => 'Phòng Kỹ thuật']);
    Position::factory()->create(['name' => 'Trưởng nhóm']);

    expect($this->actingAs($nhanVien)->getJson('/api/v1/departments')->assertOk()->json('data.*.name'))
        ->toContain('Phòng Kỹ thuật');

    expect($this->actingAs($nhanVien)->getJson('/api/v1/positions')->assertOk()->json('data.*.name'))
        ->toContain('Trưởng nhóm');
});

it('phòng ban trả về kèm phòng cha để frontend dựng cây', function (): void {
    [, $nhanVien] = sepVaNhanVien();

    $cha = Department::factory()->create(['name' => 'Khối Kinh doanh']);
    Department::factory()->create(['name' => 'Phòng Kinh doanh 1', 'parent_id' => $cha->id]);

    $con = collect($this->actingAs($nhanVien)->getJson('/api/v1/departments')->json('data'))
        ->firstWhere('name', 'Phòng Kinh doanh 1');

    expect($con['parent_id'])->toBe($cha->uuid)
        ->and($con['parent_name'])->toBe('Khối Kinh doanh');
});

it('không trả về phòng ban đã ngừng hoạt động', function (): void {
    [, $nhanVien] = sepVaNhanVien();

    Department::factory()->create(['name' => 'Phòng đã giải thể', 'is_active' => false]);

    expect($this->actingAs($nhanVien)->getJson('/api/v1/departments')->json('data.*.name'))
        ->not->toContain('Phòng đã giải thể');
});

it('chưa đăng nhập thì không đọc được cơ cấu tổ chức', function (): void {
    $this->getJson('/api/v1/departments')->assertStatus(401);
    $this->getJson('/api/v1/positions')->assertStatus(401);
});
