<?php

declare(strict_types=1);

use App\Domain\Attendance\Enums\RequestStatus;
use App\Domain\Attendance\Models\Holiday;
use App\Domain\Attendance\Models\OvertimeRequest;
use App\Domain\Identity\Enums\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Leave\Enums\LeaveStatus;
use App\Domain\Leave\Enums\LeaveType;
use App\Domain\Leave\Models\LeaveRequest;
use App\Domain\Payroll\Enums\PayrollAuditEvent;
use App\Domain\Payroll\Models\PayrollAudit;
use App\Domain\Payroll\Models\SalaryRecord;
use Carbon\CarbonImmutable;
use Database\Seeders\RolePermissionSeeder;

/*
|--------------------------------------------------------------------------
| Bảng kê lương theo giờ công thực tế
|--------------------------------------------------------------------------
|
| Chỗ mọi thứ của đợt 4 quy ra tiền: giờ công đã có người duyệt, đơn nghỉ đã
| duyệt, làm thêm giờ đã duyệt, và mức lương đang hiệu lực.
|
| Bốn ranh giới được khoá ở đây:
|
| 1. Số phút chuẩn tính theo LỊCH THỰC TẾ của kỳ, không phải 26 ngày cố định.
|    Tháng 08/2026 có 21 ngày cả ngày và 5 ngày nửa buổi = 10.890 phút.
|
| 2. Ân hạn 5 phút áp cho TỪNG NGÀY. Cộng dồn cả tháng rồi trừ một lần nghĩa là
|    năm phút lẻ mỗi ngày thành gần hai tiếng cuối tháng.
|
| 3. Ngày lễ không đòi hỏi sự có mặt — nó rút số phút chuẩn xuống, nên người
|    nghỉ lễ vẫn nhận đủ lương mà không cần một luật miễn trừ riêng.
|
| 4. Chỉ đơn ĐÃ DUYỆT mới ra tiền. Đơn đang chờ không miễn cho ai cái gì.
|
| Phép tính tiền có bộ test riêng ở tests/Unit — bài này kiểm phần ĐI GOM SỐ.
|
*/

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->withHeader('Origin', 'http://localhost:3000');
    $this->travelTo(CarbonImmutable::parse('2026-09-02 09:00:00'));
});

/** Một nhân viên có mức lương đang hiệu lực. */
function nhanVienCoLuong(string $luong = '10000000.00', string $phuCap = '0.00'): User
{
    $u = User::factory()->create(['joined_at' => '2024-01-01']);
    $u->assignRole(Role::NhanVien->value);

    SalaryRecord::query()->create([
        'user_id' => $u->id,
        'effective_from' => '2024-01-01',
        'base_salary' => $luong,
        'allowance' => $phuCap,
        'currency' => 'VND',
        'reason' => 'Mức khởi điểm.',
    ]);

    return $u;
}

/** Một đơn nghỉ đã duyệt. */
function nghiDaDuyet(User $u, string $tu, string $den, LeaveType $loai): LeaveRequest
{
    return LeaveRequest::query()->create([
        'user_id' => $u->id,
        'type' => $loai,
        'start_date' => $tu,
        'end_date' => $den,
        'reason' => 'Về quê có việc gia đình.',
        'status' => LeaveStatus::Approved,
        'reviewed_at' => now(),
    ]);
}

// ── Số phút chuẩn ───────────────────────────────────────────────────────

it('số phút chuẩn tính theo lịch thực tế của kỳ', function (): void {
    // 21 ngày cả ngày × 465 + 5 ngày nửa buổi × 225 = 10.890 phút.
    $nv = nhanVienCoLuong();

    $this->actingAs($nv)
        ->getJson('/api/v1/payroll/payslips/me?period=2026-08')
        ->assertOk()
        ->assertJsonPath('data.period', '2026-08')
        ->assertJsonPath('data.minutes.standard', 10890)
        ->assertJsonPath('data.money.hourly_rate', '55096');
});

