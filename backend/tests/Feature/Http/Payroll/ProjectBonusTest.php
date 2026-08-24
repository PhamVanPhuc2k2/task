<?php

declare(strict_types=1);

use App\Domain\Identity\Enums\Role;
use App\Domain\Identity\Models\Department;
use App\Domain\Identity\Models\User;
use App\Domain\Payroll\Models\BonusAllocation;
use App\Domain\Payroll\Models\BonusPool;
use App\Domain\Payroll\Notifications\BonusLockedNotification;
use App\Domain\Task\Models\Project;
use Carbon\CarbonImmutable;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->withHeader('Origin', 'http://localhost:3000');
    $this->travelTo(CarbonImmutable::parse('2026-08-12 09:00:00'));
});

function nguoiChiaThuong(): User
{
    $u = User::factory()->for(Department::factory(), 'department')->create();
    $u->assignRole(Role::TruongPhong->value);

    return $u;
}

/** @return array{Project, BonusPool} */
function quyThuong(User $actor, string $tong = '50000000'): array
{
    $duAn = Project::factory()->create();

    // `assertSuccessful` chứ không `assertOk`: Laravel trả 201 khi resource bọc
    // một model vừa tạo và 200 khi cập nhật. Cả hai đều đúng ở đây.
    test()->actingAs($actor)
        ->postJson("/api/v1/projects/{$duAn->uuid}/bonus", [
            'total_amount' => $tong,
            'condition_note' => 'Dự án nghiệm thu đúng hạn và khách hàng thanh toán đủ.',
        ])
        ->assertSuccessful();

    return [$duAn, BonusPool::query()->where('project_id', $duAn->id)->sole()];
}

/*
|--------------------------------------------------------------------------
| Không bao giờ có số âm — ràng buộc pháp lý, không phải kỹ thuật
|--------------------------------------------------------------------------
|
| Điều 127 khoản 2 Bộ luật Lao động 2019 cấm "phạt tiền, cắt lương thay việc
| xử lý kỷ luật lao động". Một khoản "trừ thưởng" mang số âm chính là khoản
| phạt trừ vào thu nhập, dù cột đó tên gì.
|
*/

