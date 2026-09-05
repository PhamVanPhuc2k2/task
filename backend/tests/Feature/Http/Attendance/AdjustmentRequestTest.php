<?php

declare(strict_types=1);

use App\Domain\Attendance\Enums\AttendanceDecision;
use App\Domain\Attendance\Enums\RequestStatus;
use App\Domain\Attendance\Models\AttendanceAdjustment;
use App\Domain\Attendance\Models\AttendancePeriod;
use App\Domain\Attendance\Models\WorkDay;
use App\Domain\Attendance\Notifications\AdjustmentRequestedNotification;
use App\Domain\Attendance\Notifications\AdjustmentReviewedNotification;
use App\Domain\Identity\Models\User;
use App\Domain\Leave\Enums\LeaveStatus;
use App\Domain\Leave\Enums\LeaveType;
use App\Domain\Leave\Models\LeaveRequest;
use Carbon\CarbonImmutable;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Notification;

/*
|--------------------------------------------------------------------------
| Đơn giải trình công
|--------------------------------------------------------------------------
|
| Trước module này, `work_days` chỉ có MỘT cửa vào: người quản lý bấm nút. Nhân
| viên đi gặp khách cả ngày, mất mạng, hay quên mở máy thì không có đường nào
| nói điều đó trong hệ thống — họ nhắn Zalo, quản lý nhớ thì bấm, quên thì thôi.
| Lý do thật của một ngày công bất thường nằm trong lịch sử chat của hai người.
|
| Từ khi có chốt sổ kỳ công, chuyện đó thành HẠN CHÓT CỨNG: chốt rồi thì không
| ai duyệt được nữa, kể cả giám đốc. Nên nhân viên phải có đường tự khởi xướng.
|
| Bốn ranh giới được khoá ở đây:
|
| 1. Duyệt thì ghi `work_days` qua ĐÚNG đường nút bấm tay vẫn đi, và số vào bảng
|    công là số NGƯỜI DUYỆT chốt — không phải số nhân viên tự khai.
|
| 2. Từ chối thì KHÔNG ghi gì vào `work_days`, nhưng đơn nằm lại làm vết.
|
| 3. Chốt sổ bị CHẶN khi kỳ còn đơn treo. Đây là hạng mục quan trọng nhất: chốt
|    sổ khoá luôn đơn từ, nên đơn treo qua ngày chốt là đơn không ai duyệt được
|    nữa, vĩnh viễn.
|
| 4. Kỳ đã chốt thì không nộp và không duyệt được.
|
| Mốc: 02/09/2026. Kỳ 2026-08 đã kết thúc; 2026-09 thì chưa.
|
*/

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->withHeader('Origin', 'http://localhost:3000');
    $this->travelTo(CarbonImmutable::parse('2026-09-02 09:00:00'));
});

/** Một đơn giải trình có sẵn trong database. */
function donGiaiTrinh(
    User $u,
    string $ngay = '2026-08-20',
    RequestStatus $trangThai = RequestStatus::Pending,
    ?int $soPhut = 480,
): AttendanceAdjustment {
    return AttendanceAdjustment::query()->create([
        'user_id' => $u->id,
        'work_date' => $ngay,
        'reason' => 'Cả ngày ở chỗ khách hàng hướng dẫn vận hành website.',
        'requested_minutes' => $soPhut,
        'status' => $trangThai,
        'reviewed_at' => $trangThai === RequestStatus::Pending ? null : now(),
    ]);
}

/**
 * Thân một đơn hợp lệ để POST.
 *
 * @return array<string, mixed>
 */
function thanDon(string $ngay = '2026-08-20', ?int $soPhut = 480): array
{
    $than = [
        'work_date' => $ngay,
        'reason' => 'Cả ngày ở chỗ khách hàng hướng dẫn vận hành website.',
    ];

    return $soPhut === null ? $than : $than + ['requested_minutes' => $soPhut];
}

// ── Nộp đơn ─────────────────────────────────────────────────────────────

it('nhân viên nộp được đơn giải trình cho một ngày đã qua', function (): void {
    [, $nv] = sepVaNhanVien();

    $this->actingAs($nv)
        ->postJson('/api/v1/attendance/adjustments', thanDon())
        ->assertCreated()
        ->assertJsonPath('data.work_date', '2026-08-20')
        ->assertJsonPath('data.requested_minutes', 480)
        ->assertJsonPath('data.status', RequestStatus::Pending->value)
        ->assertJsonPath('data.is_editable', true);
});

