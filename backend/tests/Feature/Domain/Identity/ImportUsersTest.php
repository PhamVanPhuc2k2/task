<?php

declare(strict_types=1);

use App\Domain\Identity\Models\Department;
use App\Domain\Identity\Models\Position;
use App\Domain\Identity\Models\User;

/** Ghi một file CSV tạm rồi trả về đường dẫn. */
function csvTam(string $noiDung, bool $themBom = false): string
{
    $thuMuc = storage_path('app/testing');
    if (! is_dir($thuMuc)) {
        mkdir($thuMuc, 0o777, true);
    }

    $duongDan = $thuMuc.'/'.uniqid('import_', true).'.csv';
    file_put_contents($duongDan, ($themBom ? "\u{FEFF}" : '').$noiDung);

    return $duongDan;
}

beforeEach(function (): void {
    Department::factory()->create(['code' => 'SALE', 'name' => 'Phòng Sale']);
    Position::factory()->create(['code' => 'NV', 'name' => 'Nhân viên']);
});

it('nhập được nhân viên từ file CSV', function (): void {
    $file = csvTam(<<<'CSV'
        employee_code,name,email,phone,department_code,position_code,manager_email,joined_at
        NV001,Nguyễn Văn An,an@congty.vn,0901234567,SALE,NV,,2025-01-15
        CSV);

    $this->artisan('users:import', ['file' => $file])->assertSuccessful();

    $nhanVien = User::query()->where('employee_code', 'NV001')->firstOrFail();

    expect($nhanVien->name)->toBe('Nguyễn Văn An')
        ->and($nhanVien->email)->toBe('an@congty.vn')
        ->and($nhanVien->department?->code)->toBe('SALE')
        ->and($nhanVien->position?->code)->toBe('NV')
        ->and($nhanVien->is_active)->toBeTrue();
});

it('đọc đúng tiếng Việt có dấu khi Excel xuất file kèm BOM UTF-8', function (): void {
    // Excel mặc định chèn BOM khi lưu "CSV UTF-8". Không xử lý thì mã nhân viên
    // đầu tiên dính ký tự vô hình và mọi lần nhập sau đều tạo bản ghi trùng.
    $file = csvTam(<<<'CSV'
        employee_code,name,email,phone,department_code,position_code,manager_email,joined_at
        NV002,Trần Thị Bích Ngọc,ngoc@congty.vn,,SALE,NV,,2025-03-01
        CSV, themBom: true);

    $this->artisan('users:import', ['file' => $file])->assertSuccessful();

    expect(User::query()->where('employee_code', 'NV002')->value('name'))
        ->toBe('Trần Thị Bích Ngọc');
});

it('cập nhật nhân viên đã có theo mã thay vì tạo bản ghi trùng', function (): void {
    User::factory()->create([
        'employee_code' => 'NV003',
        'name' => 'Tên cũ',
        'email' => 'cu@congty.vn',
    ]);

    $file = csvTam(<<<'CSV'
        employee_code,name,email,phone,department_code,position_code,manager_email,joined_at
        NV003,Tên mới,moi@congty.vn,,SALE,NV,,2025-01-01
        CSV);

    $this->artisan('users:import', ['file' => $file])->assertSuccessful();

    expect(User::query()->where('employee_code', 'NV003')->count())->toBe(1)
        ->and(User::query()->where('employee_code', 'NV003')->value('name'))->toBe('Tên mới');
});

it('gán quản lý trực tiếp theo email', function (): void {
    $sep = User::factory()->create(['email' => 'sep@congty.vn']);

    $file = csvTam(<<<'CSV'
        employee_code,name,email,phone,department_code,position_code,manager_email,joined_at
        NV004,Nhân viên,nv004@congty.vn,,SALE,NV,sep@congty.vn,2025-01-01
        CSV);

    $this->artisan('users:import', ['file' => $file])->assertSuccessful();

    expect(User::query()->where('employee_code', 'NV004')->value('manager_id'))->toBe($sep->id);
});

it('bỏ qua dòng thiếu dữ liệu bắt buộc và báo lại số dòng lỗi', function (): void {
    $file = csvTam(<<<'CSV'
        employee_code,name,email,phone,department_code,position_code,manager_email,joined_at
        NV005,Hợp lệ,hople@congty.vn,,SALE,NV,,2025-01-01
        ,Thiếu mã,thieuma@congty.vn,,SALE,NV,,2025-01-01
        NV006,Thiếu email,,,SALE,NV,,2025-01-01
        CSV);

    $this->artisan('users:import', ['file' => $file])
        ->expectsOutputToContain('Bỏ qua: 2')
        ->assertSuccessful();

    expect(User::query()->whereIn('employee_code', ['NV005', 'NV006'])->count())->toBe(1);
});

it('không ghi gì vào database khi chạy thử với --dry-run', function (): void {
    $truoc = User::query()->count();

    $file = csvTam(<<<'CSV'
        employee_code,name,email,phone,department_code,position_code,manager_email,joined_at
        NV007,Chạy thử,thu@congty.vn,,SALE,NV,,2025-01-01
        CSV);

    $this->artisan('users:import', ['file' => $file, '--dry-run' => true])->assertSuccessful();

    expect(User::query()->count())->toBe($truoc);
});

it('báo lỗi rõ ràng khi file không tồn tại', function (): void {
    $this->artisan('users:import', ['file' => '/khong/co/that.csv'])->assertFailed();
});
