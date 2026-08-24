<?php

declare(strict_types=1);

use App\Domain\Identity\Models\Department;
use App\Domain\Identity\Models\User;
use App\Domain\Task\Enums\ProjectStatus;
use App\Domain\Task\Enums\TaskStatus;
use App\Domain\Task\Models\Project;
use App\Domain\Task\Models\Task;
use Carbon\CarbonImmutable;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->withHeader('Origin', 'http://localhost:3000');

    // Neo thời gian: các nhóm "hạn hôm nay" và "hoàn thành tuần này" phụ thuộc
    // vào hôm nay là thứ mấy. Chạy vào chủ nhật mà không neo thì `startOfWeek`
    // rơi sang tuần khác và test đỏ vì lý do không liên quan.
    $this->travelTo(CarbonImmutable::parse('2026-08-12 09:00:00'));
});

/*
|--------------------------------------------------------------------------
| Quyền
|--------------------------------------------------------------------------
*/

it('cho người xem được toàn công ty vào trang tổng quan', function (): void {
    $this->actingAs(giamDoc())
        ->getJson('/api/v1/dashboard/overview')
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                'summary' => [
                    'open_tasks', 'overdue_tasks', 'due_today', 'unassigned_tasks',
                    'completed_this_week', 'active_projects', 'active_employees',
                ],
                'workload' => ['rows', 'total'],
                'projects' => ['rows', 'total'],
                'most_overdue',
            ],
        ]);
});

it('chặn trưởng phòng — phạm vi của họ là phòng mình, không phải công ty', function (): void {
    // Không phải quên phân quyền: một trang "tổng quan công ty" lọc theo phòng
    // sẽ là màn hình khác, mang ý nghĩa khác. Chặn ở đây là có chủ ý.
    [$sep] = sepVaNhanVien();

    $this->actingAs($sep)->getJson('/api/v1/dashboard/overview')->assertForbidden();
});

it('chặn nhân viên thường', function (): void {
    [, $nhanVien] = sepVaNhanVien();

    $this->actingAs($nhanVien)->getJson('/api/v1/dashboard/overview')->assertForbidden();
});

it('chặn khi chưa đăng nhập', function (): void {
    $this->getJson('/api/v1/dashboard/overview')->assertUnauthorized();
});

/*
|--------------------------------------------------------------------------
| Các con số chính
|--------------------------------------------------------------------------
*/

it('đếm đúng việc đang mở, quá hạn, hạn hôm nay và chưa giao', function (): void {
    $nv = User::factory()->create();

    Task::factory()->count(2)->create([
        'assignee_id' => $nv->id,
        'status' => TaskStatus::Todo->value,
        'due_date' => now()->subDays(3),
    ]);
    Task::factory()->create([
        'assignee_id' => $nv->id,
        'status' => TaskStatus::InProgress->value,
        'due_date' => now()->addHours(4),
    ]);
    Task::factory()->create([
        'assignee_id' => null,
        'status' => TaskStatus::Todo->value,
        'due_date' => now()->addDays(10),
    ]);

    // Việc đã đóng KHÔNG được tính vào bất kỳ con số nào ở trên, kể cả khi quá
    // hạn — đã xong rồi thì trễ hạn không còn là vấn đề cần nhìn thấy.
    Task::factory()->create([
        'assignee_id' => $nv->id,
        'status' => TaskStatus::Done->value,
        'due_date' => now()->subDays(9),
        'completed_at' => now()->subDay(),
    ]);
    Task::factory()->create([
        'assignee_id' => $nv->id,
        'status' => TaskStatus::Cancelled->value,
        'due_date' => now()->subDays(9),
    ]);

    $s = $this->actingAs(giamDoc())
        ->getJson('/api/v1/dashboard/overview')
        ->assertOk()
        ->json('data.summary');

    expect($s['open_tasks'])->toBe(4)
        ->and($s['overdue_tasks'])->toBe(2)
        ->and($s['due_today'])->toBe(1)
        ->and($s['unassigned_tasks'])->toBe(1)
        ->and($s['completed_this_week'])->toBe(1);
});

it('không tính việc hoàn thành từ tuần trước vào "hoàn thành tuần này"', function (): void {
    Task::factory()->create([
        'status' => TaskStatus::Done->value,
        'completed_at' => now()->subWeeks(2),
    ]);

    $s = $this->actingAs(giamDoc())
        ->getJson('/api/v1/dashboard/overview')
        ->json('data.summary');

    expect($s['completed_this_week'])->toBe(0);
});