it('nộp được đơn KHÔNG kèm số phút đề nghị', function (): void {
    /*
    | Điểm chính của cột `requested_minutes` nullable: người đi gặp khách cả
    | ngày không đếm phút. Bắt nhập thì họ điền một con số bịa cho xong, và
    | người duyệt mất luôn tín hiệu "người này không khẳng định con số nào".
    */
    [, $nv] = sepVaNhanVien();

    $this->actingAs($nv)
        ->postJson('/api/v1/attendance/adjustments', thanDon(soPhut: null))
        ->assertCreated()
        ->assertJsonPath('data.requested_minutes', null);
});

it('KHÔNG giải trình được cho một ngày chưa xảy ra', function (): void {
    [, $nv] = sepVaNhanVien();

    $this->actingAs($nv)
        ->postJson('/api/v1/attendance/adjustments', thanDon('2026-09-30'))
        ->assertJsonValidationErrors('work_date');
});

it('hôm nay thì giải trình được — cận trên là ngày công GIỜ VIỆT NAM', function (): void {
    /*
    | `before_or_equal:today` của Laravel so theo múi giờ ứng dụng (UTC). Test
    | này khoá HÀNH VI — hôm nay phải nộp được — để lần ai đó đổi sang `today`
    | thì bộ test chạy lúc 1h sáng giờ Việt Nam sẽ đỏ thay vì người dùng phát
    | hiện ra.
    */
    [, $nv] = sepVaNhanVien();

    $this->actingAs($nv)
        ->postJson('/api/v1/attendance/adjustments', thanDon('2026-09-02'))
        ->assertCreated();
});

it('không nộp hai đơn còn hiệu lực cho cùng một ngày', function (): void {
    [, $nv] = sepVaNhanVien();
    donGiaiTrinh($nv);

    $this->actingAs($nv)
        ->postJson('/api/v1/attendance/adjustments', thanDon())
        ->assertJsonValidationErrors('work_date');
});

it('bị từ chối rồi thì giải trình lại được cho đúng ngày đó', function (): void {
    // Đơn bị từ chối KHÔNG chặn chỗ: giải trình lại cho rõ hơn là chuyện bình
    // thường, và cấm điều đó thì người ta quay về nhắn tin cho quản lý — đúng
    // cái việc module này sinh ra để thay.
    [, $nv] = sepVaNhanVien();
    donGiaiTrinh($nv, trangThai: RequestStatus::Rejected);

    $this->actingAs($nv)
        ->postJson('/api/v1/attendance/adjustments', thanDon())
        ->assertCreated();
});

it('lý do quá ngắn thì bị chặn', function (): void {
    [, $nv] = sepVaNhanVien();

    $this->actingAs($nv)
        ->postJson('/api/v1/attendance/adjustments', ['work_date' => '2026-08-20', 'reason' => 'bận'])
        ->assertJsonValidationErrors('reason');
});

it('quản lý trực tiếp nhận được thông báo khi có đơn mới', function (): void {
    Notification::fake();

    [$sep, $nv] = sepVaNhanVien();
    $nv->forceFill(['manager_id' => $sep->id])->save();

    $this->actingAs($nv)
        ->postJson('/api/v1/attendance/adjustments', thanDon())
        ->assertCreated();

    Notification::assertSentTo($sep, AdjustmentRequestedNotification::class);
});

// ── Duyệt ───────────────────────────────────────────────────────────────

it('duyệt thì ghi vào bảng công, với số phút của NGƯỜI DUYỆT', function (): void {
    /*
    | Giao diện điền sẵn `requested_minutes` cho tiện, nhưng cái đi vào
    | `work_days` là cái người duyệt gửi lên. Nếu không thì "duyệt" chỉ còn
    | nghĩa là "đồng ý với mọi con số nhân viên tự khai".
    */
    [$sep, $nv] = sepVaNhanVien();
    $don = donGiaiTrinh($nv, soPhut: 480);

    $this->actingAs($sep)
        ->postJson("/api/v1/attendance/adjustments/{$don->uuid}/review", [
            'approve' => true,
            'minutes' => 300,
            'note' => 'Xác nhận có lịch gặp khách, nhưng chỉ nửa ngày.',
        ])
        ->assertOk()
        ->assertJsonPath('data.status', RequestStatus::Approved->value)
        ->assertJsonPath('data.review.approved_minutes', 300);

    $ngay = WorkDay::query()
        ->where('user_id', $nv->id)
        ->where('work_date', '2026-08-20')
        ->first();

    expect($ngay)->not->toBeNull()
        ->and($ngay?->decision)->toBe(AttendanceDecision::Waived)
        ->and($ngay?->adjusted_minutes)->toBe(300)
        ->and($ngay?->reviewed_by)->toBe($sep->id)
        // Lý do trên bảng công phải trỏ về đơn, nếu không thì sáu tháng sau
        // người mở bảng thấy một con số không biết từ đâu ra.
        ->and($ngay?->reason)->toContain('đơn giải trình');
});

