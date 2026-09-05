<?php

declare(strict_types=1);

namespace App\Domain\Attendance\Data;

use App\Support\Enums\DayKind;
use Illuminate\Support\Facades\Config;

/**
 * Chính sách làm thêm giờ: trả bao nhiêu phần trăm, và được làm tối đa bao lâu.
 *
 * Mặc định bám **mức sàn** Bộ luật Lao động 2019:
 *
 *   - **Điều 98**: ngày thường ít nhất 150%, ngày nghỉ hằng tuần 200%, ngày
 *     nghỉ lễ tết 300%.
 *   - **Điều 107**: không quá 50% số giờ làm việc bình thường trong 1 ngày,
 *     không quá 40 giờ trong 1 tháng, không quá 200 giờ trong 1 năm.
 *
 * ## Hệ số là PHẦN TRĂM NGUYÊN
 *
 * Người ta nói *"hệ số 150%"* chứ không nói *"1,5"*, ô nhập ở màn Cài đặt nhận
 * số nguyên, và cả module này không còn phép nhân số thực nào. Quy về tiền là
 * việc của bảng lương ở chặng sau.
 *
 * ## Không bao giờ trả dưới mức sàn, kể cả khi cấu hình sai
 *
 * `percentFor()` kẹp lên mức luật định. Trả dưới mức sàn là trái luật, và một
 * con số gõ nhầm ở màn Cài đặt không nên biến thành một sai phạm im lặng kéo
 * dài tới khi có người khiếu nại.
 *
 * Chiều ngược lại thì không kẹp: công ty trả cao hơn luật là quyền của họ.
 */
final readonly class OvertimePolicy
{
    /** Mức sàn Điều 98 — không cấu hình nào hạ được xuống dưới. */
    private const int SAN_NGAY_THUONG = 150;

    private const int SAN_NGAY_NGHI = 200;

    private const int SAN_NGAY_LE = 300;

    private function __construct(
        public int $workingPercent,
        public int $weeklyRestPercent,
        public int $holidayPercent,
        /** 0 = tắt trần. */
        public int $maxMinutesPerDay,
        public int $maxMinutesPerMonth,
        public int $maxMinutesPerYear,
    ) {}

    public static function fromConfig(): self
    {
        return new self(
            workingPercent: max(
                self::SAN_NGAY_THUONG,
                Config::integer('attendance.overtime.rate_working_percent'),
            ),
            weeklyRestPercent: max(
                self::SAN_NGAY_NGHI,
                Config::integer('attendance.overtime.rate_weekly_rest_percent'),
            ),
            holidayPercent: max(
                self::SAN_NGAY_LE,
                Config::integer('attendance.overtime.rate_holiday_percent'),
            ),
            maxMinutesPerDay: Config::integer('attendance.overtime.max_minutes_per_day'),
            maxMinutesPerMonth: Config::integer('attendance.overtime.max_minutes_per_month'),
            maxMinutesPerYear: Config::integer('attendance.overtime.max_minutes_per_year'),
        );
    }

    /** Hệ số của một ngày, tính bằng phần trăm. */
    public function percentFor(DayKind $loai): int
    {
        return match ($loai) {
            DayKind::Working => $this->workingPercent,
            DayKind::WeeklyRest => $this->weeklyRestPercent,
            DayKind::Holiday => $this->holidayPercent,
        };
    }
}
