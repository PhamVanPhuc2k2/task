<?php

declare(strict_types=1);

use App\Domain\Attendance\Enums\AttendanceDecision;
use App\Domain\Attendance\Models\WorkDay;
use App\Domain\Identity\Enums\Role;
use App\Domain\Identity\Models\Department;
use App\Domain\Identity\Models\User;
use App\Domain\Report\Enums\DailyReportStatus;
use App\Domain\Report\Models\DailyReport;
use Carbon\CarbonImmutable;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/*
|--------------------------------------------------------------------------
| Đối chiếu giờ công với báo cáo ngày
|--------------------------------------------------------------------------
|
| Mảnh cuối của đợt 3, chờ đợt 2 xong mới làm được. Cột `has_report` đã có từ
| trước; phần mới là biến nó thành bốn tình huống có tên, và đếm ra con số cho
| người quản lý khỏi phải rà ba mươi ô.
|
| Ngày dùng trong cả file: 11/08/2026 là thứ Ba.
|
*/

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->withHeader('Origin', 'http://localhost:3000');
    $this->travelTo(CarbonImmutable::parse('2026-08-12 09:00:00'));
});

/*
|--------------------------------------------------------------------------
| Màn của chính mình
|--------------------------------------------------------------------------
*/

it('nhân viên tự thấy ngày mình có giờ làm mà quên nộp báo cáo', function (): void {
    $u = User::factory()->create();
    $u->assignRole(Role::NhanVien->value);

    coGioLam($u, '2026-08-11', 300);

    $this->actingAs($u)
        ->getJson('/api/v1/attendance/me?month=2026-08')
        ->assertOk()
        ->assertJsonPath('data.cells.2026-08-11.report_match', 'missing_report')
        ->assertJsonPath('data.cells.2026-08-11.has_report', false)
        ->assertJsonPath('data.missing_report_days', 1);
});

it('nộp báo cáo rồi thì ngày đó sạch', function (): void {
    $u = User::factory()->create();
    $u->assignRole(Role::NhanVien->value);

    coGioLam($u, '2026-08-11', 300);
    daNopBaoCao($u, '2026-08-11');

    $this->actingAs($u)
        ->getJson('/api/v1/attendance/me?month=2026-08')
        ->assertOk()
        ->assertJsonPath('data.cells.2026-08-11.report_match', 'ok')
        ->assertJsonPath('data.cells.2026-08-11.has_report', true)
        ->assertJsonPath('data.missing_report_days', 0);
});

it('bản nháp không tính là đã báo cáo', function (): void {
    // Viết dở rồi bỏ đó thì quản lý không đọc được gì. Coi nháp là "đã báo
    // cáo" sẽ giấu đúng ngày cần nhìn.
    $u = User::factory()->create();
    $u->assignRole(Role::NhanVien->value);

    coGioLam($u, '2026-08-11', 300);

    DailyReport::query()->create([
        'user_id' => $u->id,
        'report_date' => '2026-08-11',
        'content' => 'đang viết dở',
        'status' => DailyReportStatus::Draft,
    ]);

    $this->actingAs($u)
        ->getJson('/api/v1/attendance/me?month=2026-08')
        ->assertJsonPath('data.cells.2026-08-11.report_match', 'missing_report')
        ->assertJsonPath('data.missing_report_days', 1);
});