it('duyệt mà không ấn định số phút thì bảng công giữ nguyên số đo được', function (): void {
    [$sep, $nv] = sepVaNhanVien();
    $don = donGiaiTrinh($nv, soPhut: null);

    $this->actingAs($sep)
        ->postJson("/api/v1/attendance/adjustments/{$don->uuid}/review", ['approve' => true])
        ->assertOk();

    $ngay = WorkDay::query()->where('user_id', $nv->id)->first();

    expect($ngay?->decision)->toBe(AttendanceDecision::Waived)
        ->and($ngay?->adjusted_minutes)->toBeNull();
});

it('từ chối thì KHÔNG ghi gì vào bảng công', function (): void {
    [$sep, $nv] = sepVaNhanVien();
    $don = donGiaiTrinh($nv);

    $this->actingAs($sep)
        ->postJson("/api/v1/attendance/adjustments/{$don->uuid}/review", [
            'approve' => false,
            'note' => 'Hôm đó không có lịch gặp khách nào trên hệ thống.',
        ])
        ->assertOk()
        ->assertJsonPath('data.status', RequestStatus::Rejected->value);

    expect(WorkDay::query()->count())->toBe(0);
});

it('từ chối bắt buộc ghi lý do', function (): void {
    [$sep, $nv] = sepVaNhanVien();
    $don = donGiaiTrinh($nv);

    $this->actingAs($sep)
        ->postJson("/api/v1/attendance/adjustments/{$don->uuid}/review", ['approve' => false])
        ->assertJsonValidationErrors('note');
});

it('người nộp nhận được thông báo khi đơn bị từ chối', function (): void {
    // Trường hợp quan trọng nhất: không báo thì người ta đinh ninh đã giải
    // trình xong, và chỉ phát hiện ra khi bảng lương về — lúc kỳ đã chốt.
    Notification::fake();

    [$sep, $nv] = sepVaNhanVien();
    $don = donGiaiTrinh($nv);

    $this->actingAs($sep)
        ->postJson("/api/v1/attendance/adjustments/{$don->uuid}/review", [
            'approve' => false,
            'note' => 'Hôm đó không có lịch gặp khách nào trên hệ thống.',
        ])
        ->assertOk();

    Notification::assertSentTo($nv, AdjustmentReviewedNotification::class);
});

it('không tự duyệt đơn của chính mình', function (): void {
    // KHÔNG suy ra được từ phép kiểm phạm vi: trưởng phòng luôn nằm trong phòng
    // của chính mình.
    [$sep] = sepVaNhanVien();
    $don = donGiaiTrinh($sep);

    $this->actingAs($sep)
        ->postJson("/api/v1/attendance/adjustments/{$don->uuid}/review", ['approve' => true])
        ->assertForbidden();
});

it('không duyệt được đơn của phòng khác', function (): void {
    [$sep] = sepVaNhanVien();
    $don = donGiaiTrinh(nguoiNgoai());

    $this->actingAs($sep)
        ->postJson("/api/v1/attendance/adjustments/{$don->uuid}/review", ['approve' => true])
        ->assertForbidden();
});

it('không duyệt lại được đơn đã xử lý', function (): void {
    [$sep, $nv] = sepVaNhanVien();
    $don = donGiaiTrinh($nv, trangThai: RequestStatus::Approved);

    $this->actingAs($sep)
        ->postJson("/api/v1/attendance/adjustments/{$don->uuid}/review", ['approve' => true])
        ->assertStatus(422);
});

// ── Rút đơn ─────────────────────────────────────────────────────────────

