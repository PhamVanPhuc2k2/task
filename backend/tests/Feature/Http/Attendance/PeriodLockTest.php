<?php

declare(strict_types=1);

use App\Domain\Attendance\Enums\PeriodStatus;
use App\Domain\Attendance\Models\AttendancePeriod;
use App\Domain\Identity\Enums\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Payroll\Enums\PayrollAuditEvent;
use App\Domain\Payroll\Models\PayrollAudit;
use Carbon\CarbonImmutable;
use Database\Seeders\RolePermissionSeeder;

/*
|--------------------------------------------------------------------------
| Chốt sổ kỳ công
|--------------------------------------------------------------------------
|
| Nền móng của đợt 4. Trả lương từ những con số CÒN SỬA ĐƯỢC nghĩa là không bao
| giờ trả lời được câu "phiếu lương này tính từ đâu ra" — nên trước khi làm bất
| kỳ phép tính tiền nào, phải có một mốc nói rằng số liệu của kỳ đã đứng yên.
|
| Ba ranh giới được khoá ở đây:
|
| 1. Chốt là khoá CẢ BA: giờ công, đơn từ, báo cáo ngày. Đơn nghỉ duyệt sau khi
|    chốt sẽ đổi số ngày công đã dùng để tính lương — và không có gì báo.
|
| 2. Admin CHỐT được nhưng KHÔNG MỞ KHOÁ được. Hai mức trách nhiệm khác nhau.
|
| 3. Mở khoá bắt buộc ghi lý do. Ba tháng sau sẽ có người hỏi vì sao giờ công
|    tháng 8 khác con số trên phiếu lương tháng 8.
|
| Mốc: 02/09/2026. Kỳ 2026-08 đã kết thúc nên chốt được; kỳ 2026-09 thì chưa.
|
*/

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->withHeader('Origin', 'http://localhost:3000');
    $this->travelTo(CarbonImmutable::parse('2026-09-02 09:00:00'));
});

/** Chốt sẵn kỳ 2026-08 mà không đi qua HTTP. */
function chotSan(string $ky = '2026-08'): AttendancePeriod
{
    return AttendancePeriod::query()->create([
        'period' => $ky,
        'status' => PeriodStatus::Closed,
        'closed_at' => now(),
    ]);
}

it('giám đốc chốt được kỳ đã kết thúc', function (): void {
    $this->actingAs(giamDoc())
        ->postJson('/api/v1/attendance/periods/close', ['period' => '2026-08'])
        ->assertOk()
        ->assertJsonPath('data.period', '2026-08')
        ->assertJsonPath('data.is_locked', true);
});

it('KHÔNG chốt được kỳ chưa kết thúc', function (): void {
    /*
    | Chốt giữa kỳ là khoá luôn những ngày chưa ai đi làm. Người ta phát hiện ra
    | vào sáng hôm sau, khi nhịp tim chấm công không ghi được gì và không có lời
    | giải thích nào trên màn hình.
    */
    $this->actingAs(giamDoc())
        ->postJson('/api/v1/attendance/periods/close', ['period' => '2026-09'])
        ->assertJsonValidationErrors('period');
});

it('không chốt hai lần một kỳ', function (): void {
    chotSan();

    $this->actingAs(giamDoc())
        ->postJson('/api/v1/attendance/periods/close', ['period' => '2026-08'])
        ->assertJsonValidationErrors('period');
});

it('admin chốt được nhưng KHÔNG mở khoá được', function (): void {
    // Ngoại lệ duy nhất của "admin có tất cả" — xem Role::defaultPermissions().
    $admin = quanTri();

    $this->actingAs($admin)
        ->postJson('/api/v1/attendance/periods/close', ['period' => '2026-08'])
        ->assertOk();

    $this->actingAs($admin)
        ->postJson('/api/v1/attendance/periods/reopen', [
            'period' => '2026-08',
            'reason' => 'Kế toán báo sai giờ công của hai người.',
        ])
        ->assertForbidden();
});

it('mở khoá bắt buộc ghi lý do đủ dài', function (): void {
    chotSan();

    $this->actingAs(giamDoc())
        ->postJson('/api/v1/attendance/periods/reopen', ['period' => '2026-08', 'reason' => 'sửa'])
        ->assertJsonValidationErrors('reason');
});

it('không mở khoá được kỳ chưa từng chốt', function (): void {
    $this->actingAs(giamDoc())
        ->postJson('/api/v1/attendance/periods/reopen', [
            'period' => '2026-07',
            'reason' => 'Kế toán báo sai giờ công của hai người.',
        ])
        ->assertJsonValidationErrors('period');
});

it('kỳ đã chốt thì không sửa được ngày công', function (): void {
    chotSan();

    [$sep, $nv] = sepVaNhanVien();

    $this->actingAs($sep)
        ->postJson("/api/v1/attendance/{$nv->uuid}/review", [
            'work_date' => '2026-08-20',
            'decision' => 'waived',
            'reason' => 'Hôm đó họp cả ngày ở chỗ khách.',
        ])
        ->assertJsonValidationErrors('work_date');
});

