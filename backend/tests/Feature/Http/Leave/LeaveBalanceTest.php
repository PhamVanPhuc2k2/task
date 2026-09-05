<?php

declare(strict_types=1);

use App\Domain\Attendance\Models\Holiday;
use App\Domain\Identity\Enums\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Leave\Enums\LeaveStatus;
use App\Domain\Leave\Enums\LeaveType;
use App\Domain\Leave\Models\LeaveBalance;
use App\Domain\Leave\Models\LeaveRequest;
use App\Domain\Payroll\Enums\PayrollAuditEvent;
use App\Domain\Payroll\Models\PayrollAudit;
use Carbon\CarbonImmutable;
use Database\Seeders\RolePermissionSeeder;

/*
|--------------------------------------------------------------------------
| Quỹ phép năm
|--------------------------------------------------------------------------
|
| Trước phần này, `LeaveType::Annual` chỉ là một cái nhãn: nó không trừ vào quỹ
| nào, và một người nộp ba mươi ngày phép năm cũng chẳng có gì chặn.
|
| Bốn ranh giới được khoá ở đây:
|
| 1. Số ngày được hưởng bám Bộ luật Lao động 2019 — 12 ngày (Điều 113), cộng 1
|    ngày mỗi 5 năm thâm niên (Điều 114), chia tỷ lệ nếu chưa làm đủ năm.
|
| 2. Đã dùng đếm theo NGÀY CÔNG, không đếm ngày lịch. Nghỉ từ thứ sáu tới thứ
|    hai phủ 4 ngày lịch nhưng chỉ tiêu 2,5 ngày phép — công ty làm sáng thứ
|    bảy, chủ nhật nghỉ. Nghỉ trùng ngày lễ không tiêu ngày phép nào.
|
| 3. Vượt quỹ thì CHẶN, và câu lỗi nói ra đường đi tiếp: nộp nghỉ không lương.
|
| 4. Sửa quỹ là quyền RIÊNG, không phải `leave.approve` — nó ra tiền, vì phép
|    chưa nghỉ hết phải được thanh toán khi thôi việc (Điều 113 khoản 4).
|
| Mốc: 02/09/2026. Lịch tuần mặc định: T2–T6 cả ngày, T7 nửa buổi, CN nghỉ.
|
*/

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->withHeader('Origin', 'http://localhost:3000');
    $this->travelTo(CarbonImmutable::parse('2026-09-02 09:00:00'));
});

/** Một nhân viên có ngày vào làm xác định — quỹ phép phụ thuộc vào nó. */
function nhanVienVaoLam(string $ngayVaoLam): User
{
    $u = User::factory()->create(['joined_at' => $ngayVaoLam]);
    $u->assignRole(Role::NhanVien->value);

    return $u;
}

/** Một đơn nghỉ có sẵn trong database, không đi qua HTTP. */
function donNghi(
    User $u,
    string $tu,
    string $den,
    LeaveType $loai = LeaveType::Annual,
    LeaveStatus $trangThai = LeaveStatus::Approved,
): LeaveRequest {
    return LeaveRequest::query()->create([
        'user_id' => $u->id,
        'type' => $loai,
        'start_date' => $tu,
        'end_date' => $den,
        'reason' => 'Về quê có việc gia đình.',
        'status' => $trangThai,
        'reviewed_at' => $trangThai === LeaveStatus::Pending ? null : now(),
    ]);
}

// ── Số ngày được hưởng ──────────────────────────────────────────────────

it('chưa ai động tới thì hưởng đúng số luật định', function (): void {
    // Vào làm 2024, chưa tới mốc thâm niên 5 năm: đúng 12 ngày của Điều 113.
    $nv = nhanVienVaoLam('2024-01-01');

    $this->actingAs($nv)
        ->getJson('/api/v1/leave/balance')
        ->assertOk()
        ->assertJsonPath('data.year', 2026)
        ->assertJsonPath('data.entitled_days', 12)
        ->assertJsonPath('data.is_overridden', false)
        ->assertJsonPath('data.carried_over_days', 0)
        ->assertJsonPath('data.used_days', 0)
        ->assertJsonPath('data.remaining_days', 12);
});

