<?php

declare(strict_types=1);

use App\Domain\Identity\Enums\Role;
use App\Domain\Identity\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/*
|--------------------------------------------------------------------------
| Chi phí của một nhịp tim
|--------------------------------------------------------------------------
|
| Đây là đường được gọi nhiều nhất cả hệ thống: hai trăm nhân sự × tám tiếng ≈
| 96.000 lượt mỗi ngày. Mỗi truy vấn thừa ở đây nhân lên chín mươi sáu nghìn
| lần.
|
| Test khoá con số lại. Ai thêm một truy vấn vào đường này sẽ thấy test đỏ và
| phải quyết định có đáng hay không — thay vì để nó trôi vào production rồi sáu
| tháng sau mới có người đi tìm vì sao database bận.
|
*/

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->withHeader('Origin', 'http://localhost:3000');
});

function demTruyVanNhip(User $u): int
{
    // Đệm quyền của spatie và trạng thái model trong bộ nhớ đều làm sai phép
    // đo — xem ReportReconciliationTest để biết lần trước đã đo nhầm thế nào.
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    /** @var User $actor */
    $actor = User::query()->findOrFail($u->id);

    DB::flushQueryLog();
    DB::enableQueryLog();

    test()->actingAs($actor)->postJson('/api/v1/attendance/heartbeat')->assertOk();

    $so = count(DB::getRawQueryLog());
    DB::disableQueryLog();

    return $so;
}

it('một nhịp tim tốn đúng 5 truy vấn', function (): void {
    /*
    | Năm truy vấn đó là:
    |   1. cộng số phút đã ghi hôm nay, để biết đã chạm trần chưa
    |   2. tìm phiên làm việc gần nhất
    |   3. nối dài phiên đang mở
    |   4. tổng hợp số phút hôm nay
    |   5. đọc quyết định ngày công (ghi nhận / bỏ qua) cho ngày hôm nay
    |
    | Trước khi tối ưu là SÁU: ba truy vấn đầu là nạp vai trò và quyền, mà
    | endpoint này không kiểm quyền nào cả. Xem EnsureUserIsActive.
    |
    | ── Vì sao chấp nhận truy vấn thứ nhất ───────────────────────────────────
    |
    | Nó là cái giá của việc đổi sang chấm công theo sự có mặt. Khi tab mở là
    | tính, một tab quên đóng qua đêm ghi thẳng 16 tiếng công — và vài lần như
    | vậy là không ai còn tin bảng công nữa. Trần ngày là thứ duy nhất chặn
    | được, mà muốn biết đã chạm trần thì phải cộng.
    |
    | Chi phí thật: một phép SUM trên chỉ mục `(user_id, work_date)` với khoảng
    | 5–20 dòng. Nó không quét bảng, không join, và không nạp model nào.
    |
    | Đã cân nhắc hai cách né và bỏ cả hai:
    |
    |   - Gộp vào truy vấn tìm phiên bằng window function: tiết kiệm một lượt
    |     đi về, đổi lấy một câu SQL mà người đọc sau phải dừng lại mất một
    |     phút. Không đáng với mức tiết kiệm này.
    |   - Đệm tổng hôm nay vào Redis: vẫn là một lượt đi về, thêm một chỗ có
    |     thể lệch với sự thật.
    |
    | Số 4 vẫn được khoá lại y như số 3 trước đây. Ai thêm truy vấn thứ năm sẽ
    | phải viết ra lý do ở đúng chỗ này.
    */
    $u = User::factory()->create();
    $u->assignRole(Role::NhanVien->value);

    // Nhịp đầu mở phiên mới; đo nhịp thứ hai vì đó mới là trường hợp thường
    // gặp — 479 trong 480 nhịp của một ngày làm việc là nối dài phiên đang mở.
    nhip($u);

    /*
    | `travelTo` KHÔNG phải để cho gọn — thiếu nó thì test này CHẬP CHỜN.
    |
    | Hai nhịp liên tiếp trong cùng một giây làm `ended_at` không đổi, nên
    | Eloquent thấy model không bẩn và **bỏ hẳn lệnh UPDATE**. Đếm ra 4. Nhưng
    | khi hai nhịp vô tình vắt qua ranh giới giây — hay gặp lúc chạy song song
    | 16 tiến trình, máy bận — thì UPDATE chạy và đếm ra 5.
    |
    | Test cũ khoá số 3 và đã mang sẵn lỗi này từ đầu; nó chỉ hiếm khi đỏ nên
    | không ai để ý. Một test lúc xanh lúc đỏ còn tệ hơn test đỏ hẳn: người ta
    | chạy lại lần nữa thấy xanh rồi đi tiếp, và cái nó canh mất tác dụng.
    |
    | Nhích một phút thì nhịp đo được LUÔN nối dài phiên — đúng trường hợp
    | thường gặp mà test này muốn đo, và đếm ra một con số duy nhất.
    */
    $this->travelTo(now()->addMinute());

    expect(demTruyVanNhip($u))->toBe(5);
});

it('không nạp vai trò và quyền trên đường nhịp tim', function (): void {
    // Kiểm thẳng vào thứ đã bỏ đi, không chỉ đếm tổng: nếu ai đó bỏ ba truy vấn
    // khác rồi thêm lại phần nạp quyền thì tổng vẫn là 3 mà ý định đã mất.
    $u = User::factory()->create();
    $u->assignRole(Role::NhanVien->value);
    nhip($u);

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    /** @var User $actor */
    $actor = User::query()->findOrFail($u->id);

    DB::flushQueryLog();
    DB::enableQueryLog();
    test()->actingAs($actor)->postJson('/api/v1/attendance/heartbeat');
    $sql = collect(DB::getRawQueryLog())->pluck('raw_query')->implode(' | ');
    DB::disableQueryLog();

    expect($sql)->not->toContain('model_has_roles')
        ->and($sql)->not->toContain('role_has_permissions');
});

it('các đường KHÁC vẫn nạp quyền như cũ', function (): void {
    /*
    | Mặc định phải là CÓ nạp. Quên khai thì chỉ tốn ba truy vấn; nếu mặc định
    | là không nạp thì quên khai là ăn N+1 âm thầm, hoặc lỗi preventLazyLoading
    | ở một endpoint bất kỳ. Sai theo hướng an toàn.
    */
    $u = quanTri();

    DB::flushQueryLog();
    DB::enableQueryLog();
    $this->actingAs($u)->getJson('/api/v1/auth/me')->assertOk();
    $sql = collect(DB::getRawQueryLog())->pluck('raw_query')->implode(' | ');
    DB::disableQueryLog();

    expect($sql)->toContain('model_has_roles');
});