it('ngày lễ rút số phút chuẩn xuống, không cần luật miễn trừ riêng', function (): void {
    /*
    | 03/08/2026 là thứ hai. Thành ngày lễ thì không ai phải có mặt, nên nó biến
    | khỏi cả tử số lẫn mẫu số — người nghỉ lễ nhận đủ lương mà không cần một
    | nhánh `if` nào nói "ngày lễ thì bỏ qua".
    */
    Holiday::query()->create([
        'date' => '2026-08-03',
        'observed_date' => '2026-08-03',
        'name' => 'Ngày thử',
        'is_paid' => true,
    ]);

    $nv = nhanVienCoLuong();

    $this->actingAs($nv)
        ->getJson('/api/v1/payroll/payslips/me?period=2026-08')
        ->assertOk()
        ->assertJsonPath('data.minutes.standard', 10890 - 465);
});

// ── Ân hạn theo từng ngày ───────────────────────────────────────────────

it('thiếu 5 phút trong một ngày thì bỏ qua', function (): void {
    // Ân hạn DỜI NGƯỠNG. 03/08 là thứ hai, ca 465 phút.
    $nv = nhanVienCoLuong();
    coGioLam($nv, '2026-08-03', 460);

    $this->actingAs($nv)
        ->getJson('/api/v1/payroll/payslips/me?period=2026-08')
        ->assertOk()
        // Ngày đó góp 0 phút thiếu thay vì 465.
        ->assertJsonPath('data.minutes.shortfall', 10890 - 465);
});

it('thiếu 6 phút thì tính ĐỦ 6, không trừ ân hạn ra', function (): void {
    // Ân hạn dời ngưỡng chứ không trừ vào số phút — cùng quy ước với ân hạn đi
    // muộn và về sớm. Ba chỗ hiểu khác nhau là ba con số không ai đối chiếu nổi.
    $nv = nhanVienCoLuong();
    coGioLam($nv, '2026-08-03', 459);

    $this->actingAs($nv)
        ->getJson('/api/v1/payroll/payslips/me?period=2026-08')
        ->assertOk()
        ->assertJsonPath('data.minutes.shortfall', 10890 - 465 + 6);
});

// ── Đơn nghỉ ────────────────────────────────────────────────────────────

it('nghỉ phép năm đã duyệt thì không đòi hỏi có mặt và không bị trừ', function (): void {
    // 03/08 và 04/08 là thứ hai, thứ ba — hai ngày cả ngày = 930 phút.
    $nv = nhanVienCoLuong();
    nghiDaDuyet($nv, '2026-08-03', '2026-08-04', LeaveType::Annual);

    $this->actingAs($nv)
        ->getJson('/api/v1/payroll/payslips/me?period=2026-08')
        ->assertOk()
        ->assertJsonPath('data.minutes.paid_leave', 930)
        ->assertJsonPath('data.minutes.unpaid_leave', 0)
        ->assertJsonPath('data.minutes.required', 10890 - 930)
        ->assertJsonPath('data.minutes.shortfall', 10890 - 930)
        ->assertJsonPath('data.money.unpaid_leave_deduction', '0');
});

it('nghỉ không lương đã duyệt thì bị trừ, ở một dòng riêng', function (): void {
    // Tách dòng chứ không gộp vào "thiếu giờ": người đọc phải thấy được ngày nào
    // bị trừ và vì sao.
    $nv = nhanVienCoLuong();
    nghiDaDuyet($nv, '2026-08-03', '2026-08-04', LeaveType::Unpaid);

    $this->actingAs($nv)
        ->getJson('/api/v1/payroll/payslips/me?period=2026-08')
        ->assertOk()
        ->assertJsonPath('data.minutes.unpaid_leave', 930)
        ->assertJsonPath('data.minutes.paid_leave', 0)
        // 930 phút = 15,5 giờ × 55.096,418732 = 853.994,49…
        ->assertJsonPath('data.money.unpaid_leave_deduction', '853994');
});