it('người nộp rút được đơn đang chờ, nhưng không rút được đơn đã duyệt', function (): void {
    [, $nv] = sepVaNhanVien();

    $cho = donGiaiTrinh($nv, '2026-08-20');
    $daDuyet = donGiaiTrinh($nv, '2026-08-21', RequestStatus::Approved);

    $this->actingAs($nv)
        ->postJson("/api/v1/attendance/adjustments/{$cho->uuid}/cancel")
        ->assertOk()
        ->assertJsonPath('data.status', RequestStatus::Cancelled->value);

    $this->actingAs($nv)
        ->postJson("/api/v1/attendance/adjustments/{$daDuyet->uuid}/cancel")
        ->assertStatus(422);
});

it('không rút được đơn của người khác', function (): void {
    [, $nv] = sepVaNhanVien();
    $don = donGiaiTrinh($nv);

    $this->actingAs(nguoiNgoai())
        ->postJson("/api/v1/attendance/adjustments/{$don->uuid}/cancel")
        ->assertForbidden();
});

// ── Hộp duyệt ───────────────────────────────────────────────────────────

it('hộp duyệt trả đúng hình dạng data.requests, và nhân viên thường không vào được', function (): void {
    /*
    | Khoá HÌNH DẠNG, không chỉ khoá nội dung. `/late-arrivals/team` từng trả
    | `data` là một mảng kèm `meta` riêng trong khi các đường cùng họ theo dạng
    | `data: { requests, ... }` — và `undefined.length` làm sập cả tab, nhưng
    | CHỈ với người có quyền duyệt, nên lỗi sống tới lúc có người duyệt mở ra.
    */
    [$sep, $nv] = sepVaNhanVien();
    donGiaiTrinh($nv);

    $this->actingAs($sep)
        ->getJson('/api/v1/attendance/adjustments/team')
        ->assertOk()
        ->assertJsonCount(1, 'data.requests')
        ->assertJsonPath('data.total', 1)
        ->assertJsonPath('data.pending', 1)
        ->assertJsonPath('data.requests.0.user.name', $nv->name)
        ->assertJsonStructure(['data' => ['requests', 'total', 'limit', 'pending']]);

    $this->actingAs($nv)
        ->getJson('/api/v1/attendance/adjustments/team')
        ->assertForbidden();
});

it('màn đơn của tôi chỉ trả đơn của mình, kèm tổng và trần', function (): void {
    [, $nv] = sepVaNhanVien();
    donGiaiTrinh($nv);
    donGiaiTrinh(nguoiNgoai());

    $this->actingAs($nv)
        ->getJson('/api/v1/attendance/adjustments/me')
        ->assertOk()
        ->assertJsonCount(1, 'data.requests')
        ->assertJsonPath('data.total', 1)
        ->assertJsonPath('data.latest_date', '2026-09-02');
});

// ── Giao với chốt sổ kỳ công ────────────────────────────────────────────

it('kỳ đã chốt thì không nộp và không duyệt được đơn giải trình', function (): void {
    khoaKy();

    [$sep, $nv] = sepVaNhanVien();

    $this->actingAs($nv)
        ->postJson('/api/v1/attendance/adjustments', thanDon())
        ->assertJsonValidationErrors('work_date');

    $don = donGiaiTrinh($nv);

    $this->actingAs($sep)
        ->postJson("/api/v1/attendance/adjustments/{$don->uuid}/review", ['approve' => true])
        ->assertJsonValidationErrors('work_date');
});

it('KHÔNG chốt được kỳ còn đơn giải trình chờ duyệt', function (): void {
    /*
    | Hạng mục quan trọng nhất của module.
    |
    | Chốt sổ khoá cả đơn từ, nên một đơn treo qua ngày chốt là đơn KHÔNG AI
    | DUYỆT ĐƯỢC NỮA — kể cả giám đốc, trừ khi mở khoá lại cả kỳ. Cách hỏng điển
    | hình: giám đốc chốt tháng 8 vào ngày 02/09, ba đơn nộp hôm 31/08 chết
    | theo, và ba người đó phát hiện ra khi bảng lương về.
    */
    [, $nv] = sepVaNhanVien();
    donGiaiTrinh($nv, '2026-08-31');

    $this->actingAs(giamDoc())
        ->postJson('/api/v1/attendance/periods/close', ['period' => '2026-08'])
        ->assertJsonValidationErrors('period');

    expect(AttendancePeriod::query()->count())->toBe(0);
});

