<?php

declare(strict_types=1);

use App\Domain\Attendance\Actions\SubmitOvertimeAction;
use App\Domain\Attendance\Enums\RequestStatus;
use App\Domain\Attendance\Models\Holiday;
use App\Domain\Attendance\Models\OvertimeRequest;
use App\Domain\Attendance\Notifications\OvertimeRequestedNotification;
use App\Domain\Attendance\Notifications\OvertimeReviewedNotification;
use App\Domain\Identity\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Notification;

/*
|--------------------------------------------------------------------------
| Đăng ký làm thêm giờ
|--------------------------------------------------------------------------
|
| Làm thêm giờ ra tiền ở mức 150–300% (Điều 98 Bộ luật Lao động 2019). Suy nó
| từ giờ ngồi trước máy là để hệ thống tự ký một khoản chi mà không ai quyết
| định — và một cái tab quên đóng qua đêm sẽ thành mười tiếng làm thêm ngày
| nghỉ. Nên đây là một ĐƠN: đăng ký trước, duyệt rồi mới tính.
|
| Năm ranh giới được khoá ở đây:
|
| 1. Hệ số suy từ LOẠI NGÀY — thường 150%, nghỉ tuần 200%, lễ 300%. Ngày lễ
|    thắng cả hai loại kia.
|
| 2. Hệ số ĐÓNG BĂNG lúc duyệt, không phải lúc đăng ký: đó là thời điểm công ty
|    cam kết trả. Lịch đổi sau đó không làm đơn cũ đổi nghĩa.
|
| 3. Giờ làm thêm phải NGOÀI CA. "Làm thêm 9h–11h" ngày thường là hai tiếng đã
|    được trả lương bình thường rồi.
|
| 4. Ba trần của Điều 107: ngày, tháng, năm. Đếm cả đơn đang chờ duyệt.
|
| 5. Không hai khoảng giờ chồng lấn trong cùng một ngày — cộng cả hai là trả
|    tiền hai lần cho cùng một giờ làm.
|
| Mốc: 02/09/2026. Lịch tuần: T2–T6 cả ngày, T7 nửa buổi, CN nghỉ. Ca 08:15–17:30.
| 11/09 là thứ sáu, 12/09 thứ bảy, 13/09 chủ nhật, 14/09 thứ hai.
|
*/

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->withHeader('Origin', 'http://localhost:3000');
    $this->travelTo(CarbonImmutable::parse('2026-09-02 09:00:00'));
});

/**
 * Thân một đơn hợp lệ để POST.
 *
 * @return array<string, string>
 */
function thanOT(
    string $ngay = '2026-09-11',
    string $tu = '18:00',
    string $den = '20:00',
): array {
    return [
        'work_date' => $ngay,
        'start_time' => $tu,
        'end_time' => $den,
        'reason' => 'Chốt bản demo cho khách sáng mai, còn phần báo cáo chưa xong.',
    ];
}

/** Một đơn làm thêm có sẵn trong database. */
function donOT(
    User $u,
    string $ngay = '2026-09-11',
    string $tu = '18:00',
    string $den = '20:00',
    RequestStatus $trangThai = RequestStatus::Pending,
): OvertimeRequest {
    return OvertimeRequest::query()->create([
        'user_id' => $u->id,
        'work_date' => $ngay,
        'start_time' => $tu,
        'end_time' => $den,
        'minutes' => SubmitOvertimeAction::phutGiua($tu, $den),
        'reason' => 'Chốt bản demo cho khách sáng mai.',
        'status' => $trangThai,
        'reviewed_at' => $trangThai === RequestStatus::Pending ? null : now(),
    ]);
}

/** Đánh dấu một ngày là nghỉ lễ. */
function ngayLe(string $ngay): Holiday
{
    return Holiday::query()->create([
        'date' => $ngay,
        'observed_date' => $ngay,
        'name' => 'Ngày thử',
        'is_paid' => true,
    ]);
}

// ── Hệ số theo loại ngày ────────────────────────────────────────────────

it('ngày làm việc thì hệ số 150%', function (): void {
    [, $nv] = sepVaNhanVien();

    $this->actingAs($nv)
        ->postJson('/api/v1/attendance/overtime', thanOT())
        ->assertCreated()
        ->assertJsonPath('data.minutes', 120)
        ->assertJsonPath('data.day_kind', 'working')
        ->assertJsonPath('data.rate_percent', 150)
        // Chưa duyệt thì con số này còn đổi được — giao diện nói "dự kiến".
        ->assertJsonPath('data.rate_is_final', false)
        ->assertJsonPath('data.status', RequestStatus::Pending->value);
});

