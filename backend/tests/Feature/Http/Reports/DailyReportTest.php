<?php

declare(strict_types=1);

use App\Domain\Identity\Enums\Role;
use App\Domain\Identity\Models\Department;
use App\Domain\Identity\Models\User;
use App\Domain\Report\Enums\DailyReportStatus;
use App\Domain\Report\Models\DailyReport;
use App\Domain\Report\Notifications\ReportReviewedNotification;
use App\Domain\Task\Models\Task;
use Carbon\CarbonImmutable;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Notification;

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->withHeader('Origin', 'http://localhost:3000');
    /*
    | 03:00 UTC = 10:00 giờ Việt Nam, vẫn là ngày 12.
    |
    | Ban đầu tôi đặt 17:00 cho giống "cuối giờ chiều" — nhưng 17:00 UTC là
    | 00:00 ngày 13 giờ Việt Nam, nên nhịp tim ghi vào ngày công 13 trong khi
    | báo cáo ghi ngày 12, và cờ `has_report` không khớp. Đúng cái bẫy mà cột
    | `work_date` sinh ra để chặn, lần này bẫy chính người viết test.
    */
    $this->travelTo(CarbonImmutable::parse('2026-08-12 03:00:00'));
});

/**
 * @param  array<string, mixed>  $ghiDe
 * @return array<string, mixed>
 */
function baoCao(array $ghiDe = []): array
{
    return array_merge([
        'report_date' => '2026-08-12',
        'content' => 'Hoàn thành phần đăng nhập, họp với khách hàng buổi chiều.',
        'task_ids' => [],
        'submit' => true,
    ], $ghiDe);
}

/*
|--------------------------------------------------------------------------
| Nộp báo cáo
|--------------------------------------------------------------------------
*/

it('nhân viên nộp được báo cáo ngày', function (): void {
    [, $nv] = sepVaNhanVien();

    $this->actingAs($nv)
        ->postJson('/api/v1/reports', baoCao())
        ->assertOk()
        ->assertJsonPath('data.status', 'submitted')
        ->assertJsonPath('data.report_date', '2026-08-12');

    expect(DailyReport::query()->where('user_id', $nv->id)->sole()->submitted_at)
        ->not->toBeNull();
});

it('lưu nháp thì chưa tính là đã nộp', function (): void {
    // Bản nháp là chỗ viết dở rồi quay lại, không phải trạng thái chờ duyệt.
    [, $nv] = sepVaNhanVien();

    $this->actingAs($nv)
        ->postJson('/api/v1/reports', baoCao(['submit' => false]))
        ->assertOk()
        ->assertJsonPath('data.status', 'draft');

    expect(DailyReport::query()->sole()->submitted_at)->toBeNull();
});

it('một người một ngày chỉ có một báo cáo', function (): void {
    [, $nv] = sepVaNhanVien();

    $this->actingAs($nv)->postJson('/api/v1/reports', baoCao(['submit' => false]))->assertOk();
    $this->actingAs($nv)->postJson('/api/v1/reports', baoCao([
        'content' => 'Viết lại cho rõ hơn: xong phần đăng nhập và phần quên mật khẩu.',
    ]))->assertOk();

    $r = DailyReport::query()->sole();

    expect($r->content)->toContain('quên mật khẩu')
        ->and($r->status)->toBe(DailyReportStatus::Submitted);
});

it('giữ mốc nộp đầu tiên khi sửa lại sau khi đã nộp', function (): void {
    // Câu "nộp muộn không" phải trả lời bằng lần nộp ĐẦU, không phải lần sửa
    // gần nhất.
    [, $nv] = sepVaNhanVien();

    $this->actingAs($nv)->postJson('/api/v1/reports', baoCao())->assertOk();
    $mocDau = DailyReport::query()->sole()->submitted_at;

    $this->travelTo(CarbonImmutable::parse('2026-08-12 22:00:00'));
    $this->actingAs($nv)->postJson('/api/v1/reports', baoCao([
        'content' => 'Bổ sung thêm: có hỗ trợ đồng nghiệp phần triển khai.',
    ]))->assertOk();

    expect(DailyReport::query()->sole()->submitted_at?->toDateTimeString())
        ->toBe($mocDau?->toDateTimeString());
});