it('đủ 5 năm thâm niên thì được thêm một ngày', function (): void {
    // Điều 114. Tính tới 31/12 của năm đang hỏi, không tính tới hôm nay — quỹ
    // phép là con số của cả năm.
    $nv = nhanVienVaoLam('2020-01-01');

    $this->actingAs($nv)
        ->getJson('/api/v1/leave/balance')
        ->assertOk()
        ->assertJsonPath('data.entitled_days', 13);
});

it('vào làm giữa năm thì chia theo tỷ lệ số tháng', function (): void {
    // Nghị định 145/2020 Điều 66. Vào 01/07 thì làm 6 tháng của năm 2026.
    $nv = nhanVienVaoLam('2026-07-01');

    $this->actingAs($nv)
        ->getJson('/api/v1/leave/balance')
        ->assertOk()
        ->assertJsonPath('data.entitled_days', 6);
});

it('chưa vào làm thì chưa có ngày phép nào', function (): void {
    $nv = nhanVienVaoLam('2027-03-01');

    $this->actingAs($nv)
        ->getJson('/api/v1/leave/balance')
        ->assertOk()
        ->assertJsonPath('data.entitled_days', 0);
});

// ── Đã dùng: đếm ngày công, không đếm ngày lịch ─────────────────────────

it('nghỉ từ thứ sáu tới thứ hai chỉ tiêu 2,5 ngày phép', function (): void {
    /*
    | Điểm quan trọng nhất của cả tính năng.
    |
    | 11/09 là thứ sáu, 14/09 là thứ hai. Bốn ngày lịch, nhưng thứ bảy nửa buổi
    | và chủ nhật không tính — 1 + 0,5 + 0 + 1 = 2,5.
    |
    | Đếm ngày lịch thì một tuần nghỉ ăn 7 ngày trong quỹ 12 ngày, và quỹ phép
    | năm thành một con số vô nghĩa.
    */
    $nv = nhanVienVaoLam('2024-01-01');
    donNghi($nv, '2026-09-11', '2026-09-14');

    $this->actingAs($nv)
        ->getJson('/api/v1/leave/balance')
        ->assertOk()
        ->assertJsonPath('data.used_days', 2.5)
        ->assertJsonPath('data.remaining_days', 9.5);
});

it('nghỉ trùng ngày lễ thì không tiêu ngày phép nào của hôm đó', function (): void {
    $nv = nhanVienVaoLam('2024-01-01');

    Holiday::query()->create([
        'date' => '2026-09-11',
        'observed_date' => '2026-09-11',
        'name' => 'Ngày thử',
        'is_paid' => true,
    ]);

    donNghi($nv, '2026-09-11', '2026-09-14');

    // Mất đúng ngày thứ sáu: 0 + 0,5 + 0 + 1 = 1,5.
    $this->actingAs($nv)
        ->getJson('/api/v1/leave/balance')
        ->assertOk()
        ->assertJsonPath('data.used_days', 1.5);
});

it('đơn ĐANG CHỜ DUYỆT cũng trừ vào quỹ', function (): void {
    // Chỉ đếm đơn đã duyệt thì nộp năm đơn nhỏ cùng lúc là lách được — mỗi đơn
    // nhìn riêng đều nằm trong quỹ, và người duyệt phải tự cộng nhẩm.
    $nv = nhanVienVaoLam('2024-01-01');
    donNghi($nv, '2026-09-11', '2026-09-14', trangThai: LeaveStatus::Pending);

    $this->actingAs($nv)
        ->getJson('/api/v1/leave/balance')
        ->assertJsonPath('data.used_days', 2.5);
});

it('đơn bị từ chối thì trả lại quỹ', function (): void {
    $nv = nhanVienVaoLam('2024-01-01');
    donNghi($nv, '2026-09-11', '2026-09-14', trangThai: LeaveStatus::Rejected);

    $this->actingAs($nv)->getJson('/api/v1/leave/balance')->assertJsonPath('data.used_days', 0);
});

it('nghỉ ốm và nghỉ không lương KHÔNG trừ vào quỹ phép năm', function (): void {
    $nv = nhanVienVaoLam('2024-01-01');
    donNghi($nv, '2026-09-11', '2026-09-14', loai: LeaveType::Sick);

    $this->actingAs($nv)->getJson('/api/v1/leave/balance')->assertJsonPath('data.used_days', 0);
});

// ── Chặn khi vượt quỹ ───────────────────────────────────────────────────

