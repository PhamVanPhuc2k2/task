<?php

declare(strict_types=1);

use App\Domain\Identity\Enums\Role;
use App\Domain\Identity\Models\Department;
use App\Domain\Identity\Models\User;
use App\Domain\Payroll\Enums\PayrollAuditEvent;
use App\Domain\Payroll\Models\PayrollAudit;
use App\Domain\Payroll\Models\SalaryRecord;
use Carbon\CarbonImmutable;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->withHeader('Origin', 'http://localhost:3000');
    $this->travelTo(CarbonImmutable::parse('2026-08-12 09:00:00'));
});

function nhanVienLuong(): User
{
    $u = User::factory()->for(Department::factory(), 'department')->create();
    $u->assignRole(Role::NhanVien->value);

    return $u;
}

/**
 * @param  array<string, mixed>  $ghiDe
 * @return array<string, mixed>
 */
function datLuong(array $ghiDe = []): array
{
    return array_merge([
        'base_salary' => '15000000',
        'allowance' => '2000000',
        'effective_from' => '2026-08-01',
        'reason' => 'Mức khởi điểm theo hợp đồng lao động.',
    ], $ghiDe);
}

/*
|--------------------------------------------------------------------------
| Lương KHÔNG được lọt ra qua hồ sơ nhân sự
|--------------------------------------------------------------------------
*/

it('không trả mức lương ở bất kỳ endpoint nhân sự nào', function (): void {
    // Đây là lý do bảng lương nằm riêng chứ không phải một cột trên `users`.
    // Test này khoá lại điều đó: nếu sau này ai đó "cho tiện" thêm cột lương
    // vào users, nó sẽ lọt ra qua UserResource và test đỏ ngay.
    $admin = quanTri();
    $nv = nhanVienLuong();

    $this->actingAs($admin)
        ->postJson("/api/v1/payroll/{$nv->uuid}", datLuong())
        ->assertCreated();

    foreach (["/api/v1/users?search={$nv->name}", '/api/v1/auth/me', '/api/v1/users/assignable'] as $duong) {
        $noiDung = $this->actingAs($admin)->getJson($duong)->assertOk()->getContent();

        expect((string) $noiDung)
            ->not->toContain('15000000')
            ->not->toContain('base_salary')
            ->not->toContain('salary');
    }
});

/*
|--------------------------------------------------------------------------
| Lịch sử, không phải một con số
|--------------------------------------------------------------------------
*/

it('đặt mức mới thì đóng mức cũ, không ghi đè', function (): void {
    // Ghi đè thì tính lại bảng lương tháng trước sẽ ra mức mới — sai, và sai
    // âm thầm cho tới khi có người đối chiếu bằng tay.
    $admin = quanTri();
    $nv = nhanVienLuong();

    $this->actingAs($admin)
        ->postJson("/api/v1/payroll/{$nv->uuid}", datLuong(['effective_from' => '2026-01-01']))
        ->assertCreated();

    $this->actingAs($admin)
        ->postJson("/api/v1/payroll/{$nv->uuid}", datLuong([
            'base_salary' => '18000000',
            'effective_from' => '2026-07-01',
            'reason' => 'Tăng lương theo kỳ đánh giá giữa năm.',
        ]))
        ->assertCreated();

    $ds = SalaryRecord::query()
        ->where('user_id', $nv->id)
        ->orderBy('effective_from')
        ->get();

    expect($ds)->toHaveCount(2)
        // Mức cũ đóng vào HÔM TRƯỚC ngày mức mới bắt đầu — không trừ một ngày
        // thì có đúng một ngày hai mức cùng hiệu lực.
        ->and($ds[0]?->effective_to?->toDateString())->toBe('2026-06-30')
        ->and($ds[1]?->effective_to)->toBeNull()
        ->and($ds[1]?->base_salary)->toBe('18000000.00');
});