it('đơn nghỉ CHƯA duyệt không miễn cho ai cái gì', function (): void {
    // Khác hẳn phép kiểm hạn mức, nơi đơn đang chờ cũng chặn chỗ. Ở đây là
    // tiền: trừ lương theo một quyết định chưa xảy ra là sai.
    $nv = nhanVienCoLuong();

    LeaveRequest::query()->create([
        'user_id' => $nv->id,
        'type' => LeaveType::Unpaid,
        'start_date' => '2026-08-03',
        'end_date' => '2026-08-04',
        'reason' => 'Về quê có việc gia đình.',
        'status' => LeaveStatus::Pending,
    ]);

    $this->actingAs($nv)
        ->getJson('/api/v1/payroll/payslips/me?period=2026-08')
        ->assertOk()
        ->assertJsonPath('data.minutes.unpaid_leave', 0)
        ->assertJsonPath('data.minutes.paid_leave', 0);
});

// ── Làm thêm giờ ────────────────────────────────────────────────────────

it('chỉ làm thêm ĐÃ DUYỆT mới ra tiền, theo hệ số đã đóng băng', function (): void {
    $nv = nhanVienCoLuong();

    OvertimeRequest::query()->create([
        'user_id' => $nv->id,
        'work_date' => '2026-08-03',
        'start_time' => '18:00',
        'end_time' => '20:00',
        'minutes' => 120,
        'reason' => 'Chốt bản demo cho khách sáng mai.',
        'status' => RequestStatus::Approved,
        'rate_percent' => 150,
        'approved_minutes' => 120,
        'reviewed_at' => now(),
    ]);

    // Đơn chờ duyệt: không cộng đồng nào.
    OvertimeRequest::query()->create([
        'user_id' => $nv->id,
        'work_date' => '2026-08-04',
        'start_time' => '18:00',
        'end_time' => '21:00',
        'minutes' => 180,
        'reason' => 'Chốt bản demo cho khách sáng mai.',
        'status' => RequestStatus::Pending,
    ]);

    $this->actingAs($nv)
        ->getJson('/api/v1/payroll/payslips/me?period=2026-08')
        ->assertOk()
        ->assertJsonPath('data.minutes.overtime', 120)
        ->assertJsonCount(1, 'data.overtime_lines')
        ->assertJsonPath('data.overtime_lines.0.percent', 150)
        // 2 giờ × 55.096,418732 × 1,5 = 165.289,25…
        ->assertJsonPath('data.overtime_lines.0.amount', '165289');
});

it('duyệt ít hơn số đăng ký thì trả theo số ĐÃ DUYỆT', function (): void {
    $nv = nhanVienCoLuong();

    OvertimeRequest::query()->create([
        'user_id' => $nv->id,
        'work_date' => '2026-08-03',
        'start_time' => '18:00',
        'end_time' => '21:00',
        'minutes' => 180,
        'reason' => 'Chốt bản demo cho khách sáng mai.',
        'status' => RequestStatus::Approved,
        'rate_percent' => 200,
        'approved_minutes' => 60,
        'reviewed_at' => now(),
    ]);

    $this->actingAs($nv)
        ->getJson('/api/v1/payroll/payslips/me?period=2026-08')
        ->assertOk()
        ->assertJsonPath('data.minutes.overtime', 60);
});

// ── Bản tạm hay bản chính ───────────────────────────────────────────────

it('kỳ chưa chốt sổ thì phiếu là bản TẠM', function (): void {
    /*
    | Mọi con số còn đổi được: một đơn giải trình được duyệt chiều nay sẽ đổi số
    | giờ thiếu của cả tháng. Không nói ra thì người ta chụp màn hình một phiếu
    | tạm rồi tháng sau đối chiếu với phiếu thật và không hiểu.
    */
    $nv = nhanVienCoLuong();

    $this->actingAs($nv)
        ->getJson('/api/v1/payroll/payslips/me?period=2026-08')
        ->assertJsonPath('data.is_final', false);

    khoaKy('2026-08');

    $this->actingAs($nv)
        ->getJson('/api/v1/payroll/payslips/me?period=2026-08')
        ->assertJsonPath('data.is_final', true);
});