it('chỉ đếm dự án còn mở và nhân sự đang làm việc', function (): void {
    Project::factory()->create(['status' => ProjectStatus::Active->value]);
    Project::factory()->create(['status' => ProjectStatus::Planning->value]);
    Project::factory()->create(['status' => ProjectStatus::Completed->value]);
    Project::factory()->create(['status' => ProjectStatus::Cancelled->value]);

    User::factory()->count(3)->create(['is_active' => true]);
    User::factory()->count(2)->create(['is_active' => false]);

    $s = $this->actingAs(giamDoc())
        ->getJson('/api/v1/dashboard/overview')
        ->json('data.summary');

    // 3 người + 1 giám đốc vừa tạo để gọi API.
    expect($s['active_projects'])->toBe(2)
        ->and($s['active_employees'])->toBe(4);
});

/*
|--------------------------------------------------------------------------
| Tải việc theo người
|--------------------------------------------------------------------------
*/

it('xếp người có nhiều việc trễ nhất lên đầu', function (): void {
    $phong = Department::factory()->create(['name' => 'Phòng Kinh doanh']);

    $itTre = User::factory()->for($phong, 'department')->create(['name' => 'Người Ít Trễ']);
    $nhieuTre = User::factory()->for($phong, 'department')->create(['name' => 'Người Nhiều Trễ']);

    // Người ít trễ ôm NHIỀU việc hơn — để chắc chắn thứ tự xếp theo số việc
    // trễ chứ không phải theo tổng số việc.
    Task::factory()->count(6)->create([
        'assignee_id' => $itTre->id,
        'status' => TaskStatus::Todo->value,
        'due_date' => now()->addDays(5),
    ]);
    Task::factory()->count(3)->create([
        'assignee_id' => $nhieuTre->id,
        'status' => TaskStatus::Todo->value,
        'due_date' => now()->subDays(2),
    ]);

    $rows = $this->actingAs(giamDoc())
        ->getJson('/api/v1/dashboard/overview')
        ->json('data.workload.rows');

    expect($rows[0]['name'])->toBe('Người Nhiều Trễ')
        ->and($rows[0]['overdue'])->toBe(3)
        ->and($rows[0]['open'])->toBe(3)
        ->and($rows[0]['department'])->toBe('Phòng Kinh doanh')
        ->and($rows[1]['name'])->toBe('Người Ít Trễ')
        ->and($rows[1]['overdue'])->toBe(0)
        ->and($rows[1]['open'])->toBe(6);
});

it('không đưa người chưa được giao việc nào vào bảng tải việc', function (): void {
    User::factory()->count(5)->create();

    $rows = $this->actingAs(giamDoc())
        ->getJson('/api/v1/dashboard/overview')
        ->json('data.workload.rows');

    expect($rows)->toBe([]);
});

it('báo tổng số người khi bảng bị cắt bớt', function (): void {
    // Cắt im lặng là kiểu nói dối khó chịu nhất: bảng hiện 12 dòng trông như
    // toàn bộ công ty, trong khi còn ba người nữa đang ôm việc trễ.
    foreach (range(1, 15) as $i) {
        $u = User::factory()->create();
        Task::factory()->create([
            'assignee_id' => $u->id,
            'status' => TaskStatus::Todo->value,
            'due_date' => now()->subDays($i),
        ]);
    }

    $tai = $this->actingAs(giamDoc())
        ->getJson('/api/v1/dashboard/overview')
        ->json('data.workload');

    expect($tai['rows'])->toHaveCount(12)
        ->and($tai['total'])->toBe(15);
});

/*
|--------------------------------------------------------------------------
| Tiến độ dự án
|--------------------------------------------------------------------------
*/

it('tính phần trăm tiến độ và số việc trễ của từng dự án', function (): void {
    $duAn = Project::factory()->create([
        'name' => 'Triển khai cho khách A',
        'status' => ProjectStatus::Active->value,
    ]);

    Task::factory()->count(3)->create([
        'project_id' => $duAn->id,
        'status' => TaskStatus::Done->value,
    ]);
    Task::factory()->create([
        'project_id' => $duAn->id,
        'status' => TaskStatus::Todo->value,
        'due_date' => now()->subDay(),
    ]);

    $rows = $this->actingAs(giamDoc())
        ->getJson('/api/v1/dashboard/overview')
        ->json('data.projects.rows');

    expect($rows[0]['name'])->toBe('Triển khai cho khách A')
        ->and($rows[0]['total'])->toBe(4)
        ->and($rows[0]['done'])->toBe(3)
        ->and($rows[0]['overdue'])->toBe(1)
        ->and($rows[0]['progress_percent'])->toBe(75)
        ->and($rows[0]['status']['label'])->toBe('Đang chạy');
});

