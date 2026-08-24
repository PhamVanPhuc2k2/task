<?php

declare(strict_types=1);

use App\Domain\Identity\Actions\AnonymiseUserAction;
use App\Domain\Identity\Enums\Role;
use App\Domain\Identity\Enums\UserActivityEvent;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Models\UserActivity;
use App\Domain\Leave\Enums\LeaveStatus;
use App\Domain\Leave\Enums\LeaveType;
use App\Domain\Leave\Models\LateArrivalRequest;
use App\Domain\Leave\Models\LeaveRequest;
use App\Domain\Task\Models\Task;
use App\Support\Exceptions\CannotAnonymiseActiveUserException;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;

/*
|--------------------------------------------------------------------------
| Xoá dữ liệu cá nhân người đã nghỉ việc
|--------------------------------------------------------------------------
|
| Nghị định 13/2023/NĐ-CP. Đây là thao tác KHÔNG đảo ngược được, nên bộ test
| này kiểm hai chiều đối nhau:
|
|   - Thông tin nhận dạng cá nhân phải biến mất HẲN
|   - Lịch sử công việc phải còn NGUYÊN
|
| Sai chiều nào cũng hỏng: chiều đầu là không tuân thủ, chiều sau là phá huỷ
| dữ liệu của công ty.
|
*/

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

function nguoiDaNghi(): User
{
    $u = User::factory()->create([
        'name' => 'Trần Văn Nghỉ',
        'email' => 'tran.van.nghi@explus.vn',
        'phone' => '0912345678',
        'employee_code' => 'NV0042',
        'is_active' => false,
        'terminated_at' => now()->subYears(6),
    ]);
    $u->assignRole(Role::NhanVien->value);

    return $u;
}

it('xoá sạch thông tin nhận dạng cá nhân', function (): void {
    $u = nguoiDaNghi();

    app(AnonymiseUserAction::class)->execute($u, quanTri());

    $sau = $u->fresh();

    expect($sau?->name)->not->toContain('Nghỉ')
        ->and($sau?->name)->toContain('ẩn danh')
        ->and($sau?->email)->not->toContain('tran.van.nghi')
        ->and($sau?->email)->toEndWith('@explus.invalid')
        ->and($sau?->phone)->toBeNull()
        ->and($sau?->employee_code)->toBeNull()
        ->and($sau?->anonymised_at)->not->toBeNull();
});

it('giữ NGUYÊN lịch sử công việc', function (): void {
    /*
    | Xoá thẳng dòng users là phá huỷ dữ liệu của công ty, không phải bảo vệ dữ
    | liệu của cá nhân: task mất người thực hiện, bảng công của kỳ đã chốt trở
    | nên vô nghĩa.
    */
    $u = nguoiDaNghi();
    $task = Task::factory()->for($u, 'assignee')->create(['title' => 'Việc đã làm xong']);

    app(AnonymiseUserAction::class)->execute($u, quanTri());

    $task->refresh();

    expect($task->assignee_id)->toBe($u->id)
        ->and($task->title)->toBe('Việc đã làm xong')
        ->and(User::query()->find($u->id))->not->toBeNull();
});

it('từ chối người còn đang làm việc', function (): void {
    $u = User::factory()->create(['is_active' => true]);

    expect(fn () => app(AnonymiseUserAction::class)->execute($u, quanTri()))
        ->toThrow(CannotAnonymiseActiveUserException::class);
});

it('tài khoản không còn đăng nhập được bằng bất kỳ đường nào', function (): void {
    $u = nguoiDaNghi();
    $u->createToken('thiet-bi-cu');
    $emailCu = $u->email;

    app(AnonymiseUserAction::class)->execute($u, quanTri());

    $sau = $u->fresh();

    expect($sau?->tokens()->count())->toBe(0)
        ->and($sau?->two_factor_secret)->toBeNull()
        ->and($sau?->two_factor_confirmed_at)->toBeNull();

    // Và luồng quên mật khẩu cũng không cứu được: email cũ không còn thuộc về
    // tài khoản nào.
    expect(User::query()->where('email', $emailCu)->exists())->toBeFalse();
});

it('xoá token đặt lại mật khẩu theo email CŨ', function (): void {
    /*
    | Bẫy thật, đã mắc khi viết: `password_reset_tokens` đánh chỉ mục theo email
    | chứ không theo user_id. Xoá sau khi đã ghi đè email thì câu lệnh chạy với
    | địa chỉ MỚI, không khớp dòng nào — và một token còn hiệu lực của địa chỉ cũ
    | vẫn nằm đó, im lặng.
    */
    $u = nguoiDaNghi();
    $emailCu = $u->email;

    Password::createToken($u);
    expect(DB::table('password_reset_tokens')->where('email', $emailCu)->exists())->toBeTrue();

    app(AnonymiseUserAction::class)->execute($u, quanTri());

    expect(DB::table('password_reset_tokens')->where('email', $emailCu)->exists())->toBeFalse();
});

it('ghi nhật ký kèm tên cũ, trước khi tên đó biến mất', function (): void {
    // Ghi sau khi xoá thì không còn gì để ghi, và dòng "đã ẩn danh ai đó" là
    // vô dụng khi cần chứng minh đã thực hiện đúng yêu cầu của ai.
    $u = nguoiDaNghi();
    $admin = quanTri();

    app(AnonymiseUserAction::class)->execute($u, $admin);

    $nhatKy = UserActivity::query()
        ->where('user_id', $u->id)
        ->where('event', UserActivityEvent::Anonymised->value)
        ->firstOrFail();

    expect($nhatKy->causer_id)->toBe($admin->id)
        ->and($nhatKy->old_values['name'] ?? null)->toBe('Trần Văn Nghỉ');
});