it('vượt quỹ thì chặn, và nói ra đường đi tiếp', function (): void {
    $nv = nhanVienVaoLam('2024-01-01');

    // Ghi đè quỹ về 1 ngày thay vì nộp đủ mười ba đơn: test này kiểm phép CHẶN,
    // không kiểm phép cộng dồn — thứ đã có test riêng ở trên.
    LeaveBalance::query()->create([
        'user_id' => $nv->id,
        'year' => 2026,
        'entitled_days_override' => 1,
    ]);

    $this->actingAs($nv)
        ->postJson('/api/v1/leave', [
            'type' => 'annual',
            'start_date' => '2026-09-11',
            'end_date' => '2026-09-14',
            'reason' => 'Về quê có việc gia đình.',
        ])
        ->assertJsonValidationErrors('start_date')
        // Câu lỗi phải chỉ đường: "hết phép" mà không nói làm gì tiếp thì người
        // ta đi hỏi nhân sự, mà nhân sự cũng chỉ trả lời đúng câu đó.
        ->assertJsonFragment(['start_date' => [
            'Quỹ phép năm 2026 chỉ còn 1 ngày, mà đơn này cần 2,5 ngày. Nghỉ thêm thì nộp đơn nghỉ không lương.',
        ]]);
});

it('vừa đủ quỹ thì vẫn nộp được', function (): void {
    $nv = nhanVienVaoLam('2024-01-01');

    LeaveBalance::query()->create([
        'user_id' => $nv->id,
        'year' => 2026,
        'entitled_days_override' => 2.5,
    ]);

    $this->actingAs($nv)
        ->postJson('/api/v1/leave', [
            'type' => 'annual',
            'start_date' => '2026-09-11',
            'end_date' => '2026-09-14',
            'reason' => 'Về quê có việc gia đình.',
        ])
        ->assertCreated();
});

it('hết phép năm thì vẫn nộp được đơn nghỉ KHÔNG LƯƠNG', function (): void {
    // Đường thoát mà câu lỗi chỉ tới phải thật sự đi được.
    $nv = nhanVienVaoLam('2024-01-01');

    LeaveBalance::query()->create([
        'user_id' => $nv->id,
        'year' => 2026,
        'entitled_days_override' => 0,
    ]);

    $this->actingAs($nv)
        ->postJson('/api/v1/leave', [
            'type' => 'unpaid',
            'start_date' => '2026-09-11',
            'end_date' => '2026-09-14',
            'reason' => 'Về quê có việc gia đình.',
        ])
        ->assertCreated();
});

it('đơn vắt qua giao thừa kiểm quỹ của CẢ HAI năm', function (): void {
    /*
    | Đơn 28/12/2026 → 04/01/2027 tiêu 4 ngày của năm 2026 và 2,5 ngày của năm
    | 2027. Dồn cả vào năm bắt đầu nghĩa là nghỉ cuối năm bị tính nặng hơn nghỉ
    | giữa năm — và quỹ năm sau bị tiêu mà năm sau không hề biết.
    */
    $nv = nhanVienVaoLam('2024-01-01');

    LeaveBalance::query()->create([
        'user_id' => $nv->id,
        'year' => 2027,
        'entitled_days_override' => 1,
    ]);

    $this->actingAs($nv)
        ->postJson('/api/v1/leave', [
            'type' => 'annual',
            'start_date' => '2026-12-28',
            'end_date' => '2027-01-04',
            'reason' => 'Về quê có việc gia đình.',
        ])
        ->assertJsonValidationErrors('start_date')
        ->assertJsonFragment(['start_date' => [
            'Quỹ phép năm 2027 chỉ còn 1 ngày, mà đơn này cần 2,5 ngày. Nghỉ thêm thì nộp đơn nghỉ không lương.',
        ]]);
});

// ── Nhân sự sửa quỹ ─────────────────────────────────────────────────────

it('giám đốc chuyển được phép tồn và cộng thêm ngày', function (): void {
    $nv = nhanVienVaoLam('2024-01-01');

    $this->actingAs(giamDoc())
        ->postJson("/api/v1/leave/balances/{$nv->uuid}", [
            'year' => 2026,
            'carried_over_days' => 3,
            'adjustment_days' => 1.5,
            'note' => 'Chuyển 3 ngày tồn của 2025, cộng 1,5 ngày cho dự án Tết.',
        ])
        ->assertOk()
        ->assertJsonPath('data.entitled_days', 12)
        ->assertJsonPath('data.carried_over_days', 3)
        ->assertJsonPath('data.adjustment_days', 1.5)
        ->assertJsonPath('data.total_days', 16.5)
        ->assertJsonPath('data.is_overridden', false);
});