it('không truyền kỳ thì mặc định là tháng VỪA KẾT THÚC', function (): void {
    // Tháng đang chạy thì phiếu chưa có nghĩa gì — còn hai mươi ngày công chưa
    // xảy ra, và mở ra thấy một phiếu gần như trống là cách nhanh nhất khiến
    // người dùng tưởng hệ thống hỏng.
    $nv = nhanVienCoLuong();

    $this->actingAs($nv)
        ->getJson('/api/v1/payroll/payslips/me')
        ->assertOk()
        ->assertJsonPath('data.period', '2026-08');
});

// ── Quyền ───────────────────────────────────────────────────────────────

it('nhân viên xem được phiếu của mình nhưng không xem được bảng cả công ty', function (): void {
    $nv = nhanVienCoLuong();

    $this->actingAs($nv)->getJson('/api/v1/payroll/payslips/me')->assertOk();
    $this->actingAs($nv)->getJson('/api/v1/payroll/payslips')->assertForbidden();
});

it('giám đốc xem được bảng kê, có tổng chi của kỳ', function (): void {
    nhanVienCoLuong('10000000.00');
    nhanVienCoLuong('6000000.00');

    $bang = $this->actingAs(giamDoc())
        ->getJson('/api/v1/payroll/payslips?period=2026-08')
        ->assertOk()
        ->assertJsonPath('data.period', '2026-08')
        ->assertJsonStructure([
            'data' => [
                'period', 'is_final', 'total', 'limit', 'net_total',
                'payslips' => [[
                    'period', 'is_final',
                    'minutes' => ['standard', 'required', 'worked', 'paid_leave', 'unpaid_leave', 'shortfall', 'overtime'],
                    'money' => ['base_salary', 'allowance', 'hourly_rate', 'shortfall_deduction', 'unpaid_leave_deduction', 'overtime_pay', 'net_total'],
                    'overtime_lines',
                    'user' => ['id', 'name', 'employee_code', 'department'],
                ]],
            ],
        ]);

    // Không ai đi làm ngày nào nên bị trừ trọn lương; tổng chi của kỳ là 0.
    expect($bang->json('data.net_total'))->toBe('0');
});

it('mọi lượt mở bảng kê đều vào nhật ký kiểm toán', function (): void {
    // "Ai đã xem bảng lương tháng 9" là câu hỏi có thật và sẽ có người hỏi.
    nhanVienCoLuong();

    $this->actingAs(giamDoc())->getJson('/api/v1/payroll/payslips?period=2026-08')->assertOk();

    $vet = PayrollAudit::query()->latest('id')->first();

    expect($vet?->event)->toBe(PayrollAuditEvent::ViewedList)
        ->and($vet?->context['period'] ?? null)->toBe('2026-08')
        ->and($vet?->context['screen'] ?? null)->toBe('payslips');
});

it('mở phiếu của CHÍNH MÌNH thì không ghi nhật ký', function (): void {
    // `payroll_audits` tồn tại để trả lời "ai đã xem lương của NGƯỜI KHÁC". Ghi
    // cả lượt tự xem thì nhật ký đầy những dòng vô nghĩa đúng lúc cần tra cứu.
    $nv = nhanVienCoLuong();

    $this->actingAs($nv)->getJson('/api/v1/payroll/payslips/me')->assertOk();

    expect(PayrollAudit::query()->count())->toBe(0);
});

it('người chưa được đặt lương vẫn hiện trên bảng, với số tiền bằng 0', function (): void {
    // Bỏ họ ra khỏi bảng là làm một người biến mất khỏi bảng lương mà không ai
    // biết vì sao — và đó đúng là người cần được chú ý.
    $u = User::factory()->create(['joined_at' => '2024-01-01']);
    $u->assignRole(Role::NhanVien->value);

    $this->actingAs($u)
        ->getJson('/api/v1/payroll/payslips/me?period=2026-08')
        ->assertOk()
        ->assertJsonPath('data.money.base_salary', '0')
        ->assertJsonPath('data.money.net_total', '0')
        // Phần giờ công vẫn đầy đủ: thiếu lương không có nghĩa là thiếu số liệu.
        ->assertJsonPath('data.minutes.standard', 10890);
});