it('dự án chưa có việc nào thì tiến độ là 0, không phải 100', function (): void {
    // Chia cho 0 ở đây sẽ thành "hoàn thành" trên màn hình — dự án vừa mở mà
    // báo xong 100% là sai theo hướng nguy hiểm nhất.
    Project::factory()->create(['status' => ProjectStatus::Planning->value]);

    $rows = $this->actingAs(giamDoc())
        ->getJson('/api/v1/dashboard/overview')
        ->json('data.projects.rows');

    expect($rows[0]['total'])->toBe(0)
        ->and($rows[0]['progress_percent'])->toBe(0);
});

it('không đưa dự án đã huỷ vào bảng tiến độ', function (): void {
    Project::factory()->create(['status' => ProjectStatus::Cancelled->value]);
    Project::factory()->create(['status' => ProjectStatus::Active->value]);

    $duAn = $this->actingAs(giamDoc())
        ->getJson('/api/v1/dashboard/overview')
        ->json('data.projects');

    expect($duAn['total'])->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Việc trễ lâu nhất
|--------------------------------------------------------------------------
*/

it('liệt kê việc trễ lâu nhất, cũ nhất lên đầu, kèm số ngày trễ', function (): void {
    $nv = User::factory()->create(['name' => 'Trần Văn B']);
    $duAn = Project::factory()->create(['name' => 'Dự án X']);

    Task::factory()->create([
        'title' => 'Trễ 2 ngày',
        'assignee_id' => $nv->id,
        'project_id' => $duAn->id,
        'status' => TaskStatus::Todo->value,
        'due_date' => now()->subDays(2),
    ]);
    Task::factory()->create([
        'title' => 'Trễ 10 ngày',
        'assignee_id' => $nv->id,
        'status' => TaskStatus::InProgress->value,
        'due_date' => now()->subDays(10),
    ]);

    $tre = $this->actingAs(giamDoc())
        ->getJson('/api/v1/dashboard/overview')
        ->json('data.most_overdue');

    expect($tre[0]['title'])->toBe('Trễ 10 ngày')
        ->and($tre[0]['days_overdue'])->toBe(10)
        ->and($tre[0]['assignee'])->toBe('Trần Văn B')
        ->and($tre[1]['title'])->toBe('Trễ 2 ngày')
        ->and($tre[1]['project'])->toBe('Dự án X');
});

it('việc chưa giao vẫn hiện trong danh sách trễ', function (): void {
    // Đây chính là loại việc dễ trôi nhất — không ai nhận thông báo nhắc hạn.
    Task::factory()->create([
        'title' => 'Không ai làm',
        'assignee_id' => null,
        'status' => TaskStatus::Todo->value,
        'due_date' => now()->subDays(4),
    ]);

    $tre = $this->actingAs(giamDoc())
        ->getJson('/api/v1/dashboard/overview')
        ->json('data.most_overdue');

    expect($tre[0]['title'])->toBe('Không ai làm')
        ->and($tre[0]['assignee'])->toBeNull();
});

/*
|--------------------------------------------------------------------------
| Hiệu năng
|--------------------------------------------------------------------------
*/

it('số truy vấn không tăng theo số nhân sự và số dự án', function (): void {
    // Đây là loại màn hình dễ thành N+1 nhất: "với mỗi người, đếm việc của họ".
    // Test chạy hai lần với lượng dữ liệu chênh nhau mười lần; nếu ai đó sau
    // này thay GROUP BY bằng vòng lặp thì lần thứ hai sẽ tốn nhiều truy vấn hơn
    // hẳn và test đỏ ngay.
    $dem = function (int $soNguoi): int {
        User::query()->whereNot('id', 1)->forceDelete();

        $gd = giamDoc();

        foreach (range(1, $soNguoi) as $i) {
            $u = User::factory()->create();
            $d = Project::factory()->create();
            Task::factory()->count(2)->create([
                'assignee_id' => $u->id,
                'project_id' => $d->id,
                'status' => TaskStatus::Todo->value,
                'due_date' => now()->subDays($i),
            ]);
        }

        // Xoá đệm quyền của spatie trước mỗi lần đo. Không xoá thì lần đo thứ
        // hai chạy trên đệm còn ấm và tốn ít hơn hai truy vấn — con số chênh
        // nhau vì lý do chẳng liên quan gì tới thứ đang muốn đo, và test sẽ đỏ
        // (hoặc tệ hơn: xanh nhầm) tuỳ thứ tự chạy.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->actingAs($gd)->getJson('/api/v1/dashboard/overview')->assertOk();

        $so = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $so;
    };

    $it = $dem(2);
    $nhieu = $dem(20);

    // Dữ liệu gấp mười lần, số truy vấn phải y hệt.
    expect($nhieu)->toBe($it)
        ->and($nhieu)->toBeLessThan(20);
});