it('chủ nhật thì hệ số 200%, và giờ nào cũng là làm thêm', function (): void {
    // Ngày nghỉ hằng tuần không có ca, nên không có phép kiểm "ngoài ca" nào.
    [, $nv] = sepVaNhanVien();

    $this->actingAs($nv)
        ->postJson('/api/v1/attendance/overtime', thanOT('2026-09-13', '09:00', '11:00'))
        ->assertCreated()
        ->assertJsonPath('data.day_kind', 'weekly_rest')
        ->assertJsonPath('data.rate_percent', 200);
});

it('thứ bảy nửa buổi vẫn là NGÀY LÀM VIỆC, hệ số 150%', function (): void {
    /*
    | Quyết định về tiền, nên phải khoá lại bằng test.
    |
    | Sáng thứ bảy có ca nên thứ bảy là ngày làm việc bình thường; ngày nghỉ
    | hằng tuần của công ty là chủ nhật. Điều 111 chỉ đòi mỗi tuần nghỉ ít nhất
    | một ngày, nên cách xếp này hợp lệ — nhưng nó là chênh lệch 50% tiền công.
    */
    [, $nv] = sepVaNhanVien();

    $this->actingAs($nv)
        ->postJson('/api/v1/attendance/overtime', thanOT('2026-09-12', '14:00', '16:00'))
        ->assertCreated()
        ->assertJsonPath('data.day_kind', 'working')
        ->assertJsonPath('data.rate_percent', 150);
});

it('ngày lễ thì hệ số 300%, thắng cả ngày thường', function (): void {
    ngayLe('2026-09-14');

    [, $nv] = sepVaNhanVien();

    $this->actingAs($nv)
        ->postJson('/api/v1/attendance/overtime', thanOT('2026-09-14', '09:00', '11:00'))
        ->assertCreated()
        ->assertJsonPath('data.day_kind', 'holiday')
        ->assertJsonPath('data.rate_percent', 300);
});

it('cấu hình hệ số dưới mức sàn thì bị kẹp lên mức luật định', function (): void {
    // Trả dưới mức sàn là trái luật. Một con số gõ nhầm ở màn Cài đặt không
    // được biến thành một sai phạm im lặng kéo dài tới khi có người khiếu nại.
    config(['attendance.overtime.rate_working_percent' => 100]);

    [, $nv] = sepVaNhanVien();

    $this->actingAs($nv)
        ->postJson('/api/v1/attendance/overtime', thanOT())
        ->assertCreated()
        ->assertJsonPath('data.rate_percent', 150);
});

// ── Giờ phải nằm ngoài ca ───────────────────────────────────────────────

it('giờ nằm trong ca ngày làm việc thì bị chặn', function (): void {
    // "Làm thêm 9h–11h" ngày thường là hai tiếng đã được trả lương bình thường
    // rồi — trả thêm 150% cho nó là trả hai lần cho cùng một giờ làm.
    [, $nv] = sepVaNhanVien();

    $this->actingAs($nv)
        ->postJson('/api/v1/attendance/overtime', thanOT('2026-09-11', '09:00', '11:00'))
        ->assertJsonValidationErrors('start_time');
});

it('bắt đầu đúng giờ tan ca thì hợp lệ', function (): void {
    // Chạm biên KHÔNG tính là giao nhau: làm thêm từ đúng giờ tan ca là trường
    // hợp thường gặp nhất, chặn nó là chặn đúng cái đường chính.
    [, $nv] = sepVaNhanVien();

    $this->actingAs($nv)
        ->postJson('/api/v1/attendance/overtime', thanOT('2026-09-11', '17:30', '19:30'))
        ->assertCreated();
});

it('làm thêm buổi sáng trước giờ vào ca cũng hợp lệ', function (): void {
    [, $nv] = sepVaNhanVien();

    $this->actingAs($nv)
        ->postJson('/api/v1/attendance/overtime', thanOT('2026-09-11', '06:00', '08:00'))
        ->assertCreated();
});

