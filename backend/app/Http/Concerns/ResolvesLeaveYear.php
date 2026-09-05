<?php

declare(strict_types=1);

namespace App\Http\Concerns;

use App\Support\Time\WorkDate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;

/**
 * Năm mà màn quỹ phép đang hỏi.
 *
 * ## Mặc định là năm hiện tại theo GIỜ VIỆT NAM
 *
 * `Date::now()->year` ở UTC trả về năm cũ trong bảy tiếng đầu ngày 01/01 giờ
 * Việt Nam — tức là sáng mùng một Tết dương lịch, cả công ty mở màn quỹ phép ra
 * và thấy số của năm ngoái, không ai hiểu vì sao. Cùng cái bẫy đã ghi ở
 * `WorkDate` và `ClosePeriodAction`.
 *
 * ## Kẹp trong ±5 năm
 *
 * Quỹ phép của năm 1999 là câu hỏi vô nghĩa, và không kẹp thì `?year=999999`
 * sinh ra những vòng lặp ngày tháng rất chậm mà chẳng ai cần. Kẹp chứ không ném
 * lỗi: đây là tham số điều hướng, và một năm ngoài tầm thì đưa về mốc gần nhất
 * vẫn hiển thị được cái gì đó có nghĩa.
 */
trait ResolvesLeaveYear
{
    protected function namQuyPhep(Request $request): int
    {
        $hienTai = self::namHienTai();

        if (! $request->filled('year')) {
            return $hienTai;
        }

        return max($hienTai - 5, min($hienTai + 5, $request->integer('year')));
    }

    protected static function namHienTai(): int
    {
        return (int) substr(WorkDate::from(Date::now()), 0, 4);
    }
}