it('chỉ có tối đa một mức đang hiệu lực', function (): void {
    $admin = quanTri();
    $nv = nhanVienLuong();

    foreach (['2026-01-01', '2026-04-01', '2026-07-01'] as $ngay) {
        $this->actingAs($admin)
            ->postJson("/api/v1/payroll/{$nv->uuid}", datLuong([
                'effective_from' => $ngay,
                'reason' => "Điều chỉnh từ {$ngay}.",
            ]))
            ->assertCreated();
    }

    expect(SalaryRecord::query()->where('user_id', $nv->id)->current()->count())->toBe(1);
});

it('chặn ngày hiệu lực không sau mức đang áp dụng', function (): void {
    $admin = quanTri();
    $nv = nhanVienLuong();

    $this->actingAs($admin)
        ->postJson("/api/v1/payroll/{$nv->uuid}", datLuong(['effective_from' => '2026-07-01']))
        ->assertCreated();

    $this->actingAs($admin)
        ->postJson("/api/v1/payroll/{$nv->uuid}", datLuong([
            'effective_from' => '2026-06-01',
            'reason' => 'Ghi lùi về trước mức hiện hành.',
        ]))
        ->assertStatus(422)
        ->assertJsonPath('code', 'SALARY_PERIOD_OVERLAP');

    expect(SalaryRecord::query()->where('user_id', $nv->id)->count())->toBe(1);
});

it('cho phép ghi lùi ngày miễn là sau mức hiện hành', function (): void {
    // Nhân sự nhập muộn vài ngày là chuyện bình thường, không nên chặn.
    $admin = quanTri();
    $nv = nhanVienLuong();

    $this->actingAs($admin)
        ->postJson("/api/v1/payroll/{$nv->uuid}", datLuong(['effective_from' => '2026-01-01']))
        ->assertCreated();

    $this->actingAs($admin)
        ->postJson("/api/v1/payroll/{$nv->uuid}", datLuong([
            'effective_from' => '2026-08-01',
            'reason' => 'Nhập muộn, hiệu lực từ đầu tháng.',
        ]))
        ->assertCreated();
});

/*
|--------------------------------------------------------------------------
| Tiền
|--------------------------------------------------------------------------
*/

it('trả số tiền dạng chuỗi, giữ nguyên độ chính xác', function (): void {
    $admin = quanTri();
    $nv = nhanVienLuong();

    $this->actingAs($admin)
        ->postJson("/api/v1/payroll/{$nv->uuid}", datLuong([
            'base_salary' => '12500000.10',
            'allowance' => '2000000.20',
        ]))
        ->assertCreated()
        ->assertJsonPath('data.base_salary', '12500000.10')
        // 12500000.10 + 2000000.20 — cộng bằng float ra 14500000.299999999.
        ->assertJsonPath('data.total', '14500000.30');
});

it('chặn số quá lớn — thường là gõ thừa số 0', function (): void {
    $admin = quanTri();
    $nv = nhanVienLuong();

    $this->actingAs($admin)
        ->postJson("/api/v1/payroll/{$nv->uuid}", datLuong(['base_salary' => '99000000000']))
        ->assertStatus(422)
        ->assertJsonValidationErrors('base_salary');
});

it('bắt buộc ghi lý do', function (): void {
    $admin = quanTri();
    $nv = nhanVienLuong();

    $this->actingAs($admin)
        ->postJson("/api/v1/payroll/{$nv->uuid}", datLuong(['reason' => 'ok']))
        ->assertStatus(422)
        ->assertJsonValidationErrors('reason');
});

/*
|--------------------------------------------------------------------------
| Quyền
|--------------------------------------------------------------------------
*/

it('nhân viên xem được lương của chính mình', function (): void {
    $admin = quanTri();
    $nv = nhanVienLuong();

    $this->actingAs($admin)->postJson("/api/v1/payroll/{$nv->uuid}", datLuong())->assertCreated();

    $this->actingAs($nv)
        ->getJson("/api/v1/payroll/{$nv->uuid}")
        ->assertOk()
        ->assertJsonPath('data.0.base_salary', '15000000.00')
        ->assertJsonPath('data.0.is_current', true);
});

it('nhân viên KHÔNG xem được lương người khác', function (): void {
    $nv = nhanVienLuong();
    $dongNghiep = nhanVienLuong();

    $this->actingAs($nv)
        ->getJson("/api/v1/payroll/{$dongNghiep->uuid}")
        ->assertForbidden();
});