it('đơn nghỉ còn chờ duyệt cũng chặn chốt sổ', function (): void {
    // Duyệt một đơn nghỉ sau khi chốt sẽ đổi số ngày công đã dùng để tính lương
    // — mà duyệt sau khi chốt đã bị chặn. Nên nó cũng mắc kẹt y hệt.
    //
    // Đơn này vắt hai kỳ (28/08 → 01/09): phép đếm phải bắt theo GIAO NHAU, chỉ
    // lọc ngày bắt đầu thì đơn vắt kỳ lọt qua.
    [, $nv] = sepVaNhanVien();

    LeaveRequest::query()->create([
        'user_id' => $nv->id,
        'type' => LeaveType::Annual,
        'start_date' => '2026-08-28',
        'end_date' => '2026-09-01',
        'reason' => 'Về quê có việc gia đình.',
        'status' => LeaveStatus::Pending,
    ]);

    $this->actingAs(giamDoc())
        ->postJson('/api/v1/attendance/periods/close', ['period' => '2026-08'])
        ->assertJsonValidationErrors('period');
});

it('xử lý hết đơn rồi thì chốt được', function (): void {
    [$sep, $nv] = sepVaNhanVien();
    $don = donGiaiTrinh($nv, '2026-08-31');

    $this->actingAs($sep)
        ->postJson("/api/v1/attendance/adjustments/{$don->uuid}/review", ['approve' => true])
        ->assertOk();

    $this->actingAs(giamDoc())
        ->postJson('/api/v1/attendance/periods/close', ['period' => '2026-08'])
        ->assertOk()
        ->assertJsonPath('data.is_locked', true);
});

it('kỳ chưa kết thúc thì báo "chưa kết thúc", không báo "còn đơn treo"', function (): void {
    // Thứ tự lỗi có chủ ý: kỳ đang chạy thì còn đơn treo là chuyện đương nhiên,
    // và nói "còn đơn treo" sẽ khiến người ta đi xử lý đơn một cách vô ích.
    [, $nv] = sepVaNhanVien();
    donGiaiTrinh($nv, '2026-09-01');

    $this->actingAs(giamDoc())
        ->postJson('/api/v1/attendance/periods/close', ['period' => '2026-09'])
        ->assertJsonValidationErrors('period')
        ->assertJsonFragment([
            'period' => ['Kỳ công tháng 09/2026 chưa kết thúc nên chưa chốt sổ được.'],
        ]);
});

it('màn chốt sổ nói ra kỳ nào sắp chốt và còn bao nhiêu đơn treo', function (): void {
    // Giao diện KHÔNG tự tính kỳ này: nó phải biết hôm nay là ngày mấy theo giờ
    // Việt Nam, và biết kỳ nào đã chốt — thứ trình duyệt không suy ra được.
    [, $nv] = sepVaNhanVien();
    donGiaiTrinh($nv, '2026-08-31');

    $this->actingAs(giamDoc())
        ->getJson('/api/v1/attendance/periods')
        ->assertOk()
        ->assertJsonPath('data.closable.period', '2026-08')
        ->assertJsonPath('data.closable.ready', false)
        ->assertJsonPath('data.closable.pending.đơn giải trình công', 1);
});

it('không nộp hộ được đơn cho người khác', function (): void {
    // Không có tham số người dùng trên đường dẫn; người nộp luôn là
    // $request->user(). Test này khoá điều đó lại — nhóm "chỉ thao tác trên dữ
    // liệu của chính mình" trong ControllerAuthorizationTest dựa vào nó.
    [, $nv] = sepVaNhanVien();
    $nguoiKhac = nguoiNgoai();

    $this->actingAs($nv)
        ->postJson('/api/v1/attendance/adjustments', thanDon() + ['user_id' => $nguoiKhac->id])
        ->assertCreated();

    expect(AttendanceAdjustment::query()->where('user_id', $nguoiKhac->id)->count())->toBe(0)
        ->and(AttendanceAdjustment::query()->where('user_id', $nv->id)->count())->toBe(1);
});

it('nhân viên thường không có quyền duyệt đơn', function (): void {
    [, $nv] = sepVaNhanVien();
    $don = donGiaiTrinh(nguoiNgoai());

    $this->actingAs($nv)
        ->postJson("/api/v1/attendance/adjustments/{$don->uuid}/review", ['approve' => true])
        ->assertForbidden();
});
