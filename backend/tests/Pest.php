<?php

declare(strict_types=1);

use App\Domain\Attendance\Models\WorkSession;
use App\Domain\Identity\Enums\Role;
use App\Domain\Identity\Models\Department;
use App\Domain\Identity\Models\User;
use App\Domain\Report\Enums\DailyReportStatus;
use App\Domain\Report\Models\DailyReport;
use App\Domain\Task\Models\Task;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\JsonResponse;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| Feature test chạy trên database thật (đã migrate lại mỗi lần) vì phần lớn
| nghiệp vụ của hệ thống này nằm ở truy vấn và ràng buộc dữ liệu — mock
| database sẽ bỏ lọt đúng loại lỗi cần bắt.
|
| Unit test không cần database nên không extend TestCase.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
*/

expect()->extend('toBeMoney', function () {
    // Tiền luôn là DECIMAL, không bao giờ là float.
    // Xem README, "Quy ước dữ liệu, thời gian & tiền tệ".
    return $this->toBeString();
});

/*
|--------------------------------------------------------------------------
| Hàm dựng dữ liệu dùng chung
|--------------------------------------------------------------------------
|
| Đặt ở đây chứ không ở trong một file test cụ thể: hàm khai báo trong file
| test chỉ tồn tại khi file đó được nạp, nên chạy riêng một file khác dùng nhờ
| nó sẽ đỏ với lỗi "undefined function" — trong khi chạy cả bộ vẫn xanh.
|
*/

/**
 * Một trưởng phòng và một nhân viên trong cùng phòng ban.
 *
 * Trưởng phòng có quyền giao việc, quản lý dự án và xem task của cả phòng;
 * nhân viên chỉ có quyền trên task của chính mình. Đây là cặp vai trò dùng để
 * kiểm tra ranh giới quyền ở hầu hết test của miền Task.
 *
 * @return array{User, User}
 */
function sepVaNhanVien(): array
{
    $phong = Department::factory()->create();

    $sep = User::factory()->for($phong, 'department')->create();
    $sep->assignRole(Role::TruongPhong->value);

    $nhanVien = User::factory()->for($phong, 'department')->create();
    $nhanVien->assignRole(Role::NhanVien->value);

    return [$sep, $nhanVien];
}

/**
 * Một nhân viên nằm ngoài mọi phạm vi của task đang kiểm.
 *
 * Thuộc phòng ban khác, không được giao việc, không theo dõi task nào. Dùng để
 * kiểm ranh giới "không thấy gì cả".
 */
function nguoiNgoai(): User
{
    $u = User::factory()->for(Department::factory(), 'department')->create();
    $u->assignRole(Role::NhanVien->value);

    return $u;
}

/**
 * Một quản trị viên có toàn quyền nhân sự.
 *
 * Ở chung đây vì có hơn một file test dùng tới — đúng lý do đã ghi ở đầu mục
 * này. Trước đây hàm nằm trong EmployeeDirectoryTest.php, nên chạy riêng một
 * file khác dùng nhờ nó sẽ đỏ với "undefined function" trong khi chạy cả bộ
 * vẫn xanh.
 */
function quanTri(): User
{
    $u = User::factory()->create();
    $u->assignRole(Role::Admin->value);

    return $u;
}

/**
 * Một phiên làm việc có sẵn trong database.
 *
 * Ghi thẳng thay vì bắn nhịp tim: chỗ dùng hàm này kiểm phần đối chiếu công với
 * báo cáo, không kiểm luật nối phiên — luật đó đã có bộ test riêng ở
 * HeartbeatTest.
 *
 * `started_at` để 03:00 UTC = 10:00 giờ Việt Nam, nằm gọn trong ngày công
 * `$ngay`. Đặt 17:00 UTC là rơi sang ngày hôm sau theo giờ Việt Nam, và test sẽ
 * đỏ vì một lý do chẳng liên quan gì tới thứ đang kiểm — đúng cái bẫy mà
 * App\Support\Time\WorkDate sinh ra để chặn.
 */