it('ghi đè giữ lại số hệ thống tự tính để đối chiếu', function (): void {
    // "Tự tính 12, nhân sự đặt 15" — không giữ lại thì ba tháng sau không ai
    // trả lời được câu "con số này đến từ đâu".
    $nv = nhanVienVaoLam('2024-01-01');

    $this->actingAs(giamDoc())
        ->postJson("/api/v1/leave/balances/{$nv->uuid}", [
            'year' => 2026,
            'entitled_days_override' => 15,
            'carried_over_days' => 0,
            'adjustment_days' => 0,
            'note' => 'Hợp đồng riêng, 15 ngày phép.',
        ])
        ->assertOk()
        ->assertJsonPath('data.entitled_days', 15)
        ->assertJsonPath('data.computed_entitled_days', 12)
        ->assertJsonPath('data.is_overridden', true);
});

it('điều chỉnh ÂM được, và không làm mất phần tính theo thâm niên', function (): void {
    $nv = nhanVienVaoLam('2020-01-01');

    $this->actingAs(giamDoc())
        ->postJson("/api/v1/leave/balances/{$nv->uuid}", [
            'year' => 2026,
            'carried_over_days' => 0,
            'adjustment_days' => -2,
            'note' => 'Trừ 2 ngày đã ứng trước từ năm ngoái.',
        ])
        ->assertOk()
        // 13 = 12 cơ bản + 1 thâm niên. Vẫn tính tự động, chỉ trừ đi 2.
        ->assertJsonPath('data.entitled_days', 13)
        ->assertJsonPath('data.total_days', 11);
});

it('vượt trần phép tồn thì bị chặn', function (): void {
    $nv = nhanVienVaoLam('2024-01-01');

    $this->actingAs(giamDoc())
        ->postJson("/api/v1/leave/balances/{$nv->uuid}", [
            'year' => 2026,
            'carried_over_days' => 99,
            'adjustment_days' => 0,
        ])
        ->assertJsonValidationErrors('carried_over_days');
});

it('nhập nửa ngày được, nhập một phần ba ngày thì không', function (): void {
    // 0,3 ngày phép là con số không màn hình nào hiển thị cho ra hồn, và nó lọt
    // vào bằng một cú gõ nhầm chứ không phải một quyết định.
    $nv = nhanVienVaoLam('2024-01-01');

    $this->actingAs(giamDoc())
        ->postJson("/api/v1/leave/balances/{$nv->uuid}", [
            'year' => 2026, 'carried_over_days' => 0.5, 'adjustment_days' => 0,
        ])
        ->assertOk();

    $this->actingAs(giamDoc())
        ->postJson("/api/v1/leave/balances/{$nv->uuid}", [
            'year' => 2026, 'carried_over_days' => 0.3, 'adjustment_days' => 0,
        ])
        ->assertJsonValidationErrors('carried_over_days');
});

it('đặt hết về 0 thì xoá dòng, quay về tự tính', function (): void {
    /*
    | Bảng thưa chỉ giữ được ý nghĩa "chưa ai động tới" nếu nó thật sự thưa. Một
    | dòng toàn số 0 làm màn hình hiện "đã điều chỉnh" cho người chưa ai đụng
    | vào, và nhân sự không có cách nào gỡ nhãn đó ra.
    */
    $nv = nhanVienVaoLam('2024-01-01');
    $gd = giamDoc();

    $this->actingAs($gd)
        ->postJson("/api/v1/leave/balances/{$nv->uuid}", [
            'year' => 2026, 'carried_over_days' => 3, 'adjustment_days' => 0,
        ])
        ->assertOk();

    expect(LeaveBalance::query()->count())->toBe(1);

    $this->actingAs($gd)
        ->postJson("/api/v1/leave/balances/{$nv->uuid}", [
            'year' => 2026, 'carried_over_days' => 0, 'adjustment_days' => 0,
        ])
        ->assertOk()
        ->assertJsonPath('data.entitled_days', 12);

    expect(LeaveBalance::query()->count())->toBe(0);
});

