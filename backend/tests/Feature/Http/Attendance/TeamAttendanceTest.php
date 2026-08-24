<?php

declare(strict_types=1);

use App\Domain\Attendance\Enums\AttendanceDecision;
use App\Domain\Attendance\Models\Holiday;
use App\Domain\Attendance\Models\WorkDay;
use App\Domain\Identity\Enums\Role;
use App\Domain\Identity\Models\Department;
use App\Domain\Identity\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->withHeader('Origin', 'http://localhost:3000');
    $this->travelTo(CarbonImmutable::parse('2026-08-12 09:00:00'));
});

/*
|--------------------------------------------------------------------------
| Phạm vi xem
|--------------------------------------------------------------------------
*/

it('trưởng phòng chỉ thấy giờ làm của phòng mình và phòng trực thuộc', function (): void {
    $phongCha = Department::factory()->create();
    $phongCon = Department::factory()->create(['parent_id' => $phongCha->id]);
    $phongKhac = Department::factory()->create();

    $sep = User::factory()->for($phongCha, 'department')->create(['name' => 'Sếp']);
    $sep->assignRole(Role::TruongPhong->value);

    $trongPhong = User::factory()->for($phongCha, 'department')->create(['name' => 'Trong phòng']);
    $phongDuoi = User::factory()->for($phongCon, 'department')->create(['name' => 'Phòng dưới']);
    $ngoaiPhong = User::factory()->for($phongKhac, 'department')->create(['name' => 'Ngoài phòng']);

    foreach ([$trongPhong, $phongDuoi, $ngoaiPhong] as $u) {
        $u->assignRole(Role::NhanVien->value);
    }

    $ten = collect(
        $this->actingAs($sep)
            ->getJson('/api/v1/attendance/team?month=2026-08')
            ->assertOk()
            ->json('data.rows'),
    )->pluck('user.name')->all();

    expect($ten)->toContain('Trong phòng', 'Phòng dưới', 'Sếp')
        ->and($ten)->not->toContain('Ngoài phòng');
});

it('giám đốc thấy toàn công ty', function (): void {
    $gd = User::factory()->for(Department::factory(), 'department')->create();
    $gd->assignRole(Role::GiamDoc->value);

    $nguoiKhac = User::factory()->for(Department::factory(), 'department')->create();
    $nguoiKhac->assignRole(Role::NhanVien->value);

    $ten = collect(
        $this->actingAs($gd)->getJson('/api/v1/attendance/team')->assertOk()->json('data.rows'),
    )->pluck('user.name')->all();

    expect($ten)->toContain($nguoiKhac->name);
});

it('nhân viên thường không vào được bảng công của đội', function (): void {
    $nv = User::factory()->for(Department::factory(), 'department')->create();
    $nv->assignRole(Role::NhanVien->value);

    $this->actingAs($nv)->getJson('/api/v1/attendance/team')->assertForbidden();
});

it('không hiện người đã nghỉ việc trong bảng công', function (): void {
    $phong = Department::factory()->create();

    $sep = User::factory()->for($phong, 'department')->create();
    $sep->assignRole(Role::TruongPhong->value);

    $daNghi = User::factory()->for($phong, 'department')
        ->create(['name' => 'Đã nghỉ', 'is_active' => false]);
    $daNghi->assignRole(Role::NhanVien->value);

    $ten = collect(
        $this->actingAs($sep)->getJson('/api/v1/attendance/team')->json('data.rows'),
    )->pluck('user.name')->all();

    expect($ten)->not->toContain('Đã nghỉ');
});

/*
|--------------------------------------------------------------------------
| Duyệt ngày công
|--------------------------------------------------------------------------
*/

it('trưởng phòng bỏ qua được một ngày công, kèm lý do', function (): void {
    $phong = Department::factory()->create();

    $sep = User::factory()->for($phong, 'department')->create();
    $sep->assignRole(Role::TruongPhong->value);

    $nv = User::factory()->for($phong, 'department')->create();
    $nv->assignRole(Role::NhanVien->value);

    $this->actingAs($sep)
        ->postJson("/api/v1/attendance/{$nv->uuid}/review", [
            'work_date' => '2026-08-11',
            'decision' => AttendanceDecision::Waived->value,
            'reason' => 'Họp với khách hàng cả ngày, không dùng hệ thống.',
            'adjusted_minutes' => 480,
        ])
        ->assertOk()
        ->assertJsonPath('data.decision', 'waived')
        ->assertJsonPath('data.decision_label', 'Bỏ qua')
        ->assertJsonPath('data.adjusted_minutes', 480)
        ->assertJsonPath('data.reviewed_by', $sep->name);

    $quyet = WorkDay::query()->where('user_id', $nv->id)->sole();

    expect($quyet->reviewed_by)->toBe($sep->id)
        ->and($quyet->work_date)->toBe('2026-08-11');
});

