<?php

declare(strict_types=1);

use App\Domain\Attendance\Enums\AttendanceDecision;
use App\Domain\Identity\Enums\Role;
use App\Domain\Identity\Models\Department;
use App\Domain\Identity\Models\User;
use App\Domain\Report\Actions\SaveDailyReportAction;
use App\Domain\Report\Models\DailyReport;
use App\Support\Exceptions\ReportDateOutOfWindowException;
use Carbon\CarbonImmutable;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Http\JsonResponse;
use Illuminate\Testing\TestResponse;

/*
|--------------------------------------------------------------------------
| Khoảng ngày được nộp báo cáo
|--------------------------------------------------------------------------
|
| Trước khi có bộ này, `date_format:Y-m-d` là toàn bộ luật: gọi API nộp được
| báo cáo cho năm 2027, và nộp bù cả tháng trước bằng vài request. Giao diện đã
| có `max={hôm nay}` từ đầu — ý định vốn có sẵn, chỉ thiếu ở chỗ có hiệu lực.
|
| Mốc thời gian: 12/08/2026 09:00 UTC = 16:00 giờ Việt Nam. Cửa sổ mặc định là
| hôm nay + 2 ngày, tức 10/08 → 12/08.
|
*/

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->withHeader('Origin', 'http://localhost:3000');
    $this->travelTo(CarbonImmutable::parse('2026-08-12 09:00:00'));
});

/** @return TestResponse<JsonResponse> */
function nopBaoCao(User $u, string $ngay): TestResponse
{
    return test()->actingAs($u)->postJson('/api/v1/reports', [
        'report_date' => $ngay,
        'content' => 'Hoàn thành phần đăng nhập, chiều họp với khách hàng.',
        'task_ids' => [],
        'submit' => true,
    ]);
}

function nhanVienBaoCao(): User
{
    $u = User::factory()->create();
    $u->assignRole(Role::NhanVien->value);

    return $u;
}

it('nộp được cho hôm nay', function (): void {
    nopBaoCao(nhanVienBaoCao(), '2026-08-12')->assertSuccessful();
});

it('nộp bù được đúng hai ngày trước', function (): void {
    $u = nhanVienBaoCao();

    nopBaoCao($u, '2026-08-11')->assertSuccessful();
    nopBaoCao($u, '2026-08-10')->assertSuccessful();
});

it('không nộp bù được ngày thứ ba trở đi', function (): void {
    nopBaoCao(nhanVienBaoCao(), '2026-08-09')
        ->assertStatus(422)
        ->assertJsonPath('errors.report_date.0', fn (string $m): bool => str_contains($m, '10/08/2026'));
});

it('không nộp được cho ngày mai', function (): void {
    nopBaoCao(nhanVienBaoCao(), '2026-08-13')->assertStatus(422);
});

it('không nộp được cho một năm sau', function (): void {
    // Đây là request đã chạy được trước khi có bộ test này.
    nopBaoCao(nhanVienBaoCao(), '2027-12-31')->assertStatus(422);

    expect(DailyReport::query()->count())->toBe(0);
});

it('không nộp bù được cả tháng trước bằng vòng lặp', function (): void {
    $u = nhanVienBaoCao();

    foreach (['2026-07-01', '2026-07-02', '2026-07-03'] as $ngay) {
        nopBaoCao($u, $ngay)->assertStatus(422);
    }

    expect(DailyReport::query()->count())->toBe(0);
});

it('Action tự chặn, không chỉ dựa vào FormRequest', function (): void {
    /*
    | Luật này là chính sách nghiệp vụ, không phải luật định dạng dữ liệu. Chỉ
    | chặn ở tầng nhận request thì bất kỳ đường nào khác gọi tới Action sau này
    | — lệnh nhập liệu, job đồng bộ — đều đi vòng qua được mà không ai nhận ra.
    */
    $action = app(SaveDailyReportAction::class);

    expect(fn () => $action->execute(
        nguoiViet: nhanVienBaoCao(),
        reportDate: '2027-12-31',
        content: 'Đi vòng qua tầng validate.',
        taskIds: [],
        nop: true,
    ))->toThrow(ReportDateOutOfWindowException::class);
});

it('cửa sổ đổi theo cấu hình', function (): void {
    config()->set('reports.backfill_days', 0);

    $u = nhanVienBaoCao();

    nopBaoCao($u, '2026-08-12')->assertSuccessful();
    nopBaoCao($u, '2026-08-11')->assertStatus(422);
});

it('API nói ra khoảng ngày cho giao diện', function (): void {
    // Frontend không được tự tính từ đồng hồ máy người dùng: máy lệch giờ, hoặc
    // nhân viên đang ở múi giờ khác, là ô soạn mở cho ngày mà API sẽ từ chối.
    $this->actingAs(nhanVienBaoCao())
        ->getJson('/api/v1/reports/me?month=2026-08')
        ->assertOk()
        ->assertJsonPath('data.window.earliest', '2026-08-10')
        ->assertJsonPath('data.window.latest', '2026-08-12');
});

it('ranh giới ngày tính theo giờ Việt Nam, không theo UTC', function (): void {
    /*
    | 01:00 giờ Việt Nam ngày 13/08 = 18:00 UTC ngày 12/08. Dùng `today` của
    | Laravel thì hôm nay vẫn là 12/08, và người làm ca sáng sớm sẽ KHÔNG nộp
    | được báo cáo của chính ngày họ đang làm.
    */
    $this->travelTo(CarbonImmutable::parse('2026-08-12 18:00:00'));

    nopBaoCao(nhanVienBaoCao(), '2026-08-13')->assertSuccessful();
});

/*
|--------------------------------------------------------------------------
| Duyệt ngày công — chỉ chặn phía tương lai
|--------------------------------------------------------------------------
*/

it('không duyệt được ngày công chưa tới', function (): void {
    $phong = Department::factory()->create();

    $sep = User::factory()->for($phong, 'department')->create();
    $sep->assignRole(Role::TruongPhong->value);

    $nv = User::factory()->for($phong, 'department')->create();
    $nv->assignRole(Role::NhanVien->value);

    $this->actingAs($sep)
        ->postJson("/api/v1/attendance/{$nv->uuid}/review", [
            'work_date' => '2026-09-01',
            'decision' => AttendanceDecision::Confirmed->value,
            'reason' => 'Duyệt trước cho một ngày chưa xảy ra.',
        ])
        ->assertStatus(422)
        ->assertJsonPath('errors.work_date.0', 'Không duyệt được ngày chưa tới.');
});

it('vẫn duyệt được bảng công tháng trước', function (): void {
    // Phía quá khứ cố ý để mở: quản lý xử lý bảng công tháng trước là việc bình
    // thường, và chốt kỳ công mới là thứ khoá lại — để đợt 4.
    $phong = Department::factory()->create();

    $sep = User::factory()->for($phong, 'department')->create();
    $sep->assignRole(Role::TruongPhong->value);

    $nv = User::factory()->for($phong, 'department')->create();
    $nv->assignRole(Role::NhanVien->value);

    $this->actingAs($sep)
        ->postJson("/api/v1/attendance/{$nv->uuid}/review", [
            'work_date' => '2026-07-15',
            'decision' => AttendanceDecision::Waived->value,
            'reason' => 'Nghỉ có xin phép, đã trao đổi miệng hồi tháng trước.',
        ])
        ->assertOk();
});
