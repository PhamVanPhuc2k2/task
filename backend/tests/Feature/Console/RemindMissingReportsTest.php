<?php

declare(strict_types=1);

use App\Domain\Identity\Enums\NotificationType;
use App\Domain\Identity\Enums\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Report\Notifications\DailyReportMissingNotification;
use Carbon\CarbonImmutable;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Notification;

/*
|--------------------------------------------------------------------------
| Nhắc nộp báo cáo cuối ngày
|--------------------------------------------------------------------------
|
| Việc còn lại của đợt 2. Điểm dễ sai không nằm ở chỗ gửi được thông báo, mà ở
| chỗ **gửi cho đúng người**: nhắc nhầm người nghỉ phép vài lần là cả công ty
| bắt đầu bỏ qua thông báo của hệ thống, kể cả loại quan trọng.
|
| `coGioLam()` và `daNopBaoCao()` nằm ở tests/Pest.php. Bản đầu tôi khai chúng
| trong ReportReconciliationTest và dùng nhờ từ đây — chạy cả bộ thì xanh, chạy
| riêng file này thì đỏ với "undefined function". Đúng cái bẫy đã ghi sẵn ở đầu
| tests/Pest.php, và tôi vẫn mắc lại.
|
*/

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    // 09:00 giờ UTC = 16:00 giờ Việt Nam, vẫn trong ngày công 12/08.
    $this->travelTo(CarbonImmutable::parse('2026-08-12 09:00:00'));
});

function nhanVienCoGio(int $phut, string $ngay = '2026-08-12'): User
{
    $u = User::factory()->create();
    $u->assignRole(Role::NhanVien->value);

    if ($phut > 0) {
        coGioLam($u, $ngay, $phut);
    }

    return $u;
}

it('nhắc người có giờ làm mà chưa nộp báo cáo', function (): void {
    Notification::fake();

    $u = nhanVienCoGio(300);

    $this->artisan('reports:remind')->assertSuccessful();

    Notification::assertSentTo(
        $u,
        DailyReportMissingNotification::class,
    );
});

it('không nhắc người đã nộp', function (): void {
    Notification::fake();

    $u = nhanVienCoGio(300);
    daNopBaoCao($u, '2026-08-12');

    $this->artisan('reports:remind')->assertSuccessful();

    Notification::assertNothingSent();
});

it('không nhắc người hôm nay không làm', function (): void {
    // Nghỉ phép, nghỉ ốm, đi công tác đều rơi vào đây. Nhắc họ là cách nhanh
    // nhất để mọi người coi thông báo của hệ thống là tiếng ồn.
    Notification::fake();

    nhanVienCoGio(0);

    $this->artisan('reports:remind')->assertSuccessful();

    Notification::assertNothingSent();
});

it('không nhắc người chỉ mở ứng dụng vài phút', function (): void {
    Notification::fake();

    nhanVienCoGio(5);

    $this->artisan('reports:remind')->assertSuccessful();

    Notification::assertNothingSent();
});

it('không nhắc người đã nghỉ việc', function (): void {
    Notification::fake();

    $u = nhanVienCoGio(300);
    $u->forceFill(['is_active' => false])->save();

    $this->artisan('reports:remind')->assertSuccessful();

    Notification::assertNothingSent();
});

it('chạy lại lần hai không gửi trùng', function (): void {
    // Người vận hành chạy lại lệnh để kiểm tra là chuyện bình thường. Không có
    // lớp chặn thì lần chạy thứ hai gửi lại cho đúng những người vừa nhận, và
    // lời nhắc mất hết uy tín ngay ngày đầu.
    $u = nhanVienCoGio(300);

    $this->artisan('reports:remind')->assertSuccessful();
    $this->artisan('reports:remind')->assertSuccessful();

    $so = $u->notifications()
        ->where('type', DailyReportMissingNotification::class)
        ->count();

    expect($so)->toBe(1);
});

it('chạy thử không gửi gì', function (): void {
    Notification::fake();

    nhanVienCoGio(300);

    $this->artisan('reports:remind --dry-run')->assertSuccessful();

    Notification::assertNothingSent();
});

it('nhắc được cho một ngày đã qua', function (): void {
    Notification::fake();

    $u = nhanVienCoGio(300, '2026-08-10');

    $this->artisan('reports:remind --date=2026-08-10')->assertSuccessful();

    Notification::assertSentTo($u, DailyReportMissingNotification::class);
});

it('thông báo nói ra số giờ hệ thống ghi nhận được', function (): void {
    $u = nhanVienCoGio(305);

    $this->artisan('reports:remind')->assertSuccessful();

    /** @var array<string, mixed> $noiDung */
    $noiDung = (array) $u->notifications()->firstOrFail()->data;

    expect($noiDung['type'])->toBe(NotificationType::ReportMissing->value)
        ->and($noiDung['worked_minutes'])->toBe(305)
        ->and($noiDung['report_date'])->toBe('2026-08-12')
        // Câu chữ phải nói được "5 giờ 5 phút", không phải "305".
        ->and($noiDung['message'])->toContain('5 giờ 5 phút');
});

it('tôn trọng tuỳ chọn tắt thông báo của người dùng', function (): void {
    $u = nhanVienCoGio(300);

    $u->notificationSettings()->updateOrCreate(
        ['type' => NotificationType::ReportMissing->value],
        ['in_app' => false, 'email' => false],
    );

    $this->artisan('reports:remind')->assertSuccessful();

    expect($u->notifications()->count())->toBe(0);
});