it('trưởng phòng duyệt được đơn nghỉ nhưng KHÔNG sửa được quỹ phép', function (): void {
    // Duyệt một đơn là quyết định về một lần vắng mặt; cộng thêm ngày phép là
    // quyết định ra tiền cho cả năm. Hai mức trách nhiệm khác nhau.
    [$sep, $nv] = sepVaNhanVien();

    $this->actingAs($sep)
        ->postJson("/api/v1/leave/balances/{$nv->uuid}", [
            'year' => 2026, 'carried_over_days' => 5, 'adjustment_days' => 0,
        ])
        ->assertForbidden();
});

it('mọi lần sửa quỹ đều vào nhật ký kiểm toán, kèm giá trị cũ', function (): void {
    // Phép chưa nghỉ hết phải được thanh toán khi thôi việc (Điều 113 khoản 4),
    // nên cộng thêm một ngày phép là cộng thêm một khoản tiền phải trả.
    $nv = nhanVienVaoLam('2024-01-01');

    $this->actingAs(giamDoc())
        ->postJson("/api/v1/leave/balances/{$nv->uuid}", [
            'year' => 2026,
            'carried_over_days' => 3,
            'adjustment_days' => 0,
            'note' => 'Chuyển 3 ngày tồn của 2025.',
        ])
        ->assertOk();

    $vet = PayrollAudit::query()->latest('id')->first();

    expect($vet?->event)->toBe(PayrollAuditEvent::LeaveBalanceChanged)
        ->and($vet?->subject_id)->toBe($nv->id)
        // `toEqual` chứ không `toBe`: JSON không phân biệt 3 với 3.0, nên số
        // thực đi qua cột `context` rồi quay về dưới dạng int.
        ->and($vet?->context['before']['carried_over'] ?? null)->toEqual(0)
        ->and($vet?->context['after']['carried_over'] ?? null)->toEqual(3);
});

// ── Bảng của nhân sự ────────────────────────────────────────────────────

it('bảng quỹ phép hiện số dư năm trước để nhân sự khỏi tự tính', function (): void {
    // Không trả thì nhân sự phải đổi năm, ghi ra giấy, rồi đổi về — và một phần
    // sẽ gõ nhầm.
    [$sep, $nv] = sepVaNhanVien();
    $nv->forceFill(['joined_at' => '2024-01-01'])->save();
    donNghi($nv, '2025-09-12', '2025-09-15');

    $this->actingAs($sep)
        ->getJson('/api/v1/leave/balances')
        ->assertOk()
        ->assertJsonPath('data.year', 2026)
        ->assertJsonPath('data.can_manage', false)
        ->assertJsonPath('data.policy.base_days', 12)
        ->assertJsonStructure([
            'data' => [
                'balances' => [[
                    'year', 'entitled_days', 'computed_entitled_days', 'is_overridden',
                    'carried_over_days', 'adjustment_days', 'total_days', 'used_days',
                    'remaining_days', 'previous_remaining_days',
                    'user' => ['id', 'name', 'department', 'joined_at'],
                ]],
                'total', 'limit', 'can_manage', 'policy',
            ],
        ]);
});

it('bảng quỹ phép chỉ hiện người trong phạm vi quản lý', function (): void {
    [$sep] = sepVaNhanVien();
    nguoiNgoai();

    $cuaDoi = $this->actingAs($sep)->getJson('/api/v1/leave/balances')->assertOk();

    // Trưởng phòng và nhân viên của phòng đó, không có người phòng khác.
    expect($cuaDoi->json('data.total'))->toBe(2);
});

it('giám đốc thấy cả công ty và sửa được', function (): void {
    sepVaNhanVien();

    $this->actingAs(giamDoc())
        ->getJson('/api/v1/leave/balances')
        ->assertOk()
        ->assertJsonPath('data.can_manage', true);
});

it('nhân viên thường không mở được bảng quỹ phép của người khác', function (): void {
    [, $nv] = sepVaNhanVien();

    $this->actingAs($nv)->getJson('/api/v1/leave/balances')->assertForbidden();
    // Nhưng quỹ của chính mình thì luôn xem được.
    $this->actingAs($nv)->getJson('/api/v1/leave/balance')->assertOk();
});

it('đổi năm thì xem được quỹ của năm khác', function (): void {
    $nv = nhanVienVaoLam('2024-01-01');

    $this->actingAs($nv)
        ->getJson('/api/v1/leave/balance?year=2025')
        ->assertOk()
        ->assertJsonPath('data.year', 2025);
});
