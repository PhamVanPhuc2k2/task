<?php

declare(strict_types=1);

namespace App\Domain\Attendance\Data;

use App\Domain\Attendance\Enums\AttendanceDecision;

/**
 * Một ngày công của một người: hệ thống đo được gì, con người quyết định gì.
 *
 * Hai con số nằm cạnh nhau chứ không đè lên nhau. `measuredMinutes` là thứ hệ
 * thống đo, `adjustedMinutes` là thứ người quản lý ấn định. Ghi đè cái đầu bằng
 * cái sau là mất luôn khả năng trả lời "hệ thống đo được bao nhiêu, và ai đã
 * sửa nó thành con số này".
 */
final readonly class DailyAttendance
{
    public function __construct(
        public int $userId,
        public string $workDate,
        public int $measuredMinutes,
        public int $sessionCount,
        public ?string $firstSeenAt,
        public ?string $lastSeenAt,
        public ?AttendanceDecision $decision = null,
        public ?int $adjustedMinutes = null,
        public ?string $reason = null,
    ) {}

    /** Số phút dùng để hiển thị và tổng hợp: người quyết định thắng máy đo. */
    public function effectiveMinutes(): int
    {
        return $this->adjustedMinutes ?? $this->measuredMinutes;
    }
}