it('giờ kết thúc trước giờ bắt đầu thì bị chặn', function (): void {
    // Ca vắt qua nửa đêm chưa hỗ trợ: nó cần cả phụ cấp làm đêm lẫn quy tắc
    // chia phần cho hai ngày công. Chặn thẳng còn hơn nhận một đơn ra số phút âm.
    [, $nv] = sepVaNhanVien();

    $this->actingAs($nv)
        ->postJson('/api/v1/attendance/overtime', thanOT('2026-09-11', '22:00', '02:00'))
        ->assertJsonValidationErrors('end_time');
});

// ── Chồng lấn ───────────────────────────────────────────────────────────

it('hai khoảng giờ chồng lấn thì bị chặn', function (): void {
    [, $nv] = sepVaNhanVien();
    donOT($nv, '2026-09-11', '18:00', '20:00');

    $this->actingAs($nv)
        ->postJson('/api/v1/attendance/overtime', thanOT('2026-09-11', '19:00', '21:00'))
        ->assertJsonValidationErrors('start_time');
});

it('hai khoảng giờ KỀ NHAU thì không sao', function (): void {
    // Một người làm thêm hai lần trong ngày là chuyện có thật. Thứ bị cấm là
    // hai đơn phủ cùng một khoảng giờ, không phải hai đơn trong cùng một ngày.
    [, $nv] = sepVaNhanVien();
    donOT($nv, '2026-09-11', '18:00', '19:00');

    $this->actingAs($nv)
        ->postJson('/api/v1/attendance/overtime', thanOT('2026-09-11', '19:00', '20:00'))
        ->assertCreated();
});

it('đơn bị từ chối thì trả lại chỗ', function (): void {
    [, $nv] = sepVaNhanVien();
    donOT($nv, '2026-09-11', '18:00', '20:00', RequestStatus::Rejected);

    $this->actingAs($nv)
        ->postJson('/api/v1/attendance/overtime', thanOT('2026-09-11', '18:00', '20:00'))
        ->assertCreated();
});

// ── Ba trần của Điều 107 ────────────────────────────────────────────────

it('vượt trần MỖI NGÀY thì bị chặn, và câu lỗi gọi tên trần đó', function (): void {
    // Trần mặc định 240 phút = 50% của 8 tiếng làm việc bình thường.
    [, $nv] = sepVaNhanVien();
    donOT($nv, '2026-09-11', '18:00', '20:00');

    $this->actingAs($nv)
        ->postJson('/api/v1/attendance/overtime', thanOT('2026-09-11', '20:00', '22:30'))
        ->assertJsonValidationErrors('start_time')
        ->assertJsonFragment(['start_time' => [
            'Ngày 11/09/2026 đã đăng ký 2 giờ làm thêm, xin thêm 2 giờ 30 phút là vượt trần 4 giờ mỗi ngày theo Bộ luật Lao động.',
        ]]);
});

it('vượt trần MỖI THÁNG thì bị chặn', function (): void {
    config(['attendance.overtime.max_minutes_per_month' => 180]);

    [, $nv] = sepVaNhanVien();
    donOT($nv, '2026-09-11', '18:00', '20:00');

    $this->actingAs($nv)
        ->postJson('/api/v1/attendance/overtime', thanOT('2026-09-10', '18:00', '20:00'))
        ->assertJsonValidationErrors('start_time')
        ->assertJsonFragment(['start_time' => [
            'Trong tháng 09/2026 đã đăng ký 2 giờ làm thêm, xin thêm 2 giờ là vượt trần 3 giờ mỗi tháng theo Bộ luật Lao động.',
        ]]);
});

it('vượt trần MỖI NĂM thì bị chặn', function (): void {
    config(['attendance.overtime.max_minutes_per_year' => 180]);

    [, $nv] = sepVaNhanVien();
    donOT($nv, '2026-09-11', '18:00', '20:00');

    $this->actingAs($nv)
        ->postJson('/api/v1/attendance/overtime', thanOT('2026-09-10', '18:00', '20:00'))
        ->assertJsonValidationErrors('start_time')
        ->assertJsonFragment(['start_time' => [
            'Năm 2026 đã đăng ký 2 giờ làm thêm, xin thêm 2 giờ là vượt trần 3 giờ mỗi năm theo Bộ luật Lao động.',
        ]]);
});