it('quản lý đã xử lý ngày đó thì thôi đếm nữa', function (): void {
    // Cờ vẫn còn để tra lại, nhưng nó đã có người nhìn và kết luận. Không trừ
    // ra thì con số "cần xem" không bao giờ về 0 và người ta ngừng nhìn nó.
    $phong = Department::factory()->create();

    $sep = User::factory()->for($phong, 'department')->create();
    $sep->assignRole(Role::TruongPhong->value);

    $nv = User::factory()->for($phong, 'department')->create();
    $nv->assignRole(Role::NhanVien->value);

    coGioLam($nv, '2026-08-11', 300);

    WorkDay::query()->create([
        'user_id' => $nv->id,
        'work_date' => '2026-08-11',
        'decision' => AttendanceDecision::Waived->value,
        'reason' => 'Họp với khách hàng cả ngày, đã trao đổi miệng.',
        'reviewed_by' => $sep->id,
        'reviewed_at' => now(),
    ]);

    $this->actingAs($nv)
        ->getJson('/api/v1/attendance/me?month=2026-08')
        ->assertJsonPath('data.cells.2026-08-11.report_match', 'missing_report')
        ->assertJsonPath('data.missing_report_days', 0);
});

/*
|--------------------------------------------------------------------------
| Bảng của quản lý
|--------------------------------------------------------------------------
*/

it('bảng công của đội đếm số ngày thiếu báo cáo cho từng người', function (): void {
    $phong = Department::factory()->create();

    $sep = User::factory()->for($phong, 'department')->create();
    $sep->assignRole(Role::TruongPhong->value);

    $nv = User::factory()->for($phong, 'department')->create(['name' => 'Nhân viên A']);
    $nv->assignRole(Role::NhanVien->value);

    coGioLam($nv, '2026-08-10', 300);
    coGioLam($nv, '2026-08-11', 300);
    coGioLam($nv, '2026-08-12', 300);
    daNopBaoCao($nv, '2026-08-11');

    $hang = collect(
        $this->actingAs($sep)
            ->getJson('/api/v1/attendance/team?month=2026-08')
            ->assertOk()
            ->json('data.rows'),
    )->firstWhere('user.name', 'Nhân viên A');

    expect($hang['missing_report_days'])->toBe(2)
        ->and($hang['cells']['2026-08-11']['report_match'])->toBe('ok')
        ->and($hang['cells']['2026-08-10']['report_match'])->toBe('missing_report');
});

it('không lấy báo cáo của người khác nhầm sang', function (): void {
    // Khoá ghép là "userId:ngày". Ghép nhầm chỉ theo ngày thì một người nộp
    // báo cáo sẽ làm cả phòng trông như đã nộp — và không ai phát hiện ra.
    $phong = Department::factory()->create();

    $sep = User::factory()->for($phong, 'department')->create();
    $sep->assignRole(Role::TruongPhong->value);

    $a = User::factory()->for($phong, 'department')->create(['name' => 'Người A']);
    $b = User::factory()->for($phong, 'department')->create(['name' => 'Người B']);
    $a->assignRole(Role::NhanVien->value);
    $b->assignRole(Role::NhanVien->value);

    coGioLam($a, '2026-08-11', 300);
    coGioLam($b, '2026-08-11', 300);
    daNopBaoCao($a, '2026-08-11');

    $hang = collect(
        $this->actingAs($sep)->getJson('/api/v1/attendance/team?month=2026-08')->json('data.rows'),
    )->keyBy('user.name');

    expect($hang['Người A']['missing_report_days'])->toBe(0)
        ->and($hang['Người B']['missing_report_days'])->toBe(1);
});

it('ngày làm ngoài hệ thống vẫn hiện ô, miễn là có báo cáo', function (): void {
    /*
    | Lỗ hổng thật, tìm ra khi rà lại chứ không phải test viết trước: ô ngày
    | được dựng từ bảng `work_sessions`, nên người họp cả ngày rồi tối về viết
    | báo cáo sẽ KHÔNG có ô nào. Quản lý nhìn ô trắng và đọc ra "hôm đó không
    | làm gì" — ngược hẳn sự thật, và chính người chịu khó báo cáo lại là người
    | bị hiểu sai.
    */
    $u = User::factory()->create();
    $u->assignRole(Role::NhanVien->value);

    // Không có phiên làm việc nào cả — chỉ có báo cáo.
    daNopBaoCao($u, '2026-08-11');

    $this->actingAs($u)
        ->getJson('/api/v1/attendance/me?month=2026-08')
        ->assertOk()
        ->assertJsonPath('data.cells.2026-08-11.report_match', 'report_only')
        ->assertJsonPath('data.cells.2026-08-11.minutes', 0)
        ->assertJsonPath('data.cells.2026-08-11.has_report', true)
        // Ngày 0 phút không được tính vào số ngày công.
        ->assertJsonPath('data.days_worked', 0)
        ->assertJsonPath('data.missing_report_days', 0);
});

