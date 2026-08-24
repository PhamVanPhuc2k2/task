<?php

declare(strict_types=1);

namespace App\Domain\Attendance\Data;

use App\Support\Time\WorkDate;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Config;

/**
 * Ca làm chuẩn của công ty: 8h15–12h, 13h30–17h30.
 *
 * ## Đây là một thay đổi về chính sách, không chỉ là thêm tính năng
 *
 * Trước khi có lớp này, chấm công **cố ý** không có khái niệm giờ vào làm. Nó
 * chỉ đo tổng số phút trong ngày, và công ty làm việc từ xa với giờ giấc linh
 * hoạt nên "đi muộn" không có nghĩa gì. Chú thích trong `config/attendance.php`
 * từng nói đúng như vậy.
 *
 * Công ty đã chốt giờ cố định, nên mốc đó xuất hiện. Nhưng cái cũ **không mất
 * đi**: tổng số phút vẫn là thứ chính để đối chiếu với báo cáo ngày. Giờ vào
 * làm là một cột thông tin thêm bên cạnh, không phải thứ thay thế.
 *
 * ## Vì sao là config chứ chưa phải bảng
 *
 * Cả công ty đang chung một ca. Dựng sẵn bảng `work_shifts` với ca theo phòng,
 * theo người, có hiệu lực từ ngày nào — trong khi chỉ có đúng một ca — là dựng
 * một cỗ máy để giải bài toán chưa tồn tại. Khi nào có phòng thật sự làm giờ
 * khác thì đổi, và `fromConfig()` là chỗ duy nhất phải sửa.
 */
final readonly class WorkShift
{
    public function __construct(
        /** Giờ vào làm buổi sáng, dạng `HH:MM` theo giờ Việt Nam. */
        public string $morningStart,
        public string $lunchStart,
        public string $lunchEnd,
        public string $end,
        /**
         * Số phút được châm chước trước khi bị tính là muộn.
         *
         * Mặc định 0 — đúng như công ty đã chốt. Nhưng nó là con số cấu hình
         * được vì đây là loại quyết định hay đổi, và đổi nó không nên phải sửa
         * mã rồi đi kiểm lại toàn bộ.
         */
        public int $graceMinutes,
    ) {}

    public static function fromConfig(): self
    {
        return new self(
            morningStart: Config::string('attendance.shift.morning_start'),
            lunchStart: Config::string('attendance.shift.lunch_start'),
            lunchEnd: Config::string('attendance.shift.lunch_end'),
            end: Config::string('attendance.shift.end'),
            graceMinutes: Config::integer('attendance.shift.grace_minutes'),
        );
    }

    /**
     * Muộn bao nhiêu phút so với giờ vào làm.
     *
     * @param  string|null  $firstSeenAtUtc  Phiên làm việc đầu tiên trong ngày,
     *                                       **giờ UTC** như MySQL trả về.
     *                                       `null` = không có phiên nào.
     */
    public function lateMinutes(?string $firstSeenAtUtc): int
    {
        // Không có phiên nào là VẮNG MẶT, không phải đi muộn. Gộp hai thứ lại
        // thì người nghỉ phép sẽ hiện thành "muộn 9 tiếng".
        if ($firstSeenAtUtc === null) {
            return 0;
        }

        /*
        | Quy về giờ Việt Nam trước khi so. Giờ trong config là giờ người ta
        | nhìn đồng hồ, còn `first_seen_at` là UTC — chênh đúng 7 tiếng. So
        | thẳng hai thứ đó ra một con số trông vẫn hợp lý, nên sai kiểu này rất
        | khó bị phát hiện bằng mắt.
        */
        $den = CarbonImmutable::parse($firstSeenAtUtc, 'UTC')
            ->setTimezone(WorkDate::timezone());

        $batDau = $den->setTimeFromTimeString($this->morningStart);

        // Ân hạn dời NGƯỠNG, không trừ vào số phút muộn: quá 5 phút châm chước
        // thì muộn tính từ giờ vào làm thật, không phải từ lúc hết châm chước.
        if ($den->lessThanOrEqualTo($batDau->addMinutes($this->graceMinutes))) {
            return 0;
        }

        return (int) floor($batDau->diffInMinutes($den));
    }

    /**
     * Người này có đến kịp mốc giờ đã hẹn không.
     *
     * Dùng cho đơn xin đi muộn đã được duyệt: đơn chỉ bao **tới đúng giờ đã
     * xin**, không bao cả ngày. Xin đến 9h mà 11h mới tới thì phần vượt quá
     * vẫn là đi muộn — bỏ luật này thì một đơn duy nhất biến thành giấy thông
     * hành cho cả ngày, và cả cơ chế duyệt mất ý nghĩa.
     *
     * @param  string|null  $firstSeenAtUtc  Phiên đầu tiên trong ngày, giờ UTC.
     * @param  string  $gioHen  Giờ đã hẹn, giờ Việt Nam (`HH:MM` hoặc `HH:MM:SS`).
     */
    public function arrivedBy(?string $firstSeenAtUtc, string $gioHen): bool
    {
        // Không có phiên nào nghĩa là không đến. Không thể "đến kịp" một mốc
        // giờ mà mình không hề xuất hiện.
        if ($firstSeenAtUtc === null) {
            return false;
        }

        $den = CarbonImmutable::parse($firstSeenAtUtc, 'UTC')
            ->setTimezone(WorkDate::timezone());

        return $den->lessThanOrEqualTo($den->setTimeFromTimeString($gioHen));
    }

    /**
     * Số phút muộn của một mốc giờ Việt Nam so với giờ vào ca.
     *
     * Khác `lateMinutes()` ở chỗ **không có ngày và không có múi giờ**: đây là
     * giờ người ta tự khai trên đơn xin đi muộn ("9h30"), chưa hề có phiên làm
     * việc nào để mà quy đổi từ UTC. Phép trừ hai mốc trên đồng hồ, không phải
     * phép so hai thời điểm.
     *
     * @param  string  $gioVietNam  `HH:MM` hoặc `HH:MM:SS`.
     */
    public function lateMinutesFromLocalTime(string $gioVietNam): int
    {
        // Không trả số âm: "muộn -20 phút" là vô nghĩa, và một số âm lọt vào
        // câu thông báo gửi cho quản lý sẽ đọc rất kỳ.
        return max(0, $this->phut($gioVietNam) - $this->phut($this->morningStart));
    }

    /** Số phút của một ngày làm đủ: sáng + chiều, không tính nghỉ trưa. */
    public function expectedMinutes(): int
    {
        return ($this->phut($this->lunchStart) - $this->phut($this->morningStart))
            + ($this->phut($this->end) - $this->phut($this->lunchEnd));
    }

    /** `HH:MM` thành số phút tính từ nửa đêm. */
    private function phut(string $gio): int
    {
        [$h, $p] = array_map(intval(...), explode(':', $gio));

        return $h * 60 + $p;
    }
}
