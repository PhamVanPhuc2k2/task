<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;

it('khởi động được và trả về phản hồi', function (): void {
    $this->get('/')->assertSuccessful();
});

/*
|--------------------------------------------------------------------------
| Quy ước múi giờ
|--------------------------------------------------------------------------
|
| Đây là ràng buộc nền của toàn hệ thống, không phải chi tiết cấu hình.
| Đổi 'timezone' sang giờ Việt Nam sẽ làm sai toàn bộ phép tính giờ công ở
| đợt 3. Test này tồn tại để lần đổi đó không lọt qua CI.
|
| Xem README, mục "Quy ước dữ liệu, thời gian & tiền tệ".
|
*/

it('lưu trữ thời gian ở UTC', function (): void {
    expect(config('app.timezone'))->toBe('UTC')
        ->and(date_default_timezone_get())->toBe('UTC');
});

it('có múi giờ hiển thị riêng là giờ Việt Nam', function (): void {
    expect(config('app.display_timezone'))->toBe('Asia/Ho_Chi_Minh');
});

it('dùng CarbonImmutable để ngày giờ không bị sửa tại chỗ', function (): void {
    $now = Date::now();
    $later = $now->addDay();

    expect($now)->toBeInstanceOf(CarbonImmutable::class)
        ->and($now->toDateTimeString())->not->toBe($later->toDateTimeString());
});

it('kết nối được cơ sở dữ liệu', function (): void {
    expect(DB::connection()->getPdo())->not->toBeNull();
});