function coGioLam(User $u, string $ngay, int $phut): void
{
    $batDau = CarbonImmutable::parse($ngay.' 03:00:00', 'UTC');

    WorkSession::query()->create([
        'user_id' => $u->id,
        'started_at' => $batDau,
        'ended_at' => $batDau->addMinutes($phut),
        'work_date' => $ngay,
        'source' => 'web',
    ]);
}

/**
 * Một phiên làm việc bắt đầu vào ĐÚNG GIỜ VIỆT NAM chỉ định.
 *
 * Khác `coGioLam` ở chỗ nói rõ giờ bắt đầu, vì phần đi muộn quan tâm tới lúc
 * nào chứ không chỉ bao lâu. Nhận giờ Việt Nam thay vì UTC là có chủ ý: test
 * viết "8h30" đọc ra ngay là muộn 15 phút, còn viết "01:30 UTC" thì người đọc
 * phải tự cộng bảy tiếng trong đầu — và đó là chỗ sai rất dễ lọt.
 */
function coGioLamTu(User $u, string $ngay, string $gioVietNam, int $phut): void
{
    $batDau = CarbonImmutable::parse(
        $ngay.' '.$gioVietNam,
        config()->string('app.display_timezone'),
    )->utc();

    WorkSession::query()->create([
        'user_id' => $u->id,
        'started_at' => $batDau,
        'ended_at' => $batDau->addMinutes($phut),
        'work_date' => $ngay,
        'source' => 'web',
    ]);
}

/**
 * Một giám đốc.
 *
 * Ở đây chứ không ở file test nào, vì hai file đã cần tới nó — và hàm khai
 * trong một file test chỉ tồn tại khi file đó được nạp, nên chạy riêng file
 * kia sẽ đỏ với "undefined function" trong khi chạy cả bộ vẫn xanh. Đúng cái
 * bẫy đã ghi ở đầu mục này, và đã mắc lại lần thứ ba.
 */
function giamDoc(): User
{
    $u = User::factory()->create();
    $u->assignRole(Role::GiamDoc->value);

    return $u;
}

/** Một báo cáo ngày đã nộp — bản nháp không dùng hàm này. */
function daNopBaoCao(User $u, string $ngay): DailyReport
{
    return DailyReport::query()->create([
        'user_id' => $u->id,
        'report_date' => $ngay,
        'content' => 'Hoàn thành phần đăng nhập, chiều họp với khách hàng.',
        'status' => DailyReportStatus::Submitted,
        'submitted_at' => now(),
    ]);
}

/**
 * Gửi một nhịp tim chấm công.
 *
 * Ở đây chứ không ở HeartbeatTest: hai file test dùng tới nó, và hàm khai
 * trong một file test chỉ tồn tại khi file đó được nạp — chạy riêng file kia
 * sẽ đỏ với "undefined function" trong khi chạy cả bộ vẫn xanh. Đúng cái bẫy
 * đã ghi ở đầu mục này, và đã mắc lại hai lần.
 *
 * @return TestResponse<JsonResponse>
 */
function nhip(User $u): TestResponse
{
    return test()->actingAs($u)->postJson('/api/v1/attendance/heartbeat');
}

/**
 * Giao cho người này một việc, để họ thuộc diện phải nộp báo cáo ngày.
 *
 * `reports:remind` chỉ nhắc người **đã từng được giao ít nhất một task** — đó
 * là cách phân biệt người đã thật sự bắt đầu làm việc với người vừa được tạo
 * tài khoản. Xem RemindMissingReportsCommand::daTungCoViec().
 *
 * Ở tests/Pest.php chứ không trong một file test: ba file dùng tới nó, và hàm
 * khai trong một file test chỉ tồn tại khi file đó được nạp — chạy riêng file
 * kia sẽ đỏ với "undefined function" trong khi chạy cả bộ vẫn xanh.
 */
function coViecDuocGiao(User $u): Task
{
    return Task::factory()->create(['assignee_id' => $u->id]);
}
