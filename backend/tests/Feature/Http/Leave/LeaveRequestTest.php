<?php

declare(strict_types=1);

use App\Domain\Identity\Enums\Role;
use App\Domain\Identity\Models\Department;
use App\Domain\Identity\Models\User;
use App\Domain\Leave\Enums\LeaveStatus;
use App\Domain\Leave\Models\LeaveRequest;
use App\Domain\Leave\Notifications\LeaveRequestedNotification;
use App\Domain\Leave\Notifications\LeaveReviewedNotification;
use Carbon\CarbonImmutable;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;

/*
|--------------------------------------------------------------------------
| Đơn nghỉ: nộp, duyệt, rút
|--------------------------------------------------------------------------
|
| Mốc thời gian: 12/08/2026 09:00 UTC = 16:00 giờ Việt Nam, thứ Tư.
|
*/

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->withHeader('Origin', 'http://localhost:3000');
    $this->travelTo(CarbonImmutable::parse('2026-08-12 09:00:00'));
});

/** @return array{User, User} [trưởng phòng, nhân viên] */
function doiNghiPhep(): array
{
    $phong = Department::factory()->create();

    $sep = User::factory()->for($phong, 'department')->create(['name' => 'Trưởng phòng']);
    $sep->assignRole(Role::TruongPhong->value);

    $nv = User::factory()->for($phong, 'department')->create(['name' => 'Nhân viên A']);
    $nv->assignRole(Role::NhanVien->value);

    return [$sep, $nv];
}

/** @return TestResponse<JsonResponse> */
function nopDon(User $u, string $tu, string $den, string $loai = 'annual'): TestResponse
{
    return test()->actingAs($u)->postJson('/api/v1/leave', [
        'type' => $loai,
        'start_date' => $tu,
        'end_date' => $den,
        'reason' => 'Về quê giỗ ông, đã bàn giao việc cho đồng nghiệp.',
    ]);
}

/*
|--------------------------------------------------------------------------
| Nộp đơn
|--------------------------------------------------------------------------
*/

it('nhân viên nộp được đơn nghỉ', function (): void {
    [, $nv] = doiNghiPhep();

    nopDon($nv, '2026-08-17', '2026-08-19')
        ->assertCreated()
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.days', 3)
        ->assertJsonPath('data.type_label', 'Nghỉ phép năm');
});

it('chặn đơn ngược ngày', function (): void {
    // Đơn "từ 20/08 đến 15/08" làm mọi phép so sánh khoảng trả về rỗng — ngày
    // nghỉ không bao giờ khớp, và không có gì báo.
    [, $nv] = doiNghiPhep();

    nopDon($nv, '2026-08-20', '2026-08-15')
        ->assertStatus(422)
        ->assertJsonValidationErrors('end_date');
});

it('chặn hai đơn chồng lấn nhau', function (): void {
    /*
    | Không chặn thì một ngày thuộc hai đơn, và câu "ngày này nghỉ theo đơn
    | nào, ai duyệt, lý do gì" hết đáp án duy nhất. Tệ hơn: duyệt đơn này rồi
    | từ chối đơn kia thì cùng một ngày vừa được miễn chấm công vừa không.
    */
    [, $nv] = doiNghiPhep();

    nopDon($nv, '2026-08-17', '2026-08-19')->assertCreated();

    nopDon($nv, '2026-08-18', '2026-08-20')
        ->assertStatus(422)
        ->assertJsonPath('code', 'LEAVE_OVERLAPS');
});

it('đơn bị từ chối KHÔNG chặn đơn nộp lại', function (): void {
    // Bị từ chối rồi nộp lại với lý do rõ hơn là chuyện bình thường.
    [$sep, $nv] = doiNghiPhep();

    $don = nopDon($nv, '2026-08-17', '2026-08-19')->json('data.id');

    $this->actingAs($sep)->postJson("/api/v1/leave/{$don}/review", [
        'approve' => false,
        'note' => 'Tuần đó cả nhóm phải chạy kịp bàn giao cho khách.',
    ])->assertOk();

    nopDon($nv, '2026-08-17', '2026-08-19')->assertCreated();
});

it('chặn đơn dài quá mức — bẫy gõ nhầm năm', function (): void {
    [, $nv] = doiNghiPhep();

    nopDon($nv, '2026-08-17', '2027-08-17')->assertStatus(422);
});

