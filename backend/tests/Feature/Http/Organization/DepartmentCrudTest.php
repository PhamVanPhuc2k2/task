<?php

declare(strict_types=1);

use App\Domain\Identity\Models\Department;
use App\Domain\Identity\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->withHeader('Origin', 'http://localhost:3000');
});

/*
|--------------------------------------------------------------------------
| Ai được sửa cơ cấu tổ chức
|--------------------------------------------------------------------------
|
| Cây phòng ban quyết định ai nhìn thấy dữ liệu của ai — `subtreeIds()` đỡ 13
| chỗ phân quyền. Nên ranh giới ở đây quan trọng hơn vẻ ngoài của nó: người tự
| sửa được cây là người tự mở rộng được tầm nhìn của chính mình.
*/

it('trưởng phòng KHÔNG sửa được cây phòng ban', function (): void {
    // Đây là test quan trọng nhất file này. Trưởng phòng nhìn phòng mình và
    // mọi phòng bên dưới; cho họ sửa cây là cho họ tự nối thêm nhánh vào phạm
    // vi của mình — gồm cả bảng công và đơn nghỉ của người khác phòng.
    [$sep] = sepVaNhanVien();

    $this->actingAs($sep)
        ->postJson('/api/v1/departments', ['name' => 'Phòng tự lập'])
        ->assertForbidden();
});

it('nhân viên không sửa được', function (): void {
    [, $nhanVien] = sepVaNhanVien();

    $phong = Department::factory()->create();

    $this->actingAs($nhanVien)
        ->putJson("/api/v1/departments/{$phong->uuid}", ['name' => 'Đổi tên'])
        ->assertForbidden();

    $this->actingAs($nhanVien)
        ->deleteJson("/api/v1/departments/{$phong->uuid}")
        ->assertForbidden();
});

it('giám đốc tạo được phòng ban', function (): void {
    $gd = giamDoc();

    $phanHoi = $this->actingAs($gd)
        ->postJson('/api/v1/departments', [
            'name' => 'Phòng Chăm sóc khách hàng',
            'code' => 'CSKH',
            'description' => 'Trực hotline và xử lý khiếu nại',
        ])
        ->assertCreated();

    expect($phanHoi->json('data.name'))->toBe('Phòng Chăm sóc khách hàng')
        ->and($phanHoi->json('data.code'))->toBe('CSKH')
        ->and($phanHoi->json('data.is_active'))->toBeTrue()
        // Lộ uuid, không lộ id tuần tự — quy ước dữ liệu của dự án.
        ->and($phanHoi->json('data.id'))->not->toBeNumeric();
});

/*
|--------------------------------------------------------------------------
| Vòng trong cây
|--------------------------------------------------------------------------
|
| Một vòng làm `descendantIds()` chạy mãi không dừng: request treo tới hết
| timeout, log không có dòng nào, và vì hàm đó đỡ 13 chỗ nên chấm công, nghỉ
| phép và báo cáo chết cùng lúc.
*/

