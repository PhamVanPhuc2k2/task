<?php

declare(strict_types=1);

namespace App\Support\Time;

use Carbon\CarbonImmutable;
use DateTimeInterface;

/**
 * Ngày công — ngày theo lịch Việt Nam, không phải ngày theo UTC.
 *
 * Tách thành lớp riêng vì đây là cái bẫy đắt nhất của chấm công, và nó không
 * tự lộ ra: dữ liệu vẫn lưu được, vẫn đọc ra được, chỉ sai ngày.
 *
 * Hệ thống lưu mọi mốc thời gian ở UTC (xem README, "Quy ước dữ liệu"). Nhân
 * viên làm tới 00:30 sáng giờ Việt Nam thì mốc đó là 17:30 UTC **của ngày hôm
 * trước**:
 *
 *     started_at (UTC)      2026-08-11 17:30
 *     giờ Việt Nam          2026-08-12 00:30
 *     ngày công đúng        2026-08-12   ← cái này
 *     DATE(started_at)      2026-08-11   ← sai, và không ai nhận ra
 *
 * Ranh giới ngày công là **00:00 giờ Việt Nam**. Ca đêm vắt qua nửa đêm sẽ bị
 * cắt làm hai ngày; đó là hành vi có chủ ý và đúng với cách kế toán đếm ngày
 * công. Nếu sau này công ty có ca đêm thật thì đây là chỗ đổi quy ước, một chỗ
 * duy nhất.
 *
 * Bổ sung cho `IncomingDateTime` (mục 1.6): lớp kia chuẩn hoá giờ nhận từ
 * client về UTC, lớp này chuyển giờ UTC thành ngày công để gom nhóm.
 */
final class WorkDate
{
    public static function timezone(): string
    {
        return config()->string('app.display_timezone');
    }

    /** Ngày công chứa mốc thời gian này, dạng `Y-m-d`. */
    public static function from(DateTimeInterface $moment): string
    {
        return CarbonImmutable::instance($moment)
            ->setTimezone(self::timezone())
            ->toDateString();
    }

    /** Nửa đêm đầu ngày công, quy về UTC — dùng làm cận dưới khi truy vấn. */
    public static function startOfDayUtc(string $workDate): CarbonImmutable
    {
        return CarbonImmutable::parse($workDate, self::timezone())
            ->startOfDay()
            ->utc();
    }

    /** Cuối ngày công, quy về UTC — dùng làm cận trên khi truy vấn. */
    public static function endOfDayUtc(string $workDate): CarbonImmutable
    {
        return CarbonImmutable::parse($workDate, self::timezone())
            ->endOfDay()
            ->utc();
    }
}
