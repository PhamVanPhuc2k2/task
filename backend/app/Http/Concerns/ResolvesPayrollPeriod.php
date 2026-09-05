<?php

declare(strict_types=1);

namespace App\Http\Concerns;

use App\Support\Time\WorkDate;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;

/**
 * Kỳ lương mà màn hình đang hỏi, dạng `YYYY-MM`.
 *
 * ## Mặc định là kỳ VỪA KẾT THÚC, không phải kỳ đang chạy
 *
 * Khác với màn bảng công, nơi mặc định là tháng hiện tại. Người mở màn phiếu
 * lương gần như luôn hỏi về tháng vừa qua — tháng đang chạy thì phiếu chưa có
 * nghĩa gì, vì còn hai mươi ngày công chưa xảy ra.
 *
 * Mở ra thấy một phiếu gần như trống rỗng là cách nhanh nhất khiến người dùng
 * tưởng hệ thống hỏng.
 *
 * ## Mốc lấy theo GIỜ VIỆT NAM
 *
 * `Date::now()` ở UTC vẫn đang ở tháng trước trong bảy tiếng đầu ngày mùng một
 * giờ Việt Nam — cùng cái bẫy đã ghi ở `WorkDate` và `ClosePeriodAction`.
 *
 * `subMonthNoOverflow()` chứ không `subMonth()`: ngày 31/03 lùi một tháng bằng
 * `subMonth()` ra 03/03 vì tháng hai không có ngày 31, và kỳ lương của tháng ba
 * sẽ được đọc thành tháng ba.
 */
trait ResolvesPayrollPeriod
{
    protected function kyLuong(Request $request): string
    {
        $request->validate(
            ['period' => ['sometimes', 'string', 'regex:/^\d{4}-\d{2}$/']],
            ['period.regex' => 'Kỳ lương phải có dạng YYYY-MM, ví dụ 2026-09.'],
        );

        if ($request->filled('period')) {
            return (string) $request->string('period');
        }

        return CarbonImmutable::parse(WorkDate::from(Date::now()))
            ->subMonthNoOverflow()
            ->format('Y-m');
    }
}