it('nhân viên không vào được bảng lương công ty', function (): void {
    $this->actingAs(nhanVienLuong())->getJson('/api/v1/payroll')->assertForbidden();
});

it('trưởng phòng không xem được lương cấp dưới', function (): void {
    // Mặc định an toàn: trưởng phòng xem lương cấp dưới là quyết định chính
    // sách của công ty, không phải mặc nhiên.
    [$sep, $nhanVien] = sepVaNhanVien();

    $this->actingAs($sep)->getJson('/api/v1/payroll')->assertForbidden();
    $this->actingAs($sep)->getJson("/api/v1/payroll/{$nhanVien->uuid}")->assertForbidden();
});

it('nhân viên không đặt được lương cho ai', function (): void {
    $nv = nhanVienLuong();
    $dongNghiep = nhanVienLuong();

    $this->actingAs($nv)
        ->postJson("/api/v1/payroll/{$dongNghiep->uuid}", datLuong())
        ->assertForbidden();
});

it('không tự đặt lương cho chính mình dù có quyền', function (): void {
    // Cùng họ với luật chặn tự đổi vai trò và tự duyệt ngày công của mình.
    $admin = quanTri();

    $this->actingAs($admin)
        ->postJson("/api/v1/payroll/{$admin->uuid}", datLuong([
            'reason' => 'Tôi tự tăng lương cho tôi.',
        ]))
        ->assertForbidden();

    expect(SalaryRecord::query()->count())->toBe(0);
});

it('giám đốc xem và đặt được lương', function (): void {
    $gd = User::factory()->for(Department::factory(), 'department')->create();
    $gd->assignRole(Role::GiamDoc->value);
    $nv = nhanVienLuong();

    $this->actingAs($gd)->postJson("/api/v1/payroll/{$nv->uuid}", datLuong())->assertCreated();
    $this->actingAs($gd)->getJson('/api/v1/payroll')->assertOk();
});

/*
|--------------------------------------------------------------------------
| Bảng lương
|--------------------------------------------------------------------------
*/

it('hiện cả người chưa được đặt mức lương nào', function (): void {
    // `salary: null` là trạng thái hợp lệ, không phải lỗi — và là thứ người
    // quản lý cần thấy để biết còn ai chưa có lương.
    $admin = quanTri();
    $coLuong = nhanVienLuong();
    $chuaCo = nhanVienLuong();

    $this->actingAs($admin)->postJson("/api/v1/payroll/{$coLuong->uuid}", datLuong())->assertCreated();

    $rows = collect($this->actingAs($admin)->getJson('/api/v1/payroll')->assertOk()->json('data'));

    expect($rows->firstWhere('user.id', $coLuong->uuid)['salary']['total'])->toBe('17000000.00')
        ->and($rows->firstWhere('user.id', $chuaCo->uuid)['salary'])->toBeNull();
});

it('bảng lương chỉ lấy mức đang hiệu lực, không lấy mức cũ', function (): void {
    $admin = quanTri();
    $nv = nhanVienLuong();

    $this->actingAs($admin)->postJson("/api/v1/payroll/{$nv->uuid}", datLuong([
        'base_salary' => '10000000', 'effective_from' => '2026-01-01',
    ]))->assertCreated();

    $this->actingAs($admin)->postJson("/api/v1/payroll/{$nv->uuid}", datLuong([
        'base_salary' => '20000000', 'effective_from' => '2026-07-01',
        'reason' => 'Tăng lương giữa năm.',
    ]))->assertCreated();

    $rows = collect($this->actingAs($admin)->getJson('/api/v1/payroll')->json('data'));

    expect($rows->firstWhere('user.id', $nv->uuid)['salary']['base_salary'])->toBe('20000000.00');
});

/*
|--------------------------------------------------------------------------
| Nhật ký — ghi cả việc XEM
|--------------------------------------------------------------------------
*/