it('bắt buộc phải ghi lý do', function (): void {
    // Một quyết định không lý do thì sáu tháng sau vô dụng ngang với không ghi
    // gì — mà đây đúng là loại quyết định sinh tranh cãi muộn.
    $phong = Department::factory()->create();
    $sep = User::factory()->for($phong, 'department')->create();
    $sep->assignRole(Role::TruongPhong->value);
    $nv = User::factory()->for($phong, 'department')->create();

    $this->actingAs($sep)
        ->postJson("/api/v1/attendance/{$nv->uuid}/review", [
            'work_date' => '2026-08-11',
            'decision' => AttendanceDecision::Waived->value,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('reason');
});

it('lý do quá ngắn cũng bị chặn', function (): void {
    // Không có mức tối thiểu thì trường này đầy những dòng "ok" và "x" — vẫn
    // không ai trả lời được vì sao, mà lại tưởng là đã ghi.
    $phong = Department::factory()->create();
    $sep = User::factory()->for($phong, 'department')->create();
    $sep->assignRole(Role::TruongPhong->value);
    $nv = User::factory()->for($phong, 'department')->create();

    $this->actingAs($sep)
        ->postJson("/api/v1/attendance/{$nv->uuid}/review", [
            'work_date' => '2026-08-11',
            'decision' => AttendanceDecision::Waived->value,
            'reason' => 'ok',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('reason');
});

it('không tự duyệt ngày công của chính mình', function (): void {
    $sep = User::factory()->for(Department::factory(), 'department')->create();
    $sep->assignRole(Role::TruongPhong->value);

    $this->actingAs($sep)
        ->postJson("/api/v1/attendance/{$sep->uuid}/review", [
            'work_date' => '2026-08-11',
            'decision' => AttendanceDecision::Waived->value,
            'reason' => 'Hôm đó tôi họp cả ngày ở ngoài.',
        ])
        ->assertForbidden();

    expect(WorkDay::query()->count())->toBe(0);
});

it('không duyệt được người ngoài phạm vi quản lý', function (): void {
    $sep = User::factory()->for(Department::factory(), 'department')->create();
    $sep->assignRole(Role::TruongPhong->value);

    $nguoiKhac = User::factory()->for(Department::factory(), 'department')->create();

    $this->actingAs($sep)
        ->postJson("/api/v1/attendance/{$nguoiKhac->uuid}/review", [
            'work_date' => '2026-08-11',
            'decision' => AttendanceDecision::Waived->value,
            'reason' => 'Không thuộc quyền quản lý của tôi.',
        ])
        ->assertForbidden();
});

it('nhân viên thường không duyệt được ngày công của ai', function (): void {
    $phong = Department::factory()->create();
    $nv = User::factory()->for($phong, 'department')->create();
    $nv->assignRole(Role::NhanVien->value);
    $dongNghiep = User::factory()->for($phong, 'department')->create();

    $this->actingAs($nv)
        ->postJson("/api/v1/attendance/{$dongNghiep->uuid}/review", [
            'work_date' => '2026-08-11',
            'decision' => AttendanceDecision::Confirmed->value,
            'reason' => 'Tôi thấy bạn ấy làm cả ngày.',
        ])
        ->assertForbidden();
});

it('duyệt lần hai ghi đè lần đầu, không tạo dòng thứ hai', function (): void {
    $phong = Department::factory()->create();
    $sep = User::factory()->for($phong, 'department')->create();
    $sep->assignRole(Role::TruongPhong->value);
    $nv = User::factory()->for($phong, 'department')->create();

    foreach (['Lần đầu tôi hiểu nhầm.', 'Đã xác minh lại với nhân viên.'] as $ly) {
        $this->actingAs($sep)
            ->postJson("/api/v1/attendance/{$nv->uuid}/review", [
                'work_date' => '2026-08-11',
                'decision' => AttendanceDecision::Confirmed->value,
                'reason' => $ly,
            ])
            ->assertOk();
    }

    $quyet = WorkDay::query()->where('user_id', $nv->id)->sole();

    expect($quyet->reason)->toBe('Đã xác minh lại với nhân viên.');
});

it('chỉ "bỏ qua" mới được ấn định số phút', function (): void {
    // "Ghi nhận" thì số của hệ thống là số đúng; "cần hỏi lại" thì chưa kết
    // luận gì nên chưa có số nào để ấn định.
    $phong = Department::factory()->create();
    $sep = User::factory()->for($phong, 'department')->create();
    $sep->assignRole(Role::TruongPhong->value);
    $nv = User::factory()->for($phong, 'department')->create();

    $this->actingAs($sep)
        ->postJson("/api/v1/attendance/{$nv->uuid}/review", [
            'work_date' => '2026-08-11',
            'decision' => AttendanceDecision::Confirmed->value,
            'reason' => 'Số hệ thống đo được là đúng.',
            'adjusted_minutes' => 999,
        ])
        ->assertOk()
        ->assertJsonPath('data.adjusted_minutes', null);
});

/*
|--------------------------------------------------------------------------
| Ngày có quyết định nhưng không có phiên nào
|--------------------------------------------------------------------------
*/

it('ngày được bỏ qua vẫn hiện trong bảng dù không có phiên làm việc nào', function (): void {
    // Đây là trường hợp hay gặp nhất: người họp cả ngày ngoài công ty, không
    // đụng vào hệ thống, quản lý bấm "bỏ qua". Bỏ sót thì ngày đó biến mất
    // khỏi bảng và trông như chưa ai xử lý.
    $phong = Department::factory()->create();
    $sep = User::factory()->for($phong, 'department')->create();
    $sep->assignRole(Role::TruongPhong->value);
    $nv = User::factory()->for($phong, 'department')->create(['name' => 'Người họp']);
    $nv->assignRole(Role::NhanVien->value);

    $this->actingAs($sep)->postJson("/api/v1/attendance/{$nv->uuid}/review", [
        'work_date' => '2026-08-11',
        'decision' => AttendanceDecision::Waived->value,
        'reason' => 'Đi công tác, không mở máy.',
        'adjusted_minutes' => 480,
    ])->assertOk();

    $rows = collect(
        $this->actingAs($sep)->getJson('/api/v1/attendance/team?month=2026-08')->json('data.rows'),
    );

    $dong = $rows->firstWhere('user.name', 'Người họp');

    expect($dong['cells']['2026-08-11']['minutes'])->toBe(480)
        ->and($dong['cells']['2026-08-11']['measured_minutes'])->toBe(0)
        ->and($dong['cells']['2026-08-11']['decision'])->toBe('waived')
        ->and($dong['total_minutes'])->toBe(480);
});

it('trả cells và holidays dạng object kể cả khi rỗng', function (): void {
    // Mảng PHP rỗng được json_encode thành `[]`, nên người chưa có ngày công
    // nào sẽ trả về một MẢNG trong khi mọi người khác trả về object. Client tra
    // `cells["2026-08-01"]` trên mảng thì im lặng ra undefined — không lỗi, chỉ
    // sai. Phát hiện khi chạy thật qua curl, test ban đầu không bắt được vì
    // `assertJsonPath` không phân biệt hai kiểu.
    $gd = User::factory()->for(Department::factory(), 'department')->create();
    $gd->assignRole(Role::GiamDoc->value);

    $raw = $this->actingAs($gd)
        ->getJson('/api/v1/attendance/team?month=2026-08')
        ->assertOk()
        ->getContent();

    $data = json_decode((string) $raw, associative: false)->data;

    expect($data->holidays)->toBeObject()
        ->and($data->rows[0]->cells)->toBeObject();

    $cuaToi = $this->actingAs($gd)
        ->getJson('/api/v1/attendance/me?month=2026-08')
        ->getContent();

    expect(json_decode((string) $cuaToi, associative: false)->data->cells)
        ->toBeObject();
});

/*
|--------------------------------------------------------------------------
| Ngày lễ
|--------------------------------------------------------------------------
*/

it('trả ngày lễ theo ngày thực nghỉ, không theo ngày lễ trên giấy', function (): void {
    // Điều 112 Bộ luật Lao động 2019: lễ trùng ngày nghỉ hằng tuần thì nghỉ bù
    // sang ngày kế tiếp. Bảng công phải đếm theo ngày người ta thật sự nghỉ.
    Holiday::query()->create([
        'date' => '2026-08-02',
        'observed_date' => '2026-08-03',
        'name' => 'Ngày lễ trùng chủ nhật',
    ]);

    $gd = User::factory()->for(Department::factory(), 'department')->create();
    $gd->assignRole(Role::GiamDoc->value);

    $le = $this->actingAs($gd)
        ->getJson('/api/v1/attendance/team?month=2026-08')
        ->json('data.holidays');

    expect($le)->toHaveKey('2026-08-03')
        ->and($le)->not->toHaveKey('2026-08-02');
});

/*
|--------------------------------------------------------------------------
| Hiệu năng
|--------------------------------------------------------------------------
*/

it('số truy vấn của bảng công không tăng theo số nhân sự', function (): void {
    // "Với mỗi người, cộng giờ của họ" là công thức chuẩn để sinh N+1 — với ba
    // mươi nhân sự là ba mươi lượt truy vấn cho một màn hình.
    $phong = Department::factory()->create();

    $dem = function (int $soNguoi) use ($phong): int {
        User::query()->whereNot('id', 1)->forceDelete();

        $gd = User::factory()->for($phong, 'department')->create();
        $gd->assignRole(Role::GiamDoc->value);

        foreach (range(1, $soNguoi) as $i) {
            User::factory()->for($phong, 'department')->create();
        }

        // Xoá đệm quyền trước mỗi lần đo: lần sau chạy trên đệm còn ấm sẽ tốn
        // ít hơn hai truy vấn vì lý do chẳng liên quan tới thứ đang đo.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->actingAs($gd)->getJson('/api/v1/attendance/team?month=2026-08')->assertOk();

        $so = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $so;
    };

    expect($dem(20))->toBe($dem(2));
});
