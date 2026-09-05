<?php

declare(strict_types=1);

namespace App\Domain\Attendance\Support;

use App\Domain\Attendance\Data\WorkWeek;
use App\Domain\Attendance\Models\Holiday;
use App\Support\Contracts\WorkingDays;
use Carbon\CarbonImmutable;

/**
 * Đếm ngày công theo lịch tuần và bảng ngày lễ.
 *
 * Bản cài đặt của `WorkingDays` — xem giao diện đó để biết vì sao phép đếm này
 * không nằm thẳng trong miền Leave.
 *
 * ## Nhớ trong bộ nhớ theo NĂM
 *
 * Một người có mười đơn nghỉ trong năm thì màn quỹ phép gọi `countBetween()`
 * mười lần. Tra bảng `holidays` mỗi lần là mười câu SQL cho cùng một danh sách
 * mười hai dòng — đúng dạng N+1 mà không màn hình nào lộ ra, vì mỗi câu đều
 * nhanh.
 *
 * Bộ nhớ đệm sống theo vòng đời của đối tượng, và đối tượng được ghép làm
 * singleton cho mỗi request. Không dùng cache ngoài: ngày lễ đổi thì trang tiếp
 * theo phải thấy ngay, chứ không phải sau khi hết hạn cache.
 */
final class CalendarWorkingDays implements WorkingDays
{
    private ?WorkWeek $tuan = null;

    /** @var array<int, array<string, true>> năm => tập ngày thực nghỉ */
    private array $ngayLe = [];

    public function countBetween(string $tuNgay, string $denNgay): float
    {
        if ($tuNgay > $denNgay) {
            return 0.0;
        }

        $tuan = $this->tuan ??= WorkWeek::fromConfig();

        $tong = 0.0;
        $ngay = CarbonImmutable::parse($tuNgay);
        $het = CarbonImmutable::parse($denNgay);

        while ($ngay->lessThanOrEqualTo($het)) {
            $chuoi = $ngay->toDateString();

            $tong += $this->laLe($chuoi) ? 0.0 : $this->giaTri($tuan, $chuoi);

            $ngay = $ngay->addDay();
        }

        return $tong;
    }

    /**
     * Giá trị một ngày: 1 nếu làm cả ngày, 0,5 nếu nửa buổi, 0 nếu nghỉ.
     *
     * Đọc `shiftFor()` chứ không đọc thẳng danh sách thứ: lớp `WorkWeek` là nơi
     * duy nhất biết "thứ bảy là nửa buổi", và hỏi lại danh sách ở đây là mở
     * đường cho hai chỗ hiểu lịch tuần khác nhau.
     */
    private function giaTri(WorkWeek $tuan, string $ngay): float
    {
        $ca = $tuan->shiftFor($ngay);

        if ($ca === null) {
            return 0.0;
        }

        return in_array($this->thu($ngay), $tuan->half, true) ? 0.5 : 1.0;
    }

    private function thu(string $ngay): int
    {
        return (int) CarbonImmutable::parse($ngay)->dayOfWeek;
    }

    private function laLe(string $ngay): bool
    {
        $nam = (int) substr($ngay, 0, 4);

        if (! isset($this->ngayLe[$nam])) {
            /** @var array<string, true> $tap */
            $tap = [];

            $ds = Holiday::query()
                ->whereBetween('observed_date', [
                    sprintf('%04d-01-01', $nam),
                    sprintf('%04d-12-31', $nam),
                ])
                ->pluck('observed_date');

            foreach ($ds as $d) {
                $tap[(string) $d] = true;
            }

            $this->ngayLe[$nam] = $tap;
        }

        return isset($this->ngayLe[$nam][$ngay]);
    }
}