it('ghi nhật ký khi xem bảng lương công ty', function (): void {
    // Khác mọi nhật ký khác trong hệ thống. "Ai đã xem bảng lương phòng Kinh
    // doanh" là câu hỏi có thật và sẽ có người hỏi.
    $admin = quanTri();

    $this->actingAs($admin)->getJson('/api/v1/payroll')->assertOk();

    $moc = PayrollAudit::query()->sole();

    expect($moc->event)->toBe(PayrollAuditEvent::ViewedList)
        ->and($moc->actor_id)->toBe($admin->id)
        ->and($moc->subject_id)->toBeNull();
});

it('ghi nhật ký khi xem lương của người khác', function (): void {
    $admin = quanTri();
    $nv = nhanVienLuong();

    $this->actingAs($admin)->getJson("/api/v1/payroll/{$nv->uuid}")->assertOk();

    $moc = PayrollAudit::query()->sole();

    expect($moc->event)->toBe(PayrollAuditEvent::ViewedPerson)
        ->and($moc->subject_id)->toBe($nv->id);
});

it('KHÔNG ghi nhật ký khi tự xem lương của mình', function (): void {
    // Nhật ký này để trả lời "ai đã xem lương người khác". Ghi cả lượt tự xem
    // thì bảng đầy rác trong một tuần và không ai đọc nữa.
    $nv = nhanVienLuong();

    $this->actingAs($nv)->getJson("/api/v1/payroll/{$nv->uuid}")->assertOk();

    expect(PayrollAudit::query()->count())->toBe(0);
});

it('nhật ký ghi việc đổi lương nhưng KHÔNG ghi số tiền', function (): void {
    // Nhật ký kiểm toán mang theo dữ liệu nhạy cảm thì bản thân nó thành chỗ rò
    // rỉ thứ hai: ai đọc được nhật ký sẽ biết lương cả công ty mà không cần
    // quyền xem lương.
    $admin = quanTri();
    $nv = nhanVienLuong();

    $this->actingAs($admin)
        ->postJson("/api/v1/payroll/{$nv->uuid}", datLuong(['base_salary' => '15000000']))
        ->assertCreated();

    $moc = PayrollAudit::query()
        ->where('event', PayrollAuditEvent::SalaryChanged->value)
        ->sole();

    expect($moc->actor_id)->toBe($admin->id)
        ->and($moc->subject_id)->toBe($nv->id)
        ->and(json_encode($moc->context))->not->toContain('15000000');
});

it('nhật ký lương không sửa và không xoá được qua API', function (): void {
    $admin = quanTri();

    $this->actingAs($admin)->deleteJson('/api/v1/payroll')->assertStatus(405);
    $this->actingAs($admin)->patchJson('/api/v1/payroll')->assertStatus(405);
});

/*
|--------------------------------------------------------------------------
| Lịch sử đọc được
|--------------------------------------------------------------------------
*/

it('trả lịch sử mới nhất lên đầu, kèm người đặt mức', function (): void {
    $admin = quanTri();
    $nv = nhanVienLuong();

    $this->actingAs($admin)->postJson("/api/v1/payroll/{$nv->uuid}", datLuong([
        'base_salary' => '10000000', 'effective_from' => '2026-01-01',
        'reason' => 'Mức khởi điểm theo hợp đồng.',
    ]))->assertCreated();

    $this->actingAs($admin)->postJson("/api/v1/payroll/{$nv->uuid}", datLuong([
        'base_salary' => '20000000', 'effective_from' => '2026-07-01',
        'reason' => 'Tăng lương giữa năm.',
    ]))->assertCreated();

    $this->actingAs($admin)
        ->getJson("/api/v1/payroll/{$nv->uuid}")
        ->assertOk()
        ->assertJsonPath('data.0.effective_from', '2026-07-01')
        ->assertJsonPath('data.0.is_current', true)
        ->assertJsonPath('data.0.reason', 'Tăng lương giữa năm.')
        ->assertJsonPath('data.0.author.name', $admin->name)
        ->assertJsonPath('data.1.effective_from', '2026-01-01')
        ->assertJsonPath('data.1.effective_to', '2026-06-30')
        ->assertJsonPath('data.1.is_current', false);
});