it('đơn ĐANG CHỜ DUYỆT cũng tính vào trần', function (): void {
    // Chỉ đếm đơn đã duyệt thì nộp năm đơn nhỏ cùng lúc là lách được — mỗi đơn
    // nhìn riêng đều nằm trong trần, và người duyệt phải tự cộng nhẩm.
    config(['attendance.overtime.max_minutes_per_day' => 180]);

    [, $nv] = sepVaNhanVien();
    donOT($nv, '2026-09-11', '18:00', '20:00', RequestStatus::Pending);

    // 120 đã đăng ký + 90 xin thêm = 210, vượt trần 180.
    $this->actingAs($nv)
        ->postJson('/api/v1/attendance/overtime', thanOT('2026-09-11', '20:00', '21:30'))
        ->assertJsonValidationErrors('start_time');
});

// ── Duyệt ───────────────────────────────────────────────────────────────

it('duyệt thì ĐÓNG BĂNG hệ số và số phút', function (): void {
    [$sep, $nv] = sepVaNhanVien();
    $don = donOT($nv, '2026-09-13', '09:00', '12:00');

    $this->actingAs($sep)
        ->postJson("/api/v1/attendance/overtime/{$don->uuid}/review", ['approve' => true])
        ->assertOk()
        ->assertJsonPath('data.status', RequestStatus::Approved->value)
        ->assertJsonPath('data.rate_percent', 200)
        ->assertJsonPath('data.rate_is_final', true)
        ->assertJsonPath('data.review.approved_minutes', 180);
});

it('lịch đổi sau khi duyệt KHÔNG làm đơn cũ đổi nghĩa', function (): void {
    /*
    | Điểm chính của việc đóng băng hệ số.
    |
    | Duyệt lúc 11/09 còn là ngày thường (150%). Nhân sự nhập thêm ngày lễ sau
    | đó. Đơn đã duyệt vẫn giữ 150% — đó là con số công ty đã cam kết trả, và
    | tính lại theo lịch của hôm nay nghĩa là bảng lương đổi sau khi đã chốt.
    */
    [$sep, $nv] = sepVaNhanVien();
    $don = donOT($nv, '2026-09-11', '18:00', '20:00');

    $this->actingAs($sep)
        ->postJson("/api/v1/attendance/overtime/{$don->uuid}/review", ['approve' => true])
        ->assertOk()
        ->assertJsonPath('data.rate_percent', 150);

    ngayLe('2026-09-11');

    /*
    | Không kiểm `day_kind` ở đây, có lý do.
    |
    | `CompanyWorkCalendar` nhớ danh sách ngày lễ theo năm trong bộ nhớ, và bộ
    | nhớ đó sống theo vòng đời của đối tượng — mỗi request một lần ở
    | production, nhưng trong test thì container dùng chung cho cả bài. Nên loại
    | ngày ở đây vẫn là con số đã nhớ từ lượt gọi trước.
    |
    | Thứ bài này khoá là TIỀN, và tiền thì không đổi.
    */
    $this->actingAs($nv)
        ->getJson('/api/v1/attendance/overtime/me')
        ->assertOk()
        ->assertJsonPath('data.requests.0.rate_percent', 150)
        ->assertJsonPath('data.requests.0.rate_is_final', true);
});

it('duyệt ÍT HƠN số đăng ký được, nhiều hơn thì không', function (): void {
    // Cho duyệt nhiều hơn là mở một đường vòng qua ba cái trần đã kiểm lúc nộp.
    [$sep, $nv] = sepVaNhanVien();
    $don = donOT($nv, '2026-09-11', '18:00', '20:00');

    $this->actingAs($sep)
        ->postJson("/api/v1/attendance/overtime/{$don->uuid}/review", [
            'approve' => true,
            'minutes' => 60,
            'note' => 'Xác nhận có ở lại nhưng chỉ một tiếng.',
        ])
        ->assertOk()
        ->assertJsonPath('data.review.approved_minutes', 60);

    $don2 = donOT($nv, '2026-09-10', '18:00', '19:00');

    $this->actingAs($sep)
        ->postJson("/api/v1/attendance/overtime/{$don2->uuid}/review", [
            'approve' => true,
            'minutes' => 600,
        ])
        ->assertJsonValidationErrors('minutes');
});

it('từ chối thì KHÔNG ghi hệ số nào, và bắt buộc có lý do', function (): void {
    [$sep, $nv] = sepVaNhanVien();
    $don = donOT($nv);

    $this->actingAs($sep)
        ->postJson("/api/v1/attendance/overtime/{$don->uuid}/review", ['approve' => false])
        ->assertJsonValidationErrors('note');

    $this->actingAs($sep)
        ->postJson("/api/v1/attendance/overtime/{$don->uuid}/review", [
            'approve' => false,
            'note' => 'Việc này không gấp, để sáng mai làm trong giờ.',
        ])
        ->assertOk()
        ->assertJsonPath('data.status', RequestStatus::Rejected->value)
        ->assertJsonPath('data.rate_is_final', false);

    expect(OvertimeRequest::query()->first()?->rate_percent)->toBeNull();
});