it('từ chối nội dung quá ngắn', function (): void {
    // Không có mức sàn thì trường này đầy những dòng "ok", "làm việc" — và lúc
    // đó báo cáo ngày thành nghi thức bấm nút.
    [, $nv] = sepVaNhanVien();

    $this->actingAs($nv)
        ->postJson('/api/v1/reports', baoCao(['content' => 'ok']))
        ->assertStatus(422)
        ->assertJsonValidationErrors('content');
});

it('nộp được mà không gắn task nào', function (): void {
    // Người họp cả ngày hoặc hỗ trợ đồng nghiệp không có task nào để gắn. Bắt
    // buộc phải có task là ràng buộc khiến người ta bịa ra một task cho xong.
    [, $nv] = sepVaNhanVien();

    $this->actingAs($nv)
        ->postJson('/api/v1/reports', baoCao(['task_ids' => []]))
        ->assertOk()
        ->assertJsonCount(0, 'data.tasks');
});

it('gắn được task mình xem được', function (): void {
    [, $nv] = sepVaNhanVien();
    $task = Task::factory()->create(['assignee_id' => $nv->id, 'title' => 'Làm màn đăng nhập']);

    $this->actingAs($nv)
        ->postJson('/api/v1/reports', baoCao(['task_ids' => [$task->uuid]]))
        ->assertOk()
        ->assertJsonPath('data.tasks.0.title', 'Làm màn đăng nhập');
});

it('chặn gắn task ngoài phạm vi xem được', function (): void {
    // Không kiểm thì bất kỳ ai cũng dò được tiêu đề task của phòng khác bằng
    // cách nhét uuid vào báo cáo rồi đọc lại phản hồi.
    [, $nv] = sepVaNhanVien();
    $taskNguoiKhac = Task::factory()->create([
        'assignee_id' => nguoiNgoai()->id,
        'title' => 'Bí mật của phòng khác',
    ]);

    $phanHoi = $this->actingAs($nv)
        ->postJson('/api/v1/reports', baoCao(['task_ids' => [$taskNguoiKhac->uuid]]))
        ->assertStatus(422);

    expect((string) $phanHoi->getContent())->not->toContain('Bí mật của phòng khác');
});

it('bỏ tích một task thì nó biến khỏi báo cáo', function (): void {
    [, $nv] = sepVaNhanVien();
    $a = Task::factory()->create(['assignee_id' => $nv->id]);
    $b = Task::factory()->create(['assignee_id' => $nv->id]);

    $this->actingAs($nv)->postJson('/api/v1/reports', baoCao([
        'task_ids' => [$a->uuid, $b->uuid], 'submit' => false,
    ]))->assertOk();

    $this->actingAs($nv)
        ->postJson('/api/v1/reports', baoCao(['task_ids' => [$a->uuid]]))
        ->assertOk()
        ->assertJsonCount(1, 'data.tasks');
});

/*
|--------------------------------------------------------------------------
| Màn của quản lý — câu khó là "ai CHƯA báo cáo"
|--------------------------------------------------------------------------
*/

it('liệt kê cả người chưa nộp báo cáo', function (): void {
    // Đây là lý do báo cáo gắn vào NGÀY chứ không gắn vào từng task: người họp
    // cả ngày không có task nào, nhưng vẫn phải xuất hiện trong danh sách
    // "chưa báo cáo".
    $phong = Department::factory()->create();

    $sep = User::factory()->for($phong, 'department')->create();
    $sep->assignRole(Role::TruongPhong->value);

    $daNop = User::factory()->for($phong, 'department')->create(['name' => 'Người đã nộp']);
    $chuaNop = User::factory()->for($phong, 'department')->create(['name' => 'Người chưa nộp']);
    foreach ([$daNop, $chuaNop] as $u) {
        $u->assignRole(Role::NhanVien->value);
    }

    $this->actingAs($daNop)->postJson('/api/v1/reports', baoCao())->assertOk();

    $data = $this->actingAs($sep)
        ->getJson('/api/v1/reports/team?date=2026-08-12')
        ->assertOk()
        ->json('data');

    $theoTen = collect($data['rows'])->keyBy('user.name');

    expect($theoTen['Người đã nộp']['report'])->not->toBeNull()
        ->and($theoTen['Người chưa nộp']['report'])->toBeNull()
        ->and($data['submitted'])->toBe(1)
        ->and($data['total'])->toBe(3);
});

