<?php

declare(strict_types=1);

use App\Domain\Attendance\Models\Holiday;
use App\Domain\Identity\Enums\NotificationType;
use App\Domain\Identity\Enums\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Report\Notifications\DailyReportMissingNotification;
use App\Domain\Task\Models\Task;
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

    // Đã từng được giao việc — điều kiện để thuộc diện phải nộp báo cáo. Thiếu
    // dòng này thì mọi test dưới đây đều xanh vì lệnh không nhắc AI CẢ, tức là
    // xanh vì lý do sai.
    coViecDuocGiao($u);

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

it('VẪN nhắc người hệ thống không đo được giờ nào', function (): void {
    /*
    | Test này từng khẳng định điều NGƯỢC LẠI, và đó là lỗi đang sửa.
    |
    | Người đi gặp khách, đi họp với đối tác, hướng dẫn khách vận hành website
    | không thể treo trình duyệt để có giờ — nhưng họ vẫn phải báo cáo, và
    | thường là báo cáo đáng đọc nhất trong ngày. Luật cũ bỏ sót đúng nhóm đó.
    |
    | Nghỉ phép KHÔNG còn dựa vào con số 0 giờ để bị loại: nó có đơn riêng, lọc
    | riêng — xem LeaveExemptsAttendanceTest.
    */
    Notification::fake();

    $u = nhanVienCoGio(0);

    $this->artisan('reports:remind')->assertSuccessful();

    Notification::assertSentTo($u, DailyReportMissingNotification::class);
});

it('vẫn nhắc người chỉ mở ứng dụng vài phút', function (): void {
    // Năm phút giờ công không nói được gì về việc hôm nay họ có làm hay không
    // — người họp cả buổi sáng rồi mở app xem thông báo đúng như vậy.
    Notification::fake();

    $u = nhanVienCoGio(5);

    $this->artisan('reports:remind')->assertSuccessful();

    Notification::assertSentTo($u, DailyReportMissingNotification::class);
});

it('không nhắc người chưa từng được giao việc', function (): void {
    /*
    | Vế duy nhất còn lại thay cho điều kiện giờ công. Nó chỉ phân biệt người đã
    | thật sự bắt đầu làm việc với người vừa được tạo tài khoản — cố ý KHÔNG
    | suy đoán hôm nay họ bận gì.
    */
    Notification::fake();

    $u = User::factory()->create();
    $u->assignRole(Role::NhanVien->value);
    coGioLam($u, '2026-08-12', 300);

    $this->artisan('reports:remind')->assertSuccessful();

    Notification::assertNothingSent();
});

it('người từng được giao việc rồi việc bị xoá thì vẫn nhắc', function (): void {
    // "Đã từng được giao việc" là một sự thật lịch sử. Một task bị xoá sau đó
    // không làm cho người ta chưa từng đi làm.
    Notification::fake();

    $u = nhanVienCoGio(0);
    Task::query()->where('assignee_id', $u->id)->delete();

    $this->artisan('reports:remind')->assertSuccessful();

    Notification::assertSentTo($u, DailyReportMissingNotification::class);
});

it('không nhắc ai vào ngày lễ', function (): void {
    /*
    | Trước đây không cần luật riêng: điều kiện "phải có giờ làm" tự nó lọc ngày
    | lễ, vì cả công ty nghỉ thì không ai có giờ. Bỏ điều kiện đó là mất luôn lá
    | chắn ấy — và hậu quả là nhắc cả công ty vào mùng 1 Tết.
    |
    | Đúng loại hệ quả không nhìn thấy khi đọc diff: dòng bị xoá nằm ở một chỗ,
    | thứ nó vô tình bảo vệ nằm ở chỗ khác.
    */
    Notification::fake();

    nhanVienCoGio(300);

    Holiday::query()->create([
        'date' => '2026-08-12',
        'observed_date' => '2026-08-12',
        'name' => 'Ngày lễ thử',
        'is_paid' => true,
    ]);

    $this->artisan('reports:remind')->assertSuccessful();

    Notification::assertNothingSent();
});

it('dùng observed_date chứ không dùng date của ngày lễ', function (): void {
    // Lễ trùng ngày nghỉ hằng tuần thì nghỉ bù vào ngày làm việc kế tiếp theo
    // khoản 3 Điều 112 — và ngày nghỉ bù mới là ngày không ai đi làm.
    Notification::fake();

    $u = nhanVienCoGio(300);

    Holiday::query()->create([
        'date' => '2026-08-09',
        'observed_date' => '2026-08-12',
        'name' => 'Lễ rơi vào chủ nhật, nghỉ bù',
        'is_paid' => true,
    ]);

    $this->artisan('reports:remind')->assertSuccessful();

    Notification::assertNotSentTo($u, DailyReportMissingNotification::class);
});

it('không nhắc người chưa tới ngày vào làm', function (): void {
    // Tài khoản tạo trước ngày đi làm là chuyện bình thường của nhân sự.
    Notification::fake();

    $u = nhanVienCoGio(0);
    $u->forceFill(['joined_at' => '2026-08-20'])->save();

    $this->artisan('reports:remind')->assertSuccessful();

    Notification::assertNothingSent();
});

it('vẫn nhắc người chưa khai ngày vào làm', function (): void {
    // Nhân sự nhập từ CSV đợt đầu có thể thiếu cột này. Một luật im lặng ngừng
    // nhắc cả nhóm đó thì không ai phát hiện ra — mặc định an toàn là cứ nhắc.
    Notification::fake();

    $u = nhanVienCoGio(0);
    $u->forceFill(['joined_at' => null])->save();

    $this->artisan('reports:remind')->assertSuccessful();

    Notification::assertSentTo($u, DailyReportMissingNotification::class);
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

it('thông báo KHÔNG nói "0 phút" khi hệ thống không đo được giờ', function (): void {
    /*
    | Với người đi gặp khách cả ngày, câu "Hệ thống ghi nhận 0 phút làm việc hôm
    | nay" đọc như một lời buộc tội cho đúng cái ngày họ làm việc vất vả nhất.
    |
    | Đây là loại lỗi không có gì báo: thông báo vẫn gửi, vẫn đúng người, chỉ là
    | câu chữ xúc phạm người đọc.
    */
    $u = nhanVienCoGio(0);

    $this->artisan('reports:remind')->assertSuccessful();

    /** @var array<string, mixed> $noiDung */
    $noiDung = (array) $u->notifications()->firstOrFail()->data;

    expect($noiDung['worked_minutes'])->toBe(0)
        ->and($noiDung['message'])->not->toContain('0 phút')
        ->and($noiDung['message'])->not->toContain('Hệ thống ghi nhận')
        ->and($noiDung['message'])->toContain('làm việc bên ngoài');
});