it('không cho một phòng ban làm cấp trên của chính nó', function (): void {
    $gd = giamDoc();
    $phong = Department::factory()->create();

    $this->actingAs($gd)
        ->putJson("/api/v1/departments/{$phong->uuid}", [
            'name' => $phong->name,
            'parent_id' => $phong->uuid,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('parent_id');
});

it('không cho chuyển một phòng ban xuống dưới cấp dưới của nó', function (): void {
    $gd = giamDoc();

    $cha = Department::factory()->create(['name' => 'Khối Kinh doanh']);
    $con = Department::factory()->create(['parent_id' => $cha->id]);
    $chau = Department::factory()->create(['parent_id' => $con->id, 'name' => 'Tổ Bán lẻ']);

    // Vòng gián tiếp qua hai cấp — đây là dạng mà kiểm "cha khác chính nó"
    // không bắt được.
    $phanHoi = $this->actingAs($gd)
        ->putJson("/api/v1/departments/{$cha->uuid}", [
            'name' => $cha->name,
            'parent_id' => $chau->uuid,
        ])
        ->assertStatus(422);

    expect($phanHoi->json('code'))->toBe('DEPARTMENT_CYCLE')
        ->and($cha->refresh()->parent_id)->toBeNull();
});

it('chuyển được sang một nhánh khác không tạo vòng', function (): void {
    $gd = giamDoc();

    $khoiA = Department::factory()->create();
    $khoiB = Department::factory()->create();
    $to = Department::factory()->create(['parent_id' => $khoiA->id]);

    $this->actingAs($gd)
        ->putJson("/api/v1/departments/{$to->uuid}", [
            'name' => $to->name,
            'parent_id' => $khoiB->uuid,
        ])
        ->assertOk();

    expect($to->refresh()->parent_id)->toBe($khoiB->id);
});

it('chuyển được lên làm phòng ban gốc', function (): void {
    $gd = giamDoc();

    $cha = Department::factory()->create();
    $con = Department::factory()->create(['parent_id' => $cha->id]);

    $this->actingAs($gd)
        ->putJson("/api/v1/departments/{$con->uuid}", [
            'name' => $con->name,
            'parent_id' => null,
        ])
        ->assertOk();

    expect($con->refresh()->parent_id)->toBeNull();
});

/*
|--------------------------------------------------------------------------
| Xoá
|--------------------------------------------------------------------------
|
| Phòng ban dùng xoá mềm, nên `restrictOnDelete` và `nullOnDelete` trong
| migration đều KHÔNG có hiệu lực — với database thì không có gì bị xoá cả.
| Hai test dưới đây khoá phần chặn ở tầng ứng dụng.
*/

it('không xoá được phòng ban còn phòng ban con', function (): void {
    $gd = giamDoc();

    $cha = Department::factory()->create();
    Department::factory()->create(['parent_id' => $cha->id]);

    $phanHoi = $this->actingAs($gd)
        ->deleteJson("/api/v1/departments/{$cha->uuid}")
        ->assertStatus(422);

    expect($phanHoi->json('code'))->toBe('DEPARTMENT_HAS_CHILDREN')
        ->and(Department::query()->whereKey($cha->id)->exists())->toBeTrue();
});

it('không xoá được phòng ban còn nhân sự', function (): void {
    // Xoá mềm để lại nhân sự trỏ vào một phòng ban mà mọi truy vấn lọc mất:
    // họ rơi khỏi bảng công và báo cáo của cấp trên, không có lỗi nào.
    $gd = giamDoc();

    $phong = Department::factory()->create();
    User::factory()->create(['department_id' => $phong->id]);

    $phanHoi = $this->actingAs($gd)
        ->deleteJson("/api/v1/departments/{$phong->uuid}")
        ->assertStatus(422);

    expect($phanHoi->json('code'))->toBe('DEPARTMENT_HAS_USERS');
});

it('chặn xoá kể cả khi nhân sự đã nghỉ việc', function (): void {
    // Người đã nghỉ vẫn giữ `department_id` để bảng công và lương cũ còn đọc
    // được theo phòng ban. Bỏ qua họ thì xoá phòng ban làm hỏng đúng phần lịch
    // sử đó.
    $gd = giamDoc();

    $phong = Department::factory()->create();
    User::factory()->create(['department_id' => $phong->id, 'is_active' => false]);

    $this->actingAs($gd)
        ->deleteJson("/api/v1/departments/{$phong->uuid}")
        ->assertStatus(422);
});

it('xoá được phòng ban trống', function (): void {
    $gd = giamDoc();
    $phong = Department::factory()->create();

    $this->actingAs($gd)
        ->deleteJson("/api/v1/departments/{$phong->uuid}")
        ->assertNoContent();

    expect(Department::query()->whereKey($phong->id)->exists())->toBeFalse()
        // Xoá MỀM: cây tổ chức cũ vẫn tra ngược được từ dữ liệu lịch sử.
        ->and(Department::withTrashed()->whereKey($phong->id)->first()?->trashed())->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| Danh sách
|--------------------------------------------------------------------------
*/

it('mặc định chỉ trả phòng ban đang hoạt động', function (): void {
    [, $nhanVien] = sepVaNhanVien();

    Department::factory()->create(['name' => 'Đang chạy']);
    Department::factory()->inactive()->create(['name' => 'Đã ngừng']);

    $ten = collect($this->actingAs($nhanVien)->getJson('/api/v1/departments')->assertOk()->json('data'))
        ->pluck('name');

    expect($ten)->toContain('Đang chạy')->not->toContain('Đã ngừng');
});

it('trả cả phòng ban đã tắt khi được hỏi', function (): void {
    // Không thấy thì không bật lại được — trang quản lý cần đường này.
    $gd = giamDoc();

    Department::factory()->inactive()->create(['name' => 'Đã ngừng']);

    $ten = collect(
        $this->actingAs($gd)
            ->getJson('/api/v1/departments?include_inactive=1')
            ->assertOk()
            ->json('data'),
    )->pluck('name');

    expect($ten)->toContain('Đã ngừng');
});

it('kèm số phòng ban con và số nhân sự', function (): void {
    // Trang quản lý dùng hai con số này để nói trước "còn 2 nhân sự" thay vì
    // để người dùng bấm Xoá rồi ăn lỗi.
    [, $nhanVien] = sepVaNhanVien();

    $cha = Department::factory()->create();
    Department::factory()->create(['parent_id' => $cha->id]);
    User::factory()->count(2)->create(['department_id' => $cha->id]);

    $dong = collect($this->actingAs($nhanVien)->getJson('/api/v1/departments')->assertOk()->json('data'))
        ->firstWhere('id', $cha->uuid);

    expect($dong['child_count'])->toBe(1)
        ->and($dong['user_count'])->toBe(2);
});

it('không nhận mã phòng ban trùng', function (): void {
    $gd = giamDoc();
    Department::factory()->create(['code' => 'KD']);

    $this->actingAs($gd)
        ->postJson('/api/v1/departments', ['name' => 'Phòng khác', 'code' => 'KD'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('code');
});

it('giữ nguyên mã của chính mình khi sửa', function (): void {
    // Không có `ignore()` thì bấm Lưu mà không đổi gì cũng báo "mã đã tồn tại".
    $gd = giamDoc();
    $phong = Department::factory()->create(['code' => 'KT']);

    $this->actingAs($gd)
        ->putJson("/api/v1/departments/{$phong->uuid}", [
            'name' => 'Phòng Kỹ thuật',
            'code' => 'KT',
        ])
        ->assertOk();
});

it('mã để trống lưu thành null chứ không phải chuỗi rỗng', function (): void {
    // `code` có ràng buộc unique. Lưu chuỗi rỗng thì phòng ban thứ hai để
    // trống sẽ đụng phòng thứ nhất, và lỗi sẽ nói về "mã đã tồn tại" trong khi
    // người dùng không nhập mã nào.
    $gd = giamDoc();

    foreach (['Phòng A', 'Phòng B'] as $ten) {
        $this->actingAs($gd)
            ->postJson('/api/v1/departments', ['name' => $ten, 'code' => ''])
            ->assertCreated();
    }

    expect(Department::query()->whereNull('code')->count())->toBe(2);
});

it('trả về cùng hình dạng ở cả đường đọc lẫn đường ghi', function (): void {
    /*
    | Frontend dùng chung đúng MỘT kiểu `Department` cho cả GET lẫn POST/PUT.
    | Đường ghi thiếu một trường thì kiểu ở TypeScript nói dối — nó khai là
    | luôn có — mà TypeScript không kiểm được lời khai đó với dữ liệu thật.
    | Lỗi chỉ lộ ra khi có người render kết quả mutation và thấy `undefined`.
    */
    $gd = giamDoc();

    $khiTao = $this->actingAs($gd)
        ->postJson('/api/v1/departments', ['name' => 'Phòng Mới'])
        ->assertCreated()
        ->json('data');

    $khiDoc = collect(
        $this->actingAs($gd)->getJson('/api/v1/departments')->assertOk()->json('data'),
    )->firstWhere('id', $khiTao['id']);

    expect(array_keys($khiTao))->toEqualCanonicalizing(array_keys($khiDoc));

    $khiSua = $this->actingAs($gd)
        ->putJson("/api/v1/departments/{$khiTao['id']}", ['name' => 'Phòng Mới hơn'])
        ->assertOk()
        ->json('data');

    expect(array_keys($khiSua))->toEqualCanonicalizing(array_keys($khiDoc));
});
