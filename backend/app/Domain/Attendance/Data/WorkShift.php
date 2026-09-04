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
 * ## Đây là ca của MỘT NGÀY, không phải của cả tuần
 *
 * Từ khi công ty làm sáng thứ bảy, mỗi ngày có thể có ca khác nhau. Câu hỏi
 * "ngày này có ca không, và là ca nào" thuộc về `WorkWeek`; lớp này chỉ giữ
 * bốn mốc giờ của đúng một ngày.
 *
 * `fromConfig()` trả về ca của **ngày làm cả ngày**. Đừng dùng nó để tính đi
 * muộn cho một ngày cụ thể — đi qua `WorkWeek::shiftFor()`, vì chỉ nó biết hôm
 * đó có phải ngày nghỉ hay không. Dùng thẳng `fromConfig()` là đúng ở những chỗ
 * chỉ cần mốc giờ vào làm, ví dụ đơn xin đi muộn: giờ vào làm giống nhau ở mọi
 * ngày có ca.
 *
 * ## Vì sao là config chứ chưa phải bảng
 *
 * Cả công ty vẫn đang chung một lịch tuần. Dựng sẵn bảng `work_shifts` với ca
 * theo phòng, theo người, có hiệu lực từ ngày nào — trong khi chỉ có đúng một
 * lịch — là dựng một cỗ máy để giải bài toán chưa tồn tại. Khi nào có phòng
 * thật sự làm giờ khác thì đổi, và `WorkWeek::fromConfig()` là chỗ duy nhất
 * phải sửa.
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
        /**
         * Số phút được châm chước trước khi bị tính là về sớm.
         *
         * Tách khỏi `graceMinutes` vì hai chiều không đối xứng: công ty chốt ân
         * hạn đi muộn bằng 0 nhưng về sớm bằng 5. Về sớm năm phút mà bắt làm
         * đơn thì không ai dùng, và một tính năng không ai dùng còn tệ hơn
         * không có — nó làm bảng công trông như đã theo dõi trong khi thực tế
         * thì không.
         */
        public int $earlyGraceMinutes,
    ) {}

    public static function fromConfig(): self
    {
        return new self(
            morningStart: Config::string('attendance.shift.morning_start'),
            lunchStart: Config::string('attendance.shift.lunch_start'),
            lunchEnd: Config::string('attendance.shift.lunch_end'),
            end: Config::string('attendance.shift.end'),
            graceMinutes: Config::integer('attendance.shift.grace_minutes'),
            earlyGraceMinutes: Config::integer('leave.early_leave_grace_minutes'),
        );
    }

    /**
     * Ca của một ngày nửa buổi: vào lúc `morning_start`, tan lúc `half_end`.
     *
     * Ba mốc còn lại đều đặt bằng giờ tan, và đó không phải mẹo vặt: ngày nửa
     * buổi tan TRƯỚC giờ nghỉ trưa nên không có nghỉ trưa nào để khai. Nhờ cách
     * đặt này, `expectedMinutes()` ra đúng số phút buổi sáng — sáng cộng chiều,
     * mà chiều bằng không — nên `WorkShift` không cần thêm một nhánh `if` nào,
     * và mọi chỗ đang dùng nó không phải biết ngày nửa buổi tồn tại.
     */
    public static function halfDay(
        string $morningStart,
        string $end,
        int $graceMinutes,
        int $earlyGraceMinutes,
    ): self {
        return new self(
            morningStart: $morningStart,
            lunchStart: $end,
            lunchEnd: $end,
            end: $end,
            graceMinutes: $graceMinutes,
            earlyGraceMinutes: $earlyGraceMinutes,
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
     * Rời sớm bao nhiêu phút so với giờ tan ca.
     *
     * ## Vì sao đối xứng với `lateMinutes()` nhưng không giống hệt
     *
     * Đi muộn đọc `first_seen_at` — có ngay từ nhịp tim đầu tiên. Về sớm đọc
     * `last_seen_at`, mà mốc đó **chỉ đứng yên khi ngày đã kết thúc**: phiên
     * làm việc còn mở thì nó vẫn đang lớn dần. Nên con số này chỉ có nghĩa khi
     * nhìn lại một ngày đã qua, không phải khi theo dõi ngày hôm nay.
     *
     * Không có phiên nào là VẮNG MẶT, không phải về sớm — gộp hai thứ lại thì
     * người nghỉ phép hiện thành "về sớm 9 tiếng".
     *
     * Ngày nửa buổi tự đúng mà không cần nhánh riêng: `end` của ca thứ bảy là
     * 12:00, nên về lúc 11:30 ra 30 phút chứ không phải so với 17:30.
     *
     * @param  string|null  $lastSeenAtUtc  Phiên cuối cùng trong ngày, **giờ
     *                                      UTC** như MySQL trả về.
     */
    public function earlyLeaveMinutes(?string $lastSeenAtUtc): int
    {
        if ($lastSeenAtUtc === null) {
            return 0;
        }

        $roi = CarbonImmutable::parse($lastSeenAtUtc, 'UTC')
            ->setTimezone(WorkDate::timezone());

        $tanCa = $roi->setTimeFromTimeString($this->end);

        // Ân hạn dời NGƯỠNG, không trừ vào số phút: quá 5 phút châm chước thì
        // về sớm tính từ giờ tan ca thật. Cùng quy ước với ân hạn đi muộn.
        if ($roi->greaterThanOrEqualTo($tanCa->subMinutes($this->earlyGraceMinutes))) {
            return 0;
        }

        // Làm quá giờ tan không phải "về sớm âm phút".
        return max(0, (int) floor($roi->diffInMinutes($tanCa)));
    }

    /**
     * Người này có ở lại tới mốc giờ đã hẹn không.
     *
     * Đối xứng với `arrivedBy()`: đơn xin về sớm chỉ bao **từ đúng giờ đã xin**
     * trở đi. Xin về lúc 16h mà 14h đã tắt máy thì phần sớm hơn vẫn là về sớm —
     * bỏ luật này thì một đơn duy nhất biến thành giấy thông hành cho cả buổi
     * chiều, và cả cơ chế duyệt mất ý nghĩa.
     *
     * @param  string|null  $lastSeenAtUtc  Phiên cuối trong ngày, giờ UTC.
     * @param  string  $gioHen  Giờ đã hẹn, giờ Việt Nam (`HH:MM` hoặc `HH:MM:SS`).
     */
    public function stayedUntil(?string $lastSeenAtUtc, string $gioHen): bool
    {
        // Không có phiên nào nghĩa là không đến, chứ không phải "ở lại đủ".
        if ($lastSeenAtUtc === null) {
            return false;
        }

        $roi = CarbonImmutable::parse($lastSeenAtUtc, 'UTC')
            ->setTimezone(WorkDate::timezone());

        return $roi->greaterThanOrEqualTo($roi->setTimeFromTimeString($gioHen));
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

    /**
     * Số phút SỚM của một mốc giờ Việt Nam so với giờ tan ca.
     *
     * Đối xứng với `lateMinutesFromLocalTime()`, và cùng lý do tồn tại: đây là
     * giờ người ta tự khai trên đơn ("về lúc 16h"), chưa hề có phiên làm việc
     * nào để quy đổi từ UTC. Phép trừ hai mốc trên đồng hồ, không phải phép so
     * hai thời điểm.
     *
     * @param  string  $gioVietNam  `HH:MM` hoặc `HH:MM:SS`.
     */
    public function earlyMinutesFromLocalTime(string $gioVietNam): int
    {
        // Không trả số âm: ở lại quá giờ tan không phải "về sớm -20 phút".
        return max(0, $this->phut($this->end) - $this->phut($gioVietNam));
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
