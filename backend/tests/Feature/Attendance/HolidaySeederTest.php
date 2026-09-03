<?php

declare(strict_types=1);

use App\Domain\Attendance\Models\Holiday;
use Carbon\CarbonImmutable;
use Database\Seeders\HolidaySeeder;

/*
|--------------------------------------------------------------------------
| Ngày nghỉ lễ
|--------------------------------------------------------------------------
|
| Bảng `holidays` trước đó TRỐNG. Hệ quả không nhỏ: hệ thống coi mùng 1 Tết là
| ngày làm việc bình thường, cột đối chiếu gắn cờ "có giờ làm mà chưa báo cáo"
| cho cả công ty suốt kỳ nghỉ, và lệnh nhắc 17h30 vẫn bắn email.
|
| Bộ test này KHÔNG kiểm được ngày âm lịch có đúng không — đó là dữ liệu phải
| đối chiếu với thông báo của Chính phủ. Nó kiểm phần máy làm: đủ số ngày, đúng
| luật nghỉ bù, và chạy lại không nhân đôi.
|
*/

beforeEach(function (): void {
    $this->seed(HolidaySeeder::class);
});

it('đủ 11 ngày lễ mỗi năm theo Điều 112', function (): void {
    $so = Holiday::query()
        ->whereBetween('date', ['2026-01-01', '2026-12-31'])
        ->count();

    expect($so)->toBe(11);
});

it('nghỉ Tết đúng 5 ngày liên tiếp', function (): void {
    $tet = Holiday::query()
        ->where('name', 'like', 'Tết Nguyên đán%')
        ->whereBetween('date', ['2026-01-01', '2026-12-31'])
        ->orderBy('date')
        ->pluck('date')
        ->all();

    expect($tet)->toHaveCount(5)
        // Từ 30 tháng Chạp tới mùng 4 — mùng 1 Tết 2026 là 17/02.
        ->and($tet[0])->toBe('2026-02-16')
        ->and($tet[4])->toBe('2026-02-20');
});

it('đẩy ngày nghỉ bù khi ngày lễ rơi vào ngày nghỉ hằng tuần', function (): void {
    // Khoản 3 Điều 112. Giỗ Tổ 2026 rơi chủ nhật 26/04.
    $gioTo = Holiday::query()->where('date', '2026-04-26')->firstOrFail();

    expect(CarbonImmutable::parse($gioTo->date)->dayOfWeek)->toBe(0)
        ->and($gioTo->observed_date)->toBe('2026-04-27');
});

it('nhảy qua CẢ hai ngày cuối tuần chứ không chỉ một', function (): void {
    /*
    | Lỗi kinh điển: cộng đúng một ngày. Ngày lễ rơi thứ bảy thì cộng một ngày
    | ra chủ nhật — vẫn là ngày nghỉ, và người lao động mất một ngày nghỉ bù mà
    | bảng công không nói gì.
    */
    // Công ty nghỉ cả thứ bảy: không ngày nào làm nửa buổi.
    config()->set('attendance.work_days_full', '1,2,3,4,5');
    config()->set('attendance.work_days_half', '');

    Holiday::query()->delete();
    $this->seed(HolidaySeeder::class);

    foreach (Holiday::query()->get() as $h) {
        expect(CarbonImmutable::parse($h->observed_date)->dayOfWeek)
            ->not->toBeIn([0, 6]);
    }
});

it('không bao giờ nghỉ bù về trước ngày lễ', function (): void {
    foreach (Holiday::query()->get() as $h) {
        expect($h->observed_date)->toBeGreaterThanOrEqual($h->date);
    }
});

it('chạy lại không nhân đôi dữ liệu', function (): void {
    // Seeder là thứ người vận hành chạy lại khi sửa một ngày sai. Không idempotent
    // thì lần chạy thứ hai để lại hai bản ghi cho cùng một ngày.
    $truoc = Holiday::query()->count();

    $this->seed(HolidaySeeder::class);

    expect(Holiday::query()->count())->toBe($truoc);
});

it('công ty làm 6 ngày một tuần thì chỉ nhảy qua chủ nhật', function (): void {
    config()->set('attendance.work_days_full', '1,2,3,4,5,6');
    config()->set('attendance.work_days_half', '');

    Holiday::query()->delete();
    $this->seed(HolidaySeeder::class);

    // 30/04/2026 là thứ Năm — không đụng tới cuối tuần dù cấu hình nào.
    expect(Holiday::query()->where('date', '2026-04-30')->value('observed_date'))
        ->toBe('2026-04-30');

    // Ngày lễ rơi thứ bảy thì công ty 6 ngày vẫn đi làm, không nghỉ bù.
    foreach (Holiday::query()->get() as $h) {
        expect(CarbonImmutable::parse($h->observed_date)->dayOfWeek)->not->toBe(0);
    }
});

it('ngày làm nửa buổi KHÔNG phải ngày nghỉ, nên không sinh nghỉ bù', function (): void {
    /*
    | Đây là lịch công ty đang dùng từ tháng 9/2026: thứ bảy làm buổi sáng.
    |
    | Lễ rơi vào thứ bảy thì người lao động đã được nghỉ đúng một buổi đáng lẽ
    | phải làm — không sinh nghỉ bù. Coi ngày nửa buổi là ngày nghỉ thì mỗi lễ
    | rơi thứ bảy lại đẻ thêm một ngày nghỉ mà luật không cho.
    */
    config()->set('attendance.work_days_full', '1,2,3,4,5');
    config()->set('attendance.work_days_half', '6');

    Holiday::query()->delete();
    $this->seed(HolidaySeeder::class);

    foreach (Holiday::query()->get() as $h) {
        // Chỉ chủ nhật mới bị đẩy. Thứ bảy giữ nguyên vì vẫn là ngày làm việc.
        expect(CarbonImmutable::parse($h->observed_date)->dayOfWeek)->not->toBe(0);
    }

    // Và ít nhất một ngày lễ phải THẬT SỰ rơi vào thứ bảy mà vẫn đứng yên,
    // nếu không thì test trên xanh mà chẳng kiểm được gì.
    $thuBay = Holiday::query()->get()->filter(
        fn (Holiday $h): bool => CarbonImmutable::parse($h->date)->dayOfWeek === 6,
    );

    expect($thuBay)->not->toBeEmpty();

    foreach ($thuBay as $h) {
        expect($h->observed_date)->toBe($h->date);
    }
});