it('chặn đơn nằm ngoài khoảng ngày cho phép', function (): void {
    [, $nv] = doiNghiPhep();

    // Quá xa trong tương lai.
    nopDon($nv, '2028-01-01', '2028-01-02')->assertStatus(422);
    // Quá xa trong quá khứ.
    nopDon($nv, '2020-01-01', '2020-01-02')->assertStatus(422);
});

it('nộp bù được đơn nghỉ ốm của tuần trước', function (): void {
    // Nghỉ ốm đột xuất thường khai sau khi đã nghỉ, và đó chính là trường hợp
    // cần miễn chấm công nhất. Chặn quá chặt thì người ta quay lại nhắn Zalo.
    [, $nv] = doiNghiPhep();

    nopDon($nv, '2026-08-05', '2026-08-06', 'sick')->assertCreated();
});

it('báo cho quản lý trực tiếp khi có đơn mới', function (): void {
    Notification::fake();

    [$sep, $nv] = doiNghiPhep();
    $nv->forceFill(['manager_id' => $sep->id])->save();

    nopDon($nv, '2026-08-17', '2026-08-19')->assertCreated();

    Notification::assertSentTo($sep, LeaveRequestedNotification::class);
});

/*
|--------------------------------------------------------------------------
| Duyệt
|--------------------------------------------------------------------------
*/

it('trưởng phòng duyệt được đơn của phòng mình', function (): void {
    Notification::fake();

    [$sep, $nv] = doiNghiPhep();
    $don = nopDon($nv, '2026-08-17', '2026-08-19')->json('data.id');

    $this->actingAs($sep)
        ->postJson("/api/v1/leave/{$don}/review", ['approve' => true])
        ->assertOk()
        ->assertJsonPath('data.status', 'approved');

    Notification::assertSentTo($nv, LeaveReviewedNotification::class);
});

it('từ chối BẮT BUỘC có lý do', function (): void {
    // Duyệt thì không cần: "đồng ý" đã là câu trả lời đầy đủ. Từ chối mà không
    // nói vì sao thì người nộp không biết phải sửa gì.
    [$sep, $nv] = doiNghiPhep();
    $don = nopDon($nv, '2026-08-17', '2026-08-19')->json('data.id');

    $this->actingAs($sep)
        ->postJson("/api/v1/leave/{$don}/review", ['approve' => false])
        ->assertStatus(422)
        ->assertJsonValidationErrors('note');
});

it('không duyệt được đơn của phòng khác', function (): void {
    [$sep] = doiNghiPhep();

    $phongKhac = Department::factory()->create();
    $nguoiKhac = User::factory()->for($phongKhac, 'department')->create();
    $nguoiKhac->assignRole(Role::NhanVien->value);

    $don = nopDon($nguoiKhac, '2026-08-17', '2026-08-19')->json('data.id');

    $this->actingAs($sep)
        ->postJson("/api/v1/leave/{$don}/review", ['approve' => true])
        ->assertForbidden();
});

it('không tự duyệt đơn của chính mình', function (): void {
    /*
    | Ràng buộc nhân sự cơ bản, và nó KHÔNG suy ra được từ phạm vi phòng ban —
    | trưởng phòng luôn nằm trong phòng của chính mình, nên nếu chỉ kiểm phạm vi
    | thì họ tự duyệt được.
    */
    [$sep] = doiNghiPhep();
    $don = nopDon($sep, '2026-08-17', '2026-08-19')->json('data.id');

    $this->actingAs($sep)
        ->postJson("/api/v1/leave/{$don}/review", ['approve' => true])
        ->assertForbidden();
});

it('nhân viên thường không duyệt được đơn nào', function (): void {
    [, $nv] = doiNghiPhep();
    /** @var Department $phong */
    $phong = $nv->department;
    $nv2 = User::factory()->for($phong, 'department')->create();
    $nv2->assignRole(Role::NhanVien->value);

    $don = nopDon($nv2, '2026-08-17', '2026-08-19')->json('data.id');

    $this->actingAs($nv)
        ->postJson("/api/v1/leave/{$don}/review", ['approve' => true])
        ->assertForbidden();
});

