<?php

declare(strict_types=1);

use App\Domain\Identity\Enums\Role;
use App\Domain\Identity\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/*
|--------------------------------------------------------------------------
| Chi phí của một nhịp tim
|--------------------------------------------------------------------------
|
| Đây là đường được gọi nhiều nhất cả hệ thống: hai trăm nhân sự × tám tiếng ≈
| 96.000 lượt mỗi ngày. Mỗi truy vấn thừa ở đây nhân lên chín mươi sáu nghìn
| lần.
|
| Test khoá con số lại. Ai thêm một truy vấn vào đường này sẽ thấy test đỏ và
| phải quyết định có đáng hay không — thay vì để nó trôi vào production rồi sáu
| tháng sau mới có người đi tìm vì sao database bận.
|
*/

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->withHeader('Origin', 'http://localhost:3000');
});

function demTruyVanNhip(User $u): int
{
    // Đệm quyền của spatie và trạng thái model trong bộ nhớ đều làm sai phép
    // đo — xem ReportReconciliationTest để biết lần trước đã đo nhầm thế nào.
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    /** @var User $actor */
    $actor = User::query()->findOrFail($u->id);

    DB::flushQueryLog();
    DB::enableQueryLog();

    test()->actingAs($actor)->postJson('/api/v1/attendance/heartbeat')->assertOk();

    $so = count(DB::getRawQueryLog());
    DB::disableQueryLog();

    return $so;
}

it('một nhịp tim tốn đúng 3 truy vấn', function (): void {
    /*
    | Ba truy vấn đó là:
    |   1. tìm phiên làm việc gần nhất
    |   2. ghi phiên (nối dài phiên cũ hoặc mở phiên mới)
    |   3. tổng hợp số phút hôm nay để trả về cho giao diện
    |
    | Trước khi tối ưu là SÁU: ba truy vấn đầu là nạp vai trò và quyền, mà
    | endpoint này không kiểm quyền nào cả. Xem EnsureUserIsActive.
    */
    $u = User::factory()->create();
    $u->assignRole(Role::NhanVien->value);

    // Nhịp đầu mở phiên mới; đo nhịp thứ hai vì đó mới là trường hợp thường
    // gặp — 479 trong 480 nhịp của một ngày làm việc là nối dài phiên đang mở.
    nhip($u);

    expect(demTruyVanNhip($u))->toBe(3);
});

it('không nạp vai trò và quyền trên đường nhịp tim', function (): void {
    // Kiểm thẳng vào thứ đã bỏ đi, không chỉ đếm tổng: nếu ai đó bỏ ba truy vấn
    // khác rồi thêm lại phần nạp quyền thì tổng vẫn là 3 mà ý định đã mất.
    $u = User::factory()->create();
    $u->assignRole(Role::NhanVien->value);
    nhip($u);

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    /** @var User $actor */
    $actor = User::query()->findOrFail($u->id);

    DB::flushQueryLog();
    DB::enableQueryLog();
    test()->actingAs($actor)->postJson('/api/v1/attendance/heartbeat');
    $sql = collect(DB::getRawQueryLog())->pluck('raw_query')->implode(' | ');
    DB::disableQueryLog();

    expect($sql)->not->toContain('model_has_roles')
        ->and($sql)->not->toContain('role_has_permissions');
});

it('các đường KHÁC vẫn nạp quyền như cũ', function (): void {
    /*
    | Mặc định phải là CÓ nạp. Quên khai thì chỉ tốn ba truy vấn; nếu mặc định
    | là không nạp thì quên khai là ăn N+1 âm thầm, hoặc lỗi preventLazyLoading
    | ở một endpoint bất kỳ. Sai theo hướng an toàn.
    */
    $u = quanTri();

    DB::flushQueryLog();
    DB::enableQueryLog();
    $this->actingAs($u)->getJson('/api/v1/auth/me')->assertOk();
    $sql = collect(DB::getRawQueryLog())->pluck('raw_query')->implode(' | ');
    DB::disableQueryLog();

    expect($sql)->toContain('model_has_roles');
});