it('không tự duyệt đơn của chính mình, không duyệt đơn phòng khác', function (): void {
    [$sep] = sepVaNhanVien();

    $cuaMinh = donOT($sep);
    $cuaNguoiKhac = donOT(nguoiNgoai(), '2026-09-10');

    $this->actingAs($sep)
        ->postJson("/api/v1/attendance/overtime/{$cuaMinh->uuid}/review", ['approve' => true])
        ->assertForbidden();

    $this->actingAs($sep)
        ->postJson("/api/v1/attendance/overtime/{$cuaNguoiKhac->uuid}/review", ['approve' => true])
        ->assertForbidden();
});

it('nhân viên thường không có quyền duyệt', function (): void {
    [, $nv] = sepVaNhanVien();
    $don = donOT(nguoiNgoai());

    $this->actingAs($nv)
        ->postJson("/api/v1/attendance/overtime/{$don->uuid}/review", ['approve' => true])
        ->assertForbidden();
});

// ── Rút đơn ─────────────────────────────────────────────────────────────

it('rút được đơn đang chờ, không rút được đơn đã duyệt', function (): void {
    // Đơn đã duyệt là một khoản tiền công ty đã cam kết trả cho việc đã làm.
    [, $nv] = sepVaNhanVien();

    $cho = donOT($nv, '2026-09-11', '18:00', '19:00');
    $daDuyet = donOT($nv, '2026-09-10', '18:00', '19:00', RequestStatus::Approved);

    $this->actingAs($nv)
        ->postJson("/api/v1/attendance/overtime/{$cho->uuid}/cancel")
        ->assertOk()
        ->assertJsonPath('data.status', RequestStatus::Cancelled->value);

    $this->actingAs($nv)
        ->postJson("/api/v1/attendance/overtime/{$daDuyet->uuid}/cancel")
        ->assertStatus(422);
});

it('không rút được đơn của người khác', function (): void {
    [, $nv] = sepVaNhanVien();
    $don = donOT($nv);

    $this->actingAs(nguoiNgoai())
        ->postJson("/api/v1/attendance/overtime/{$don->uuid}/cancel")
        ->assertForbidden();
});

// ── Thông báo ───────────────────────────────────────────────────────────

it('quản lý trực tiếp được báo khi có đăng ký mới', function (): void {
    Notification::fake();

    [$sep, $nv] = sepVaNhanVien();
    $nv->forceFill(['manager_id' => $sep->id])->save();

    $this->actingAs($nv)
        ->postJson('/api/v1/attendance/overtime', thanOT())
        ->assertCreated();

    Notification::assertSentTo($sep, OvertimeRequestedNotification::class);
});

it('người nộp được báo khi bị từ chối', function (): void {
    // Không báo thì người ta ở lại làm hai tiếng buổi tối cho một khoản tiền
    // sẽ không bao giờ được trả.
    Notification::fake();

    [$sep, $nv] = sepVaNhanVien();
    $don = donOT($nv);

    $this->actingAs($sep)
        ->postJson("/api/v1/attendance/overtime/{$don->uuid}/review", [
            'approve' => false,
            'note' => 'Việc này không gấp, để sáng mai làm trong giờ.',
        ])
        ->assertOk();

    Notification::assertSentTo($nv, OvertimeReviewedNotification::class);
});

// ── Màn hình ────────────────────────────────────────────────────────────

it('màn của tôi nói ra chính sách và số đã dùng', function (): void {
    /*
    | Ba trần chồng lên nhau và người nộp không có cách nào tự biết mình đã dùng
    | bao nhiêu. Không nói ra thì họ gõ xong cả cái đơn rồi mới nhận một câu từ
    | chối, và lần sau vẫn không đoán được.
    */
    [, $nv] = sepVaNhanVien();
    donOT($nv, '2026-09-11', '18:00', '20:00');

    $this->actingAs($nv)
        ->getJson('/api/v1/attendance/overtime/me')
        ->assertOk()
        ->assertJsonPath('data.policy.rate_holiday_percent', 300)
        ->assertJsonPath('data.policy.max_minutes_per_day', 240)
        ->assertJsonPath('data.used.month', 120)
        ->assertJsonPath('data.used.year', 120)
        ->assertJsonStructure([
            'data' => ['requests', 'total', 'limit', 'window', 'policy', 'used'],
        ]);
});