it('bảng của quản lý cũng hiện ngày chỉ có báo cáo', function (): void {
    $phong = Department::factory()->create();

    $sep = User::factory()->for($phong, 'department')->create();
    $sep->assignRole(Role::TruongPhong->value);

    $nv = User::factory()->for($phong, 'department')->create(['name' => 'Họp cả ngày']);
    $nv->assignRole(Role::NhanVien->value);

    daNopBaoCao($nv, '2026-08-11');

    $hang = collect(
        $this->actingAs($sep)->getJson('/api/v1/attendance/team?month=2026-08')->json('data.rows'),
    )->firstWhere('user.name', 'Họp cả ngày');

    expect($hang['cells'])->toHaveKey('2026-08-11')
        ->and($hang['cells']['2026-08-11']['report_match'])->toBe('report_only')
        ->and($hang['total_minutes'])->toBe(0);
});

it('giờ quá thấp mà có báo cáo thì không bị tính là bất thường', function (): void {
    $u = User::factory()->create();
    $u->assignRole(Role::NhanVien->value);

    coGioLam($u, '2026-08-11', 5);
    daNopBaoCao($u, '2026-08-11');

    $this->actingAs($u)
        ->getJson('/api/v1/attendance/me?month=2026-08')
        ->assertJsonPath('data.cells.2026-08-11.report_match', 'report_only')
        ->assertJsonPath('data.missing_report_days', 0);
});

it('không sinh thêm truy vấn nào khi số người tăng lên', function (): void {
    // Đối chiếu là chỗ dễ thành N+1 nhất của tính năng này: mỗi ô một câu
    // "ngày đó có báo cáo chưa" thì bảng ba mươi người thành chín trăm câu SQL.
    $phong = Department::factory()->create();

    $sep = User::factory()->for($phong, 'department')->create();
    $sep->assignRole(Role::TruongPhong->value);

    $sepId = $sep->id;

    $dem = function () use ($sepId): int {
        /*
        | Hai thứ phải đặt lại trước mỗi lần đo, nếu không phép đo này đo nhầm
        | thứ khác. Bản đầu tôi viết thiếu cả hai và nó ra 13 rồi 9 — số truy
        | vấn *giảm* khi thêm người, thứ không thể xảy ra nếu có N+1 thật. Dump
        | từng câu SQL mới thấy: chênh lệch nằm ở đối tượng $sep giữ sẵn vai trò
        | và phòng ban trong bộ nhớ từ lần trước.
        |
        |   - Đệm quyền của spatie: lần đo đầu tốn thêm truy vấn nạp vai trò.
        |   - Đối tượng người dùng: phải lấy bản mới, để lần nào cũng nạp lại
        |     quan hệ đúng như một request thật của một tiến trình mới.
        */
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        /** @var User $actor */
        $actor = User::query()->findOrFail($sepId);

        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->actingAs($actor)->getJson('/api/v1/attendance/team?month=2026-08')->assertOk();

        $so = count(DB::getRawQueryLog());
        DB::disableQueryLog();

        return $so;
    };

    $motNguoi = $dem();

    for ($i = 0; $i < 5; $i++) {
        $nv = User::factory()->for($phong, 'department')->create();
        $nv->assignRole(Role::NhanVien->value);
        coGioLam($nv, '2026-08-11', 300);
        daNopBaoCao($nv, '2026-08-10');
    }

    expect($dem())->toBe($motNguoi);
});