it('email mới dùng tên miền không bao giờ phân giải được', function (): void {
    // RFC 2606 dành riêng `.invalid`. Dùng một tên miền thật — kể cả tên miền
    // công ty — thì thư gửi nhầm tới đó có thể tới tay một người thật.
    $u = nguoiDaNghi();

    app(AnonymiseUserAction::class)->execute($u, quanTri());

    expect($u->fresh()?->email)->toEndWith('.invalid');
});

it('lệnh chỉ nhặt người đã quá hạn lưu trữ', function (): void {
    config()->set('identity.retention_months', 60);

    $quaHan = nguoiDaNghi();
    $moiNghi = User::factory()->create([
        'name' => 'Vừa mới nghỉ',
        'is_active' => false,
        'terminated_at' => now()->subMonth(),
    ]);

    $this->artisan('users:anonymise --dry-run')
        ->expectsOutputToContain('Trần Văn Nghỉ')
        ->assertSuccessful();

    // Chạy thử thì không đụng vào dữ liệu.
    expect($quaHan->fresh()?->anonymised_at)->toBeNull()
        ->and($moiNghi->fresh()?->anonymised_at)->toBeNull();
});

it('chạy hai lần không ghi đè người đã ẩn danh', function (): void {
    $u = nguoiDaNghi();

    app(AnonymiseUserAction::class)->execute($u, quanTri());
    $tenSauLan1 = $u->fresh()?->name;

    $this->artisan('users:anonymise --dry-run')->assertSuccessful();

    expect($u->fresh()?->name)->toBe($tenSauLan1);
});

it('xoá lý do đơn nghỉ — dữ liệu nhạy cảm, không phải tài sản công việc', function (): void {
    /*
    | Lý do xin nghỉ do chính người đó viết về hoàn cảnh riêng, và rất thường là
    | thông tin sức khoẻ — nhóm dữ liệu cá nhân NHẠY CẢM của Nghị định 13, mức
    | bảo vệ cao hơn dữ liệu thường.
    |
    | Khác hẳn nội dung task hay báo cáo ngày: những thứ đó là tài sản công việc
    | của công ty, người viết chỉ là tác giả.
    */
    $u = nguoiDaNghi();

    $don = LeaveRequest::query()->create([
        'user_id' => $u->id,
        'type' => LeaveType::Sick,
        'start_date' => '2026-07-01',
        'end_date' => '2026-07-03',
        'reason' => 'Nằm viện điều trị, có giấy bác sĩ.',
        'status' => LeaveStatus::Approved,
        'review_note' => 'Đã xem giấy viện.',
        'reviewed_at' => now(),
    ]);

    app(AnonymiseUserAction::class)->execute($u, quanTri());

    $sau = $don->fresh();

    expect($sau?->reason)->not->toContain('viện')
        ->and($sau?->review_note)->toBeNull()
        // Dòng vẫn còn, và phần có nghĩa pháp lý giữ nguyên: đơn nghỉ là chứng
        // từ lao động — ngày nào được nghỉ, loại gì, ai duyệt.
        ->and($sau?->start_date)->toBe('2026-07-01')
        ->and($sau?->end_date)->toBe('2026-07-03')
        ->and($sau?->status)->toBe(LeaveStatus::Approved);
});

it('không đụng tới đơn nghỉ của người khác', function (): void {
    $u = nguoiDaNghi();

    $nguoiKhac = User::factory()->create();
    $donKhac = LeaveRequest::query()->create([
        'user_id' => $nguoiKhac->id,
        'type' => LeaveType::Annual,
        'start_date' => '2026-07-01',
        'end_date' => '2026-07-01',
        'reason' => 'Lý do của người khác, phải còn nguyên.',
        'status' => LeaveStatus::Approved,
    ]);

    app(AnonymiseUserAction::class)->execute($u, quanTri());

    expect($donKhac->fresh()?->reason)->toContain('người khác');
});

it('xoá cả lý do đơn xin đi muộn', function (): void {
    /*
    | Cùng lý do với đơn nghỉ, và dễ quên hơn vì bảng khác: "đưa con đi khám",
    | "đưa mẹ đi viện" là thông tin sức khoẻ của NGƯỜI KHÁC trong gia đình —
    | vẫn thuộc nhóm dữ liệu cá nhân nhạy cảm của Nghị định 13.
    |
    | Ngày và giờ đã duyệt vẫn giữ: đó là phần có nghĩa với bảng công.
    */
    $u = nguoiDaNghi();

    $don = LateArrivalRequest::query()->create([
        'user_id' => $u->id,
        'date' => '2026-07-01',
        'expected_arrival' => '09:30',
        'reason' => 'Đưa con đi khám ở bệnh viện Nhi.',
        'status' => LeaveStatus::Approved,
        'review_note' => 'Đã xác nhận với gia đình.',
        'reviewed_at' => now(),
    ]);

    app(AnonymiseUserAction::class)->execute($u, quanTri());

    $sau = $don->fresh();

    expect($sau?->reason)->not->toContain('bệnh viện')
        ->and($sau?->review_note)->toBeNull()
        ->and($sau?->date)->toBe('2026-07-01')
        ->and($sau?->arrivalLabel())->toBe('09:30');
});
