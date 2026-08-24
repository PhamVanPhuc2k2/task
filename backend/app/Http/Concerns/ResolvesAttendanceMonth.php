<?php

declare(strict_types=1);

namespace App\Http\Concerns;

use App\Support\Time\WorkDate;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;

/**
 * Đọc tham số `month=YYYY-MM` và dựng khoảng ngày công của tháng đó.
 *
 * Tháng ở đây là **tháng theo lịch Việt Nam**, không phải theo UTC — cùng lý do
 * với `App\Support\Time\WorkDate`. Không có tham số thì lấy tháng hiện tại theo
 * giờ Việt Nam, chứ không phải theo giờ máy chủ: một máy chủ đặt UTC sẽ sang
 * tháng mới trễ bảy tiếng so với người dùng, và ngày 01 hằng tháng sẽ có bảy
 * tiếng bảng công hiện sai tháng.
 */
trait ResolvesAttendanceMonth
{
    /**
     * @return array{string, string, string} [tháng, ngày đầu, ngày cuối]
     */
    protected function resolveMonth(Request $request): array
    {
        $thang = (string) $request->string('month');

        $moc = preg_match('/^\d{4}-\d{2}$/', $thang) === 1
            ? CarbonImmutable::parse($thang.'-01', WorkDate::timezone())
            : CarbonImmutable::instance(Date::now())->setTimezone(WorkDate::timezone());

        return [
            $moc->format('Y-m'),
            $moc->startOfMonth()->toDateString(),
            $moc->endOfMonth()->toDateString(),
        ];
    }

    /**
     * Mọi ngày trong tháng, dạng `Y-m-d`.
     *
     * Trả về đủ ngày kể cả ngày không ai làm: bảng tháng phải có đủ ô, thiếu ô
     * thì các cột lệch nhau và không đọc được theo hàng dọc.
     *
     * @return list<string>
     */
    protected function daysOfMonth(string $tuNgay, string $denNgay): array
    {
        $ngay = CarbonImmutable::parse($tuNgay);
        $cuoi = CarbonImmutable::parse($denNgay);
        $ds = [];

        while ($ngay->lessThanOrEqualTo($cuoi)) {
            $ds[] = $ngay->toDateString();
            $ngay = $ngay->addDay();
        }

        return $ds;
    }
}