it('hộp duyệt trả đúng hình dạng, và nhân viên thường không vào được', function (): void {
    [$sep, $nv] = sepVaNhanVien();
    donOT($nv);

    $this->actingAs($sep)
        ->getJson('/api/v1/attendance/overtime/team')
        ->assertOk()
        ->assertJsonCount(1, 'data.requests')
        ->assertJsonPath('data.pending', 1)
        ->assertJsonPath('data.requests.0.user.name', $nv->name)
        ->assertJsonStructure(['data' => ['requests', 'total', 'limit', 'pending']]);

    $this->actingAs($nv)
        ->getJson('/api/v1/attendance/overtime/team')
        ->assertForbidden();
});

it('hỏi trước được hệ số của một ngày, kèm ca hôm đó', function (): void {
    /*
    | "Tối nay là chủ nhật, 200%" là thông tin quyết định người ta có nhận làm
    | hay không, và nó phải hiện TRƯỚC khi đăng ký. Giao diện không tự tính
    | được: hệ số phụ thuộc lịch tuần và bảng ngày lễ.
    */
    [, $nv] = sepVaNhanVien();

    $this->actingAs($nv)
        ->getJson('/api/v1/attendance/overtime/preview?date=2026-09-11')
        ->assertOk()
        ->assertJsonPath('data.day_kind', 'working')
        ->assertJsonPath('data.rate_percent', 150)
        ->assertJsonPath('data.shift.start', '08:15')
        ->assertJsonPath('data.shift.end', '17:30');

    $this->actingAs($nv)
        ->getJson('/api/v1/attendance/overtime/preview?date=2026-09-13')
        ->assertOk()
        ->assertJsonPath('data.rate_percent', 200)
        // Ngày nghỉ không có ca, nên ô nhập giờ không bị chặn khoảng nào.
        ->assertJsonPath('data.shift', null);
});

it('ngày lễ rơi vào thứ hai thì KHÔNG trả về ca nào', function (): void {
    // Đọc thẳng WorkWeek::shiftFor() mà không kiểm loại ngày là đúng cái lỗi đã
    // bắt được ở SubmitOvertimeAction — hôm đó không ai đi làm.
    ngayLe('2026-09-14');

    [, $nv] = sepVaNhanVien();

    $this->actingAs($nv)
        ->getJson('/api/v1/attendance/overtime/preview?date=2026-09-14')
        ->assertOk()
        ->assertJsonPath('data.day_kind', 'holiday')
        ->assertJsonPath('data.rate_percent', 300)
        ->assertJsonPath('data.shift', null);
});

// ── Giao với chốt sổ kỳ công ────────────────────────────────────────────

it('kỳ đã chốt thì không đăng ký và không duyệt được', function (): void {
    khoaKy('2026-08');

    [$sep, $nv] = sepVaNhanVien();

    $this->actingAs($nv)
        ->postJson('/api/v1/attendance/overtime', thanOT('2026-08-14', '18:00', '20:00'))
        ->assertJsonValidationErrors('work_date');

    $don = donOT($nv, '2026-08-14', '18:00', '20:00');

    $this->actingAs($sep)
        ->postJson("/api/v1/attendance/overtime/{$don->uuid}/review", ['approve' => true])
        ->assertJsonValidationErrors('work_date');
});

it('đăng ký làm thêm còn treo cũng chặn chốt sổ', function (): void {
    // Loại nặng nhất trong ba loại đơn: người ta ĐÃ LÀM rồi. Chốt sổ mà bỏ lại
    // nó là vứt đi một khoản tiền 150–300% cho công việc đã xong.
    [, $nv] = sepVaNhanVien();
    donOT($nv, '2026-08-31', '18:00', '20:00');

    $this->actingAs(giamDoc())
        ->postJson('/api/v1/attendance/periods/close', ['period' => '2026-08'])
        ->assertJsonValidationErrors('period')
        ->assertJsonFragment(['period' => [
            'Kỳ công tháng 08/2026 còn 1 đăng ký làm thêm giờ chờ duyệt. Xử lý hết rồi mới chốt được — chốt sổ khoá luôn đơn từ, nên đơn còn treo sẽ không ai duyệt được nữa.',
        ]]);
});