it('đơn đã duyệt KHÔNG duyệt lại được', function (): void {
    // Ngày đã duyệt là căn cứ miễn chấm công. Đổi ngược lại nghĩa là bảng công
    // của một ngày trong quá khứ đổi nghĩa mà không ai biết.
    [$sep, $nv] = doiNghiPhep();
    $don = nopDon($nv, '2026-08-17', '2026-08-19')->json('data.id');

    $this->actingAs($sep)->postJson("/api/v1/leave/{$don}/review", ['approve' => true])->assertOk();

    $this->actingAs($sep)
        ->postJson("/api/v1/leave/{$don}/review", [
            'approve' => false,
            'note' => 'Đổi ý, không cho nghỉ nữa.',
        ])
        ->assertStatus(422)
        ->assertJsonPath('code', 'LEAVE_NOT_EDITABLE');
});

/*
|--------------------------------------------------------------------------
| Rút đơn
|--------------------------------------------------------------------------
*/

it('người nộp rút được đơn đang chờ', function (): void {
    [, $nv] = doiNghiPhep();
    $don = nopDon($nv, '2026-08-17', '2026-08-19')->json('data.id');

    $this->actingAs($nv)
        ->postJson("/api/v1/leave/{$don}/cancel")
        ->assertOk()
        ->assertJsonPath('data.status', 'cancelled');
});

it('không rút được đơn của người khác', function (): void {
    [$sep, $nv] = doiNghiPhep();
    $don = nopDon($nv, '2026-08-17', '2026-08-19')->json('data.id');

    // Quản lý muốn bác đơn thì dùng đường duyệt và ghi lý do — rút hộ là xoá
    // dấu vết của một quyết định.
    $this->actingAs($sep)->postJson("/api/v1/leave/{$don}/cancel")->assertForbidden();
});

it('không rút được đơn đã duyệt', function (): void {
    [$sep, $nv] = doiNghiPhep();
    $don = nopDon($nv, '2026-08-17', '2026-08-19')->json('data.id');

    $this->actingAs($sep)->postJson("/api/v1/leave/{$don}/review", ['approve' => true]);

    $this->actingAs($nv)
        ->postJson("/api/v1/leave/{$don}/cancel")
        ->assertStatus(422);
});

/*
|--------------------------------------------------------------------------
| Hộp duyệt
|--------------------------------------------------------------------------
*/

it('hộp duyệt chỉ hiện đơn trong phạm vi quản lý', function (): void {
    [$sep, $nv] = doiNghiPhep();

    $phongKhac = Department::factory()->create();
    $nguoiKhac = User::factory()->for($phongKhac, 'department')->create(['name' => 'Phòng khác']);
    $nguoiKhac->assignRole(Role::NhanVien->value);

    nopDon($nv, '2026-08-17', '2026-08-19');
    nopDon($nguoiKhac, '2026-08-17', '2026-08-19');

    $ten = collect(
        $this->actingAs($sep)->getJson('/api/v1/leave/team')->assertOk()->json('data.requests'),
    )->pluck('user.name')->all();

    expect($ten)->toContain('Nhân viên A')
        ->and($ten)->not->toContain('Phòng khác');
});

it('số đơn đang chờ đúng kể cả khi đang xem tab khác', function (): void {
    // Con số này hiện lên thanh điều hướng, nên nó phải đúng bất kể bộ lọc.
    [$sep, $nv] = doiNghiPhep();

    nopDon($nv, '2026-08-17', '2026-08-19');

    $this->actingAs($sep)
        ->getJson('/api/v1/leave/team?status=approved')
        ->assertOk()
        ->assertJsonPath('data.pending_count', 1)
        ->assertJsonPath('data.total', 0);
});

it('nhân viên thường không mở được hộp duyệt', function (): void {
    [, $nv] = doiNghiPhep();

    $this->actingAs($nv)->getJson('/api/v1/leave/team')->assertForbidden();
});

it('đơn của tôi chỉ thấy đơn của chính mình', function (): void {
    [$sep, $nv] = doiNghiPhep();

    nopDon($nv, '2026-08-17', '2026-08-19');
    nopDon($sep, '2026-08-24', '2026-08-25');

    $don = $this->actingAs($nv)->getJson('/api/v1/leave/me')->assertOk()->json('data.requests');

    expect($don)->toHaveCount(1);

    expect(LeaveRequest::query()->where('status', LeaveStatus::Pending->value)->count())->toBe(2);
});