it('từ chối phần chia mang số âm', function (): void {
    $sep = nguoiChiaThuong();
    [$duAn] = quyThuong($sep);
    $nv = User::factory()->create();

    $this->actingAs($sep)
        ->putJson("/api/v1/projects/{$duAn->uuid}/bonus/allocations", [
            'allocations' => [[
                'user_id' => $nv->uuid,
                'amount' => '-5000000',
                'reason' => 'Trừ vì làm chậm tiến độ.',
            ]],
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('allocations.0.amount');

    expect(BonusAllocation::query()->count())->toBe(0);
});

it('database TỪ CHỐI số âm kể cả khi bỏ qua tầng validate', function (): void {
    // Ràng buộc CHECK ở database là lớp cuối cùng. Kiểm ở tầng Action thôi thì
    // một Action mới viết sau này có thể quên; kiểm ở database thì mọi đường
    // ghi đều đâm vào cùng một bức tường, kể cả câu UPDATE gõ tay trong tinker.
    $sep = nguoiChiaThuong();
    [, $quy] = quyThuong($sep);
    $nv = User::factory()->create();

    expect(fn () => DB::table('bonus_allocations')->insert([
        'uuid' => (string) Str::uuid(),
        'pool_id' => $quy->id,
        'user_id' => $nv->id,
        'amount' => -1000,
        'reason' => 'Ghi thẳng vào database, bỏ qua mọi tầng ứng dụng.',
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});

it('cho phép chia 0 đồng — đó là giảm thưởng, không phải phạt', function (): void {
    // Đây là điểm mấu chốt của thiết kế: muốn ai đó không nhận gì thì đặt 0,
    // chứ không phải trừ vào khoản khác.
    $sep = nguoiChiaThuong();
    [$duAn] = quyThuong($sep);
    $nv = User::factory()->create();

    $this->actingAs($sep)
        ->putJson("/api/v1/projects/{$duAn->uuid}/bonus/allocations", [
            'allocations' => [[
                'user_id' => $nv->uuid,
                'amount' => '0',
                'reason' => 'Không tham gia giai đoạn cuối của dự án.',
            ]],
        ])
        ->assertOk()
        ->assertJsonPath('data.allocations.0.amount', '0.00');
});

/*
|--------------------------------------------------------------------------
| Không vượt quỹ
|--------------------------------------------------------------------------
*/

it('chặn tổng phần chia vượt quá quỹ', function (): void {
    // Chia vượt quỹ là lỗi kế toán phát hiện sau, lúc tiền đã hứa với nhân
    // viên rồi.
    $sep = nguoiChiaThuong();
    [$duAn] = quyThuong($sep, '10000000');
    $a = User::factory()->create();
    $b = User::factory()->create();

    $this->actingAs($sep)
        ->putJson("/api/v1/projects/{$duAn->uuid}/bonus/allocations", [
            'allocations' => [
                ['user_id' => $a->uuid, 'amount' => '6000000', 'reason' => 'Chủ trì phần backend.'],
                ['user_id' => $b->uuid, 'amount' => '6000000', 'reason' => 'Chủ trì phần giao diện.'],
            ],
        ])
        ->assertStatus(422)
        ->assertJsonPath('code', 'BONUS_EXCEEDS_POOL');

    expect(BonusAllocation::query()->count())->toBe(0);
});

it('chia đúng bằng quỹ thì được', function (): void {
    $sep = nguoiChiaThuong();
    [$duAn] = quyThuong($sep, '10000000');
    $a = User::factory()->create();

    $this->actingAs($sep)
        ->putJson("/api/v1/projects/{$duAn->uuid}/bonus/allocations", [
            'allocations' => [
                ['user_id' => $a->uuid, 'amount' => '10000000', 'reason' => 'Làm toàn bộ dự án một mình.'],
            ],
        ])
        ->assertOk()
        ->assertJsonPath('data.remaining', '0.00');
});

it('không hạ quỹ xuống dưới tổng đã chia', function (): void {
    $sep = nguoiChiaThuong();
    [$duAn] = quyThuong($sep, '10000000');
    $a = User::factory()->create();

    $this->actingAs($sep)->putJson("/api/v1/projects/{$duAn->uuid}/bonus/allocations", [
        'allocations' => [['user_id' => $a->uuid, 'amount' => '8000000', 'reason' => 'Đóng góp chính.']],
    ])->assertOk();

    $this->actingAs($sep)
        ->postJson("/api/v1/projects/{$duAn->uuid}/bonus", [
            'total_amount' => '5000000',
            'condition_note' => 'Hạ quỹ xuống dưới phần đã chia.',
        ])
        ->assertStatus(422)
        ->assertJsonPath('code', 'BONUS_EXCEEDS_POOL');
});

/*
|--------------------------------------------------------------------------
| Chốt một chiều
|--------------------------------------------------------------------------
*/

it('chốt xong thì không sửa được quỹ lẫn phần chia', function (): void {
    $sep = nguoiChiaThuong();
    [$duAn] = quyThuong($sep);
    $a = User::factory()->create();

    $this->actingAs($sep)->putJson("/api/v1/projects/{$duAn->uuid}/bonus/allocations", [
        'allocations' => [['user_id' => $a->uuid, 'amount' => '5000000', 'reason' => 'Đóng góp chính.']],
    ])->assertOk();

    $this->actingAs($sep)
        ->postJson("/api/v1/projects/{$duAn->uuid}/bonus/status", ['status' => 'locked'])
        ->assertOk()
        ->assertJsonPath('data.status', 'locked')
        ->assertJsonPath('data.is_editable', false);

    $this->actingAs($sep)
        ->postJson("/api/v1/projects/{$duAn->uuid}/bonus", [
            'total_amount' => '99000000',
            'condition_note' => 'Đổi quỹ sau khi đã chốt.',
        ])
        ->assertStatus(422)
        ->assertJsonPath('code', 'BONUS_POOL_NOT_EDITABLE');

    $this->actingAs($sep)
        ->putJson("/api/v1/projects/{$duAn->uuid}/bonus/allocations", [
            'allocations' => [['user_id' => $a->uuid, 'amount' => '9000000', 'reason' => 'Đổi sau khi chốt.']],
        ])
        ->assertStatus(422)
        ->assertJsonPath('code', 'BONUS_POOL_NOT_EDITABLE');
});

it('không quay ngược từ đã chốt về đang lập', function (): void {
    // Mở lại một quỹ đã chốt nghĩa là đổi được con số mà nhân viên đã nhìn thấy.
    $sep = nguoiChiaThuong();
    [$duAn] = quyThuong($sep);

    $this->actingAs($sep)
        ->postJson("/api/v1/projects/{$duAn->uuid}/bonus/status", ['status' => 'locked'])
        ->assertOk();

    $this->actingAs($sep)
        ->postJson("/api/v1/projects/{$duAn->uuid}/bonus/status", ['status' => 'draft'])
        ->assertStatus(422);
});

it('không nhảy thẳng từ đang lập sang đã chi', function (): void {
    $sep = nguoiChiaThuong();
    [$duAn] = quyThuong($sep);

    $this->actingAs($sep)
        ->postJson("/api/v1/projects/{$duAn->uuid}/bonus/status", ['status' => 'distributed'])
        ->assertStatus(422)
        ->assertJsonPath('code', 'INVALID_BONUS_POOL_TRANSITION');
});

/*
|--------------------------------------------------------------------------
| Lý do bắt buộc
|--------------------------------------------------------------------------
*/

it('mỗi phần chia phải có lý do', function (): void {
    $sep = nguoiChiaThuong();
    [$duAn] = quyThuong($sep);
    $a = User::factory()->create();

    $this->actingAs($sep)
        ->putJson("/api/v1/projects/{$duAn->uuid}/bonus/allocations", [
            'allocations' => [['user_id' => $a->uuid, 'amount' => '5000000', 'reason' => 'ok']],
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('allocations.0.reason');
});

it('quỹ phải ghi điều kiện mở', function (): void {
    $sep = nguoiChiaThuong();
    $duAn = Project::factory()->create();

    $this->actingAs($sep)
        ->postJson("/api/v1/projects/{$duAn->uuid}/bonus", ['total_amount' => '10000000'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('condition_note');
});

/*
|--------------------------------------------------------------------------
| Thay thế toàn bộ danh sách
|--------------------------------------------------------------------------
*/

it('bỏ một người khỏi danh sách thì họ hết phần', function (): void {
    // Cập nhật từng dòng sẽ để sót người đã bị bỏ khỏi danh sách, và họ vẫn
    // nhận thưởng.
    $sep = nguoiChiaThuong();
    [$duAn] = quyThuong($sep);
    $a = User::factory()->create();
    $b = User::factory()->create();

    $this->actingAs($sep)->putJson("/api/v1/projects/{$duAn->uuid}/bonus/allocations", [
        'allocations' => [
            ['user_id' => $a->uuid, 'amount' => '3000000', 'reason' => 'Phần backend của dự án.'],
            ['user_id' => $b->uuid, 'amount' => '3000000', 'reason' => 'Phần giao diện của dự án.'],
        ],
    ])->assertOk();

    $this->actingAs($sep)->putJson("/api/v1/projects/{$duAn->uuid}/bonus/allocations", [
        'allocations' => [
            ['user_id' => $a->uuid, 'amount' => '6000000', 'reason' => 'Làm cả phần của người kia.'],
        ],
    ])->assertOk();

    expect(BonusAllocation::query()->count())->toBe(1)
        ->and(BonusAllocation::query()->sole()->user_id)->toBe($a->id);
});

/*
|--------------------------------------------------------------------------
| Báo cho nhân viên khi chốt
|--------------------------------------------------------------------------
*/

it('báo cho mọi người trong danh sách khi chốt quỹ', function (): void {
    // Không có thông báo thì cả cơ chế minh bạch mất một nửa: nhân viên xem
    // được phần của mình nhưng không ai biết mà vào xem.
    Notification::fake();

    $sep = nguoiChiaThuong();
    [$duAn] = quyThuong($sep);
    $a = User::factory()->create();
    $b = User::factory()->create();

    $this->actingAs($sep)->putJson("/api/v1/projects/{$duAn->uuid}/bonus/allocations", [
        'allocations' => [
            ['user_id' => $a->uuid, 'amount' => '3000000', 'reason' => 'Phần backend của dự án.'],
            // Người được 0 đồng VẪN được báo — họ cần đọc lý do. Im lặng với
            // riêng nhóm đó là cách chắc chắn nhất để sinh tin đồn.
            ['user_id' => $b->uuid, 'amount' => '0', 'reason' => 'Không tham gia giai đoạn cuối.'],
        ],
    ])->assertOk();

    Notification::assertNothingSent();

    $this->actingAs($sep)
        ->postJson("/api/v1/projects/{$duAn->uuid}/bonus/status", ['status' => 'locked'])
        ->assertOk();

    Notification::assertSentTo([$a, $b], BonusLockedNotification::class);
});

it('thông báo KHÔNG chứa số tiền', function (): void {
    // Thông báo gửi cả qua email; số tiền thưởng nằm trong hộp thư cá nhân là
    // một bản sao dữ liệu nhạy cảm ngoài tầm kiểm soát của hệ thống.
    $sep = nguoiChiaThuong();
    [$duAn] = quyThuong($sep);
    $a = User::factory()->create();
    $a->assignRole(Role::NhanVien->value);

    $this->actingAs($sep)->putJson("/api/v1/projects/{$duAn->uuid}/bonus/allocations", [
        'allocations' => [['user_id' => $a->uuid, 'amount' => '7654321', 'reason' => 'Đóng góp chính của dự án.']],
    ])->assertOk();

    $this->actingAs($sep)
        ->postJson("/api/v1/projects/{$duAn->uuid}/bonus/status", ['status' => 'locked'])
        ->assertOk();

    // Cột `data` đã là chuỗi JSON sẵn — `json_encode` thẳng lên nó sẽ bọc thêm
    // một lớp thoát nữa và tiếng Việt thành `ự`, khiến phép so chuỗi
    // luôn trượt kể cả khi dữ liệu đúng. Giải mã trước rồi mã hoá lại.
    $noiDung = json_encode(
        DB::table('notifications')
            ->where('notifiable_id', $a->id)
            ->pluck('data')
            ->map(fn (string $json): mixed => json_decode($json, associative: true))
            ->all(),
        JSON_UNESCAPED_UNICODE,
    );

    expect($noiDung)->not->toContain('7654321')
        ->and($noiDung)->toContain($duAn->name);
});

it('không báo lại khi đánh dấu đã chi', function (): void {
    // Lúc chi thì nhân viên đã biết số của mình từ lâu; báo thêm lần nữa là
    // nhiễu.
    $sep = nguoiChiaThuong();
    [$duAn] = quyThuong($sep);
    $a = User::factory()->create();

    $this->actingAs($sep)->putJson("/api/v1/projects/{$duAn->uuid}/bonus/allocations", [
        'allocations' => [['user_id' => $a->uuid, 'amount' => '3000000', 'reason' => 'Đóng góp chính của dự án.']],
    ])->assertOk();

    $this->actingAs($sep)->postJson("/api/v1/projects/{$duAn->uuid}/bonus/status", ['status' => 'locked'])->assertOk();

    Notification::fake();

    $this->actingAs($sep)
        ->postJson("/api/v1/projects/{$duAn->uuid}/bonus/status", ['status' => 'distributed'])
        ->assertOk();

    Notification::assertNothingSent();
});

it('không gửi thông báo cho người đã nghỉ việc', function (): void {
    Notification::fake();

    $sep = nguoiChiaThuong();
    [$duAn] = quyThuong($sep);
    $daNghi = User::factory()->create(['is_active' => false]);

    $this->actingAs($sep)->putJson("/api/v1/projects/{$duAn->uuid}/bonus/allocations", [
        'allocations' => [['user_id' => $daNghi->uuid, 'amount' => '3000000', 'reason' => 'Đóng góp trước khi nghỉ.']],
    ])->assertOk();

    $this->actingAs($sep)->postJson("/api/v1/projects/{$duAn->uuid}/bonus/status", ['status' => 'locked'])->assertOk();

    // Phần thưởng vẫn giữ nguyên — kế toán vẫn phải chi. Chỉ là không gửi
    // thông báo vào một tài khoản đã vô hiệu hoá.
    Notification::assertNothingSent();
    expect(BonusAllocation::query()->where('user_id', $daNghi->id)->exists())->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| Nhân viên xem phần của mình
|--------------------------------------------------------------------------
*/

it('nhân viên chỉ thấy phần của mình sau khi quỹ đã chốt', function (): void {
    // Bản nháp có con số còn đổi; đã cho xem một lần thì mọi lần đổi sau đều bị
    // đọc thành "bị cắt bớt", kể cả khi con số tăng lên.
    $sep = nguoiChiaThuong();
    [$duAn] = quyThuong($sep);

    $nv = User::factory()->create();
    $nv->assignRole(Role::NhanVien->value);

    $this->actingAs($sep)->putJson("/api/v1/projects/{$duAn->uuid}/bonus/allocations", [
        'allocations' => [['user_id' => $nv->uuid, 'amount' => '5000000', 'reason' => 'Hoàn thành đúng hạn phần được giao.']],
    ])->assertOk();

    // Còn nháp: chưa thấy gì.
    $this->actingAs($nv)
        ->getJson('/api/v1/bonus/me')
        ->assertOk()
        ->assertJsonPath('data.total', '0.00')
        ->assertJsonCount(0, 'data.items');

    $this->actingAs($sep)
        ->postJson("/api/v1/projects/{$duAn->uuid}/bonus/status", ['status' => 'locked'])
        ->assertOk();

    $this->actingAs($nv)
        ->getJson('/api/v1/bonus/me')
        ->assertOk()
        ->assertJsonPath('data.total', '5000000.00')
        ->assertJsonPath('data.items.0.amount', '5000000.00')
        // Trả kèm lý do, có chủ ý: quỹ thưởng bí mật là nguồn nghi ngờ lớn nhất.
        ->assertJsonPath('data.items.0.reason', 'Hoàn thành đúng hạn phần được giao.')
        ->assertJsonPath('data.items.0.project', $duAn->name);
});

it('nhân viên không thấy phần của người khác', function (): void {
    $sep = nguoiChiaThuong();
    [$duAn] = quyThuong($sep);

    $toi = User::factory()->create();
    $toi->assignRole(Role::NhanVien->value);
    $nguoiKhac = User::factory()->create();

    $this->actingAs($sep)->putJson("/api/v1/projects/{$duAn->uuid}/bonus/allocations", [
        'allocations' => [
            ['user_id' => $toi->uuid, 'amount' => '2000000', 'reason' => 'Phần việc của tôi.'],
            ['user_id' => $nguoiKhac->uuid, 'amount' => '8000000', 'reason' => 'Phần việc của người kia.'],
        ],
    ])->assertOk();

    $this->actingAs($sep)->postJson("/api/v1/projects/{$duAn->uuid}/bonus/status", ['status' => 'locked'])->assertOk();

    $noiDung = $this->actingAs($toi)->getJson('/api/v1/bonus/me')->assertOk()->getContent();

    expect((string) $noiDung)
        ->toContain('2000000.00')
        ->not->toContain('8000000')
        ->not->toContain('Phần việc của người kia');
});

it('nhân viên không xem được quỹ thưởng của dự án', function (): void {
    $sep = nguoiChiaThuong();
    [$duAn] = quyThuong($sep);

    $nv = User::factory()->create();
    $nv->assignRole(Role::NhanVien->value);

    $this->actingAs($nv)->getJson("/api/v1/projects/{$duAn->uuid}/bonus")->assertForbidden();
});

it('nhân viên không lập được quỹ thưởng', function (): void {
    $nv = User::factory()->create();
    $nv->assignRole(Role::NhanVien->value);
    $duAn = Project::factory()->create();

    $this->actingAs($nv)
        ->postJson("/api/v1/projects/{$duAn->uuid}/bonus", [
            'total_amount' => '10000000',
            'condition_note' => 'Tôi tự lập quỹ thưởng.',
        ])
        ->assertForbidden();
});

/*
|--------------------------------------------------------------------------
| Trưởng phòng chia thưởng nhưng không xem được lương
|--------------------------------------------------------------------------
*/

it('trưởng phòng chia được thưởng mà vẫn không xem được lương', function (): void {
    // Hai khoản khác nhau về bản chất nên tách quyền. Gộp chung thì muốn cho
    // trưởng phòng chia thưởng là phải mở luôn quyền xem lương cả công ty.
    $sep = nguoiChiaThuong();
    [$duAn] = quyThuong($sep);

    $this->actingAs($sep)->getJson("/api/v1/projects/{$duAn->uuid}/bonus")->assertOk();
    $this->actingAs($sep)->getJson('/api/v1/payroll')->assertForbidden();
});

/*
|--------------------------------------------------------------------------
| Tiền
|--------------------------------------------------------------------------
*/

it('cộng tiền chính xác, không qua float', function (): void {
    $sep = nguoiChiaThuong();
    [$duAn] = quyThuong($sep, '20000000');
    $a = User::factory()->create();
    $b = User::factory()->create();

    $this->actingAs($sep)
        ->putJson("/api/v1/projects/{$duAn->uuid}/bonus/allocations", [
            'allocations' => [
                ['user_id' => $a->uuid, 'amount' => '12500000.10', 'reason' => 'Phần backend của dự án.'],
                ['user_id' => $b->uuid, 'amount' => '2000000.20', 'reason' => 'Phần giao diện của dự án.'],
            ],
        ])
        ->assertOk()
        // Cộng bằng float ra 14500000.299999999.
        ->assertJsonPath('data.allocated_total', '14500000.30')
        ->assertJsonPath('data.remaining', '5499999.70');
});

it('một dự án chỉ có một quỹ', function (): void {
    $sep = nguoiChiaThuong();
    [$duAn] = quyThuong($sep, '10000000');

    $this->actingAs($sep)
        ->postJson("/api/v1/projects/{$duAn->uuid}/bonus", [
            'total_amount' => '20000000',
            'condition_note' => 'Nâng quỹ lên sau khi thương lượng lại.',
        ])
        ->assertOk()
        ->assertJsonPath('data.total_amount', '20000000.00');

    expect(BonusPool::query()->where('project_id', $duAn->id)->count())->toBe(1);
});