it('bản nháp coi như chưa nộp với quản lý', function (): void {
    $phong = Department::factory()->create();
    $sep = User::factory()->for($phong, 'department')->create();
    $sep->assignRole(Role::TruongPhong->value);
    $nv = User::factory()->for($phong, 'department')->create(['name' => 'Đang viết dở']);
    $nv->assignRole(Role::NhanVien->value);

    $this->actingAs($nv)->postJson('/api/v1/reports', baoCao(['submit' => false]))->assertOk();

    $dong = collect(
        $this->actingAs($sep)->getJson('/api/v1/reports/team?date=2026-08-12')->json('data.rows'),
    )->firstWhere('user.name', 'Đang viết dở');

    expect($dong['report'])->toBeNull()
        // Nhưng quản lý VẪN biết là có bản nháp — khác hẳn với chưa động gì.
        ->and($dong['has_draft'])->toBeTrue();
});

it('trưởng phòng chỉ thấy báo cáo phòng mình', function (): void {
    $phong = Department::factory()->create();
    $sep = User::factory()->for($phong, 'department')->create();
    $sep->assignRole(Role::TruongPhong->value);

    $nguoiKhac = nguoiNgoai();
    $this->actingAs($nguoiKhac)->postJson('/api/v1/reports', baoCao())->assertOk();

    $ten = collect(
        $this->actingAs($sep)->getJson('/api/v1/reports/team?date=2026-08-12')->json('data.rows'),
    )->pluck('user.name')->all();

    expect($ten)->not->toContain($nguoiKhac->name);
});

it('nhân viên thường không vào được màn báo cáo của đội', function (): void {
    [, $nv] = sepVaNhanVien();

    $this->actingAs($nv)->getJson('/api/v1/reports/team')->assertForbidden();
});

/*
|--------------------------------------------------------------------------
| Quản lý đọc và nhận xét
|--------------------------------------------------------------------------
*/

it('quản lý đánh dấu đã đọc, không cần nhận xét', function (): void {
    // Bắt ghi nhận xét mỗi ngày cho mỗi người là cách nhanh nhất khiến quản lý
    // bỏ luôn việc đọc.
    Notification::fake();

    [$sep, $nv] = sepVaNhanVien();
    $this->actingAs($nv)->postJson('/api/v1/reports', baoCao())->assertOk();
    $r = DailyReport::query()->sole();

    $this->actingAs($sep)
        ->postJson("/api/v1/reports/{$r->uuid}/review", [])
        ->assertOk()
        ->assertJsonPath('data.status', 'reviewed');

    Notification::assertNothingSent();
});

it('gửi thông báo khi có nhận xét thật', function (): void {
    Notification::fake();

    [$sep, $nv] = sepVaNhanVien();
    $this->actingAs($nv)->postJson('/api/v1/reports', baoCao())->assertOk();
    $r = DailyReport::query()->sole();

    $this->actingAs($sep)
        ->postJson("/api/v1/reports/{$r->uuid}/review", [
            'note' => 'Phần họp khách hàng ghi rõ hơn giúp mình nhé.',
        ])
        ->assertOk()
        ->assertJsonPath('data.review.note', 'Phần họp khách hàng ghi rõ hơn giúp mình nhé.');

    Notification::assertSentTo($nv, ReportReviewedNotification::class);
});

it('không đọc được bản nháp', function (): void {
    // Bản nháp là câu chữ nhân viên chưa muốn cho ai xem.
    [$sep, $nv] = sepVaNhanVien();
    $this->actingAs($nv)->postJson('/api/v1/reports', baoCao(['submit' => false]))->assertOk();

    $this->actingAs($sep)
        ->postJson('/api/v1/reports/'.DailyReport::query()->sole()->uuid.'/review', [])
        ->assertStatus(422)
        ->assertJsonPath('code', 'REPORT_NOT_SUBMITTED');
});

it('không sửa được báo cáo quản lý đã đọc', function (): void {
    [$sep, $nv] = sepVaNhanVien();
    $this->actingAs($nv)->postJson('/api/v1/reports', baoCao())->assertOk();
    $this->actingAs($sep)
        ->postJson('/api/v1/reports/'.DailyReport::query()->sole()->uuid.'/review', [])
        ->assertOk();

    $this->actingAs($nv)
        ->postJson('/api/v1/reports', baoCao(['content' => 'Sửa lại sau khi sếp đã đọc.']))
        ->assertStatus(422)
        ->assertJsonPath('code', 'REPORT_NOT_EDITABLE');
});

