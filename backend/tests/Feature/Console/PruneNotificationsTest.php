<?php

declare(strict_types=1);

use App\Domain\Identity\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/*
|--------------------------------------------------------------------------
| Dọn thông báo cũ
|--------------------------------------------------------------------------
|
| Trước lệnh này, bảng `notifications` không có gì dọn nó. Điều dễ làm sai ở
| đây không phải "xoá được" mà là **xoá đúng thứ**: xoá nhầm thông báo chưa đọc
| của người vừa đi nghỉ dài, hoặc tệ hơn, đụng vào nhật ký kiểm toán.
|
*/

beforeEach(function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-08-12 09:00:00'));
});

function themThongBao(User $u, string $taoLuc, ?string $docLuc): string
{
    $id = (string) Str::uuid();

    DB::table('notifications')->insert([
        'id' => $id,
        'type' => 'App\\Test\\FakeNotification',
        'notifiable_type' => User::class,
        'notifiable_id' => $u->id,
        'data' => json_encode(['title' => 'thử'], JSON_THROW_ON_ERROR),
        'read_at' => $docLuc,
        'created_at' => $taoLuc,
        'updated_at' => $taoLuc,
    ]);

    return $id;
}

function conLai(): int
{
    return DB::table('notifications')->count();
}

it('xoá thông báo đã đọc cũ hơn 90 ngày', function (): void {
    $u = User::factory()->create();

    themThongBao($u, '2026-01-01 09:00:00', '2026-01-02 09:00:00');

    $this->artisan('notifications:prune')->assertSuccessful();

    expect(conLai())->toBe(0);
});

it('giữ thông báo đã đọc còn trong hạn', function (): void {
    $u = User::factory()->create();

    // Đọc hôm qua — còn xa mốc 90 ngày.
    themThongBao($u, '2026-08-11 09:00:00', '2026-08-11 10:00:00');

    $this->artisan('notifications:prune')->assertSuccessful();

    expect(conLai())->toBe(1);
});

it('giữ thông báo CHƯA đọc lâu hơn nhiều so với đã đọc', function (): void {
    /*
    | Đây là điểm dễ làm sai nhất: dùng một mốc chung cho cả hai. Xoá một thông
    | báo chưa ai đọc là xoá thứ có thể còn cần — người nghỉ thai sản sáu tháng
    | về vẫn nên thấy mình đã bỏ lỡ gì.
    */
    $u = User::factory()->create();

    // 120 ngày trước: quá mốc "đã đọc" (90) nhưng chưa tới mốc "chưa đọc" (365).
    themThongBao($u, '2026-04-14 09:00:00', null);

    $this->artisan('notifications:prune')->assertSuccessful();

    expect(conLai())->toBe(1);
});

it('xoá thông báo chưa đọc cũ hơn một năm', function (): void {
    $u = User::factory()->create();

    themThongBao($u, '2025-01-01 09:00:00', null);

    $this->artisan('notifications:prune')->assertSuccessful();

    expect(conLai())->toBe(0);
});

it('chạy thử chỉ đếm, không xoá', function (): void {
    $u = User::factory()->create();

    themThongBao($u, '2026-01-01 09:00:00', '2026-01-02 09:00:00');

    $this->artisan('notifications:prune --dry-run')
        ->expectsOutputToContain('Sẽ xoá 1 thông báo đã đọc')
        ->assertSuccessful();

    expect(conLai())->toBe(1);
});

it('xoá hết dù số dòng vượt quá một lô', function (): void {
    /*
    | Vòng lặp xoá theo lô dễ viết sai thành "xoá đúng một lô rồi thoát". Lỗi đó
    | không lộ ra khi test có 5 dòng — nó chỉ lộ ra ở production, dưới dạng bảng
    | vẫn phình lên đều đặn dù lệnh dọn tuần nào cũng báo thành công.
    |
    | Lô là 1.000 nên 1.200 dòng buộc phải chạy hai vòng.
    */
    $u = User::factory()->create();

    $dong = [];

    for ($i = 0; $i < 1200; $i++) {
        $dong[] = [
            'id' => (string) Str::uuid(),
            'type' => 'App\\Test\\FakeNotification',
            'notifiable_type' => User::class,
            'notifiable_id' => $u->id,
            'data' => '{"title":"thử"}',
            'read_at' => '2026-01-02 09:00:00',
            'created_at' => '2026-01-01 09:00:00',
            'updated_at' => '2026-01-01 09:00:00',
        ];
    }

    foreach (array_chunk($dong, 400) as $lo) {
        DB::table('notifications')->insert($lo);
    }

    $this->artisan('notifications:prune')->assertSuccessful();

    expect(conLai())->toBe(0);
});

it('không đụng tới nhật ký kiểm toán', function (): void {
    /*
    | `user_activities` và `payroll_audits` là nhật ký, không phải thông báo.
    | Chúng phải sống lâu hơn mọi chính sách dọn dẹp — và không được có lệnh nào
    | trong hệ thống xoá chúng.
    */
    $truoc = [
        'user_activities' => DB::table('user_activities')->count(),
        'payroll_audits' => DB::table('payroll_audits')->count(),
    ];

    $this->artisan('notifications:prune')->assertSuccessful();

    expect(DB::table('user_activities')->count())->toBe($truoc['user_activities'])
        ->and(DB::table('payroll_audits')->count())->toBe($truoc['payroll_audits']);
});