it('kỳ đã chốt thì không nộp được báo cáo ngày của kỳ đó', function (): void {
    // Đối chiếu giờ công với báo cáo là một trong những căn cứ người quản lý
    // dùng khi chốt sổ — sửa báo cáo sau khi chốt là đổi căn cứ sau quyết định.
    chotSan();

    $u = User::factory()->create();
    $u->assignRole(Role::NhanVien->value);

    $this->actingAs($u)
        ->postJson('/api/v1/reports', [
            'report_date' => '2026-08-31',
            'content' => 'Hoàn thành phần đăng nhập, chiều họp với khách hàng.',
            'task_ids' => [],
            'submit' => true,
        ])
        ->assertJsonValidationErrors('report_date');
});

it('kỳ đã chốt thì không nộp được đơn nghỉ phủ vào đó', function (): void {
    chotSan();

    $u = User::factory()->create();
    $u->assignRole(Role::NhanVien->value);

    $this->actingAs($u)
        ->postJson('/api/v1/leave', [
            'type' => 'annual',
            'start_date' => '2026-08-20',
            'end_date' => '2026-08-21',
            'reason' => 'Về quê có việc gia đình.',
        ])
        ->assertJsonValidationErrors('start_date');
});

it('đơn vắt hai kỳ mà MỘT kỳ đã chốt cũng bị chặn', function (): void {
    /*
    | Chỗ dễ lọt nhất. Chỉ kiểm ngày bắt đầu thì một đơn từ 30/08 sang 02/09 đi
    | qua được nếu ngày bắt đầu rơi vào kỳ đang mở — và nó vẫn đổi số ngày công
    | của kỳ đã chốt.
    */
    chotSan();

    $u = User::factory()->create();
    $u->assignRole(Role::NhanVien->value);

    $this->actingAs($u)
        ->postJson('/api/v1/leave', [
            'type' => 'annual',
            'start_date' => '2026-08-30',
            'end_date' => '2026-09-02',
            'reason' => 'Về quê có việc gia đình.',
        ])
        ->assertJsonValidationErrors('start_date');
});

it('mở khoá rồi thì sửa lại được', function (): void {
    chotSan();

    $this->actingAs(giamDoc())
        ->postJson('/api/v1/attendance/periods/reopen', [
            'period' => '2026-08',
            'reason' => 'Kế toán báo sai giờ công của hai người.',
        ])
        ->assertOk()
        ->assertJsonPath('data.is_locked', false)
        ->assertJsonPath('data.reopen_reason', 'Kế toán báo sai giờ công của hai người.');

    [$sep, $nv] = sepVaNhanVien();

    $this->actingAs($sep)
        ->postJson("/api/v1/attendance/{$nv->uuid}/review", [
            'work_date' => '2026-08-20',
            'decision' => 'waived',
            'reason' => 'Hôm đó họp cả ngày ở chỗ khách.',
        ])
        ->assertOk();
});

it('kỳ chưa ai động tới thì mặc định là MỞ', function (): void {
    // Bảng thưa: không có dòng nghĩa là mở. Không sinh sẵn dòng cho mọi tháng,
    // vì một tháng thiếu dòng vì job không chạy sẽ bị coi là "không tồn tại".
    [$sep, $nv] = sepVaNhanVien();

    $this->actingAs($sep)
        ->postJson("/api/v1/attendance/{$nv->uuid}/review", [
            'work_date' => '2026-08-20',
            'decision' => 'waived',
            'reason' => 'Hôm đó họp cả ngày ở chỗ khách.',
        ])
        ->assertOk();
});

it('chốt và mở khoá đều vào nhật ký kiểm toán', function (): void {
    /*
    | Bảng `attendance_periods` chỉ giữ trạng thái HIỆN TẠI. Một kỳ chốt → mở →
    | chốt lại nhiều lần thì lịch sử nằm ở `payroll_audits` — nhật ký chỉ ghi
    | thêm, không sửa, không xoá.
    */
    $gd = giamDoc();

    $this->actingAs($gd)
        ->postJson('/api/v1/attendance/periods/close', ['period' => '2026-08'])
        ->assertOk();

    $this->actingAs($gd)
        ->postJson('/api/v1/attendance/periods/reopen', [
            'period' => '2026-08',
            'reason' => 'Kế toán báo sai giờ công của hai người.',
        ])
        ->assertOk();

    $vet = PayrollAudit::query()->orderBy('id')->get();

    expect($vet)->toHaveCount(2)
        ->and($vet[0]?->event)->toBe(PayrollAuditEvent::PeriodClosed)
        ->and($vet[1]?->event)->toBe(PayrollAuditEvent::PeriodReopened)
        // Lý do phải nằm trong nhật ký, không chỉ trên dòng kỳ công: chốt lại
        // lần nữa sẽ không xoá cột `reopen_reason`, nhưng lần mở thứ hai sẽ ghi
        // đè nó — còn nhật ký thì giữ cả hai.
        ->and($vet[1]?->context['reason'] ?? null)
        ->toBe('Kế toán báo sai giờ công của hai người.');
});

it('nhân viên thường không thấy màn chốt sổ', function (): void {
    $u = User::factory()->create();
    $u->assignRole(Role::NhanVien->value);

    $this->actingAs($u)->getJson('/api/v1/attendance/periods')->assertForbidden();
    $this->actingAs($u)
        ->postJson('/api/v1/attendance/periods/close', ['period' => '2026-08'])
        ->assertForbidden();
});