it('không tự đánh dấu đã đọc báo cáo của chính mình', function (): void {
    [$sep] = sepVaNhanVien();

    $this->actingAs($sep)->postJson('/api/v1/reports', baoCao())->assertOk();

    $this->actingAs($sep)
        ->postJson('/api/v1/reports/'.DailyReport::query()->sole()->uuid.'/review', [])
        ->assertForbidden();
});

it('không đọc được báo cáo của người ngoài phạm vi', function (): void {
    [$sep] = sepVaNhanVien();
    $nguoiKhac = nguoiNgoai();

    $this->actingAs($nguoiKhac)->postJson('/api/v1/reports', baoCao())->assertOk();

    $this->actingAs($sep)
        ->postJson('/api/v1/reports/'.DailyReport::query()->sole()->uuid.'/review', [])
        ->assertForbidden();
});

/*
|--------------------------------------------------------------------------
| Nối vào chấm công — mảnh còn thiếu từ đợt 3
|--------------------------------------------------------------------------
*/

it('bảng công đánh dấu ngày nào đã có báo cáo', function (): void {
    // Có giờ mà không có báo cáo là tín hiệu đáng nhìn nhất trên bảng công, và
    // là thứ con số giờ đơn độc không bao giờ nói được.
    $phong = Department::factory()->create();
    $sep = User::factory()->for($phong, 'department')->create();
    $sep->assignRole(Role::TruongPhong->value);
    $nv = User::factory()->for($phong, 'department')->create(['name' => 'Có báo cáo']);
    $nv->assignRole(Role::NhanVien->value);

    $this->actingAs($nv)->postJson('/api/v1/attendance/heartbeat')->assertOk();
    $this->actingAs($nv)->postJson('/api/v1/reports', baoCao())->assertOk();

    $dong = collect(
        $this->actingAs($sep)->getJson('/api/v1/attendance/team?month=2026-08')->json('data.rows'),
    )->firstWhere('user.name', 'Có báo cáo');

    expect($dong['cells']['2026-08-12']['has_report'])->toBeTrue();
});

it('chi tiết ngày công hiện trạng thái báo cáo', function (): void {
    [$sep, $nv] = sepVaNhanVien();

    $this->actingAs($nv)->postJson('/api/v1/attendance/heartbeat')->assertOk();
    $this->actingAs($nv)->postJson('/api/v1/reports', baoCao())->assertOk();

    $this->actingAs($sep)
        ->getJson("/api/v1/attendance/{$nv->uuid}/2026-08-12")
        ->assertOk()
        ->assertJsonPath('data.daily_report.status', 'submitted');
});

it('chi tiết ngày công trả null khi chưa báo cáo', function (): void {
    [$sep, $nv] = sepVaNhanVien();

    $this->actingAs($nv)->postJson('/api/v1/attendance/heartbeat')->assertOk();

    $this->actingAs($sep)
        ->getJson("/api/v1/attendance/{$nv->uuid}/2026-08-12")
        ->assertOk()
        ->assertJsonPath('data.daily_report', null);
});

/*
|--------------------------------------------------------------------------
| Của tôi
|--------------------------------------------------------------------------
*/

it('nhân viên xem lại báo cáo của chính mình theo tháng', function (): void {
    [, $nv] = sepVaNhanVien();

    $this->actingAs($nv)->postJson('/api/v1/reports', baoCao())->assertOk();
    $this->actingAs($nv)->postJson('/api/v1/reports', baoCao([
        'report_date' => '2026-08-11',
        'content' => 'Hôm qua làm phần danh sách công việc.',
    ]))->assertOk();

    $this->actingAs($nv)
        ->getJson('/api/v1/reports/me?month=2026-08')
        ->assertOk()
        ->assertJsonPath('data.submitted_count', 2)
        // Mới nhất lên đầu.
        ->assertJsonPath('data.reports.0.report_date', '2026-08-12');
});

it('không xem được báo cáo của người khác qua đường của tôi', function (): void {
    [, $nv] = sepVaNhanVien();
    $nguoiKhac = nguoiNgoai();

    $this->actingAs($nguoiKhac)->postJson('/api/v1/reports', baoCao([
        'content' => 'Nội dung riêng của người khác, không ai được thấy.',
    ]))->assertOk();

    $noiDung = (string) $this->actingAs($nv)
        ->getJson('/api/v1/reports/me?month=2026-08')
        ->assertOk()
        ->getContent();

    expect($noiDung)->not->toContain('Nội dung riêng của người khác');
});
