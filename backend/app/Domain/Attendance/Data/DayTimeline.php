<?php

declare(strict_types=1);

namespace App\Domain\Attendance\Data;

use App\Support\Time\WorkDate;
use Carbon\CarbonImmutable;

/**
 * Một ngày của một người, vẽ trên trục thời gian.
 *
 * ## Trả lời câu hỏi mà tổng số giờ không trả lời được
 *
 * Làm 5 tiếng liền một mạch và làm 5 tiếng rải rác từ sáng tới tối cho ra cùng
 * một con số. Lưới tháng vì thế im lặng về nhịp làm việc — mà nhịp mới là thứ
 * người quản lý nhìn vào để biết hôm nay có gì bất thường.
 *
 * ## Khoảng trống ở ĐÂU mới là "ngồi không"
 *
 * Chỉ khe **giữa hai phiên** mới tính. Khoảng trước phiên đầu và sau phiên cuối
 * là chưa tới giờ làm và đã nghỉ — gộp vào thì cả buổi tối biến thành thời gian
 * lười biếng, và con số mất sạch ý nghĩa.
 *
 * Nhịp tim cách nhau quá 10 phút thì `RecordHeartbeatAction` đã cắt thành phiên
 * mới, nên mỗi khe ở đây đều **dài hơn 10 phút** — không có khe vụn.
 */
final readonly class DayTimeline
{
    /**
     * @param  list<array{start: string, end: string, minutes: int, interactive: bool}>  $sessions
     * @param  list<array{start: string, end: string, minutes: int, lunch_minutes: int}>  $gaps
     */
    public function __construct(
        public int $userId,
        public array $sessions,
        public array $gaps,
        public int $workedMinutes,
        /**
         * Phần `workedMinutes` có thao tác thật trên Explus.
         *
         * KHÔNG phải một con số để chấm điểm ai. Từ khi chấm công tính theo sự
         * có mặt, lập trình viên làm cả buổi trong IDE sẽ có con số này gần 0
         * mà vẫn làm việc đầy đủ — đúng lý do đổi cách tính. Nó chỉ trả lời
         * "khoảng nào người này đang thao tác trên hệ thống", để dòng thời gian
         * vẽ được hai màu.
         */
        public int $interactiveMinutes,
        public int $idleMinutes,
        /**
         * Phần khoảng lặng rơi vào giờ nghỉ trưa của ca.
         *
         * Tách khỏi `idleMinutes` chứ không gộp: nghỉ trưa là quyền, không phải
         * dấu hiệu lười. Gộp vào thì NGÀY NÀO CŨNG có một khoảng vàng 90 phút
         * cho MỌI NGƯỜI — cờ bật cho tất cả là cờ vô nghĩa.
         */
        public int $lunchMinutes,
        public ?string $firstSeen,
        public ?string $lastSeen,
        /**
         * Mốc bắt đầu phiên đầu tiên, GIỮ NGUYÊN GIỜ UTC.
         *
         * Có vẻ thừa khi đã có `$firstSeen`, nhưng không: `$firstSeen` là
         * "08:15" để hiển thị, còn mọi phép tính đi muộn đều nhận UTC. Truyền
         * nhầm chuỗi giờ Việt Nam vào hàm chờ UTC ra con số lệch bảy tiếng mà
         * vẫn trông hợp lý — đã mắc đúng lỗi đó khi viết màn này.
         */
        public ?string $firstSeenUtc,
    ) {}

    /**
     * Dựng từ các phiên làm việc thô (giờ UTC như database trả về).
     *
     * @param  list<array{started_at: string, ended_at: string, interactive: bool}>  $phienUtc
     * @param  string  $truaTu  Giờ bắt đầu nghỉ trưa, `HH:MM` giờ Việt Nam.
     * @param  string  $truaDen  Giờ kết thúc nghỉ trưa.
     */
    public static function build(
        int $userId,
        array $phienUtc,
        string $truaTu,
        string $truaDen,
    ): self {
        $moc = [];

        foreach ($phienUtc as $p) {
            $tu = self::vietNam($p['started_at']);
            $den = self::vietNam($p['ended_at']);

            $moc[] = ['tu' => $tu, 'den' => $den, 'co' => $p['interactive']];
        }

        // Sắp theo thời điểm bắt đầu: database không hứa thứ tự, mà toàn bộ
        // phép tính khe bên dưới giả định đã sắp xếp.
        usort($moc, static fn (array $a, array $b): int => $a['tu'] <=> $b['tu']);

        $phien = [];
        $khe = [];
        $lamViec = 0;
        $coThaoTac = 0;
        $ngoiKhong = 0;
        $nghiTrua = 0;
        $ketThucTruoc = null;

        $truaBatDau = self::phutTrongNgay($truaTu);
        $truaKetThuc = self::phutTrongNgay($truaDen);

        foreach ($moc as $m) {
            $soPhut = self::phutGiua($m['tu'], $m['den']);

            $phien[] = [
                'start' => self::gio($m['tu']),
                'end' => self::gio($m['den']),
                'minutes' => $soPhut,
                // Có thao tác thật, hay chỉ để tab mở. Cả hai đều tính vào giờ
                // công; khác nhau ở chỗ giao diện vẽ hai màu, để người xem
                // phân biệt được "ngồi làm" với "để đó" mà không cần đoán.
                'interactive' => $m['co'],
            ];

            $lamViec += $soPhut;

            if ($m['co']) {
                $coThaoTac += $soPhut;
            }

            if ($ketThucTruoc !== null && $m['tu'] > $ketThucTruoc) {
                $keDai = self::phutGiua($ketThucTruoc, $m['tu']);

                /*
                | Cắt phần rơi vào giờ nghỉ trưa ra khỏi con số "ngồi không".
                |
                | Khe vẫn được vẽ nguyên vẹn trên giao diện — người xem cần
                | thấy đúng khoảng trống có thật. Chỉ có PHÉP ĐẾM là tách đôi:
                | phần trưa sang `lunchMinutes`, phần còn lại mới là ngồi không.
                */
                $tuPhut = self::phutTrongNgay(self::gio($ketThucTruoc));
                $denPhut = self::phutTrongNgay(self::gio($m['tu']));

                $phanTrua = max(
                    0,
                    min($denPhut, $truaKetThuc) - max($tuPhut, $truaBatDau),
                );

                $khe[] = [
                    'start' => self::gio($ketThucTruoc),
                    'end' => self::gio($m['tu']),
                    'minutes' => $keDai,
                    'lunch_minutes' => $phanTrua,
                ];

                $nghiTrua += $phanTrua;
                $ngoiKhong += max(0, $keDai - $phanTrua);
            }

            // `max` chứ không gán thẳng: hai phiên chồng lấn nhau (dữ liệu cũ,
            // hoặc ghi tay) sẽ sinh ra một khe ÂM nếu lấy mốc kết thúc của
            // phiên sau làm chuẩn.
            $ketThucTruoc = $ketThucTruoc === null
                ? $m['den']
                : max($ketThucTruoc, $m['den']);
        }

        return new self(
            userId: $userId,
            sessions: $phien,
            gaps: $khe,
            workedMinutes: $lamViec,
            interactiveMinutes: $coThaoTac,
            idleMinutes: $ngoiKhong,
            lunchMinutes: $nghiTrua,
            firstSeen: $phien === [] ? null : $phien[0]['start'],
            lastSeen: $phien === [] ? null : $phien[count($phien) - 1]['end'],
            firstSeenUtc: $moc === [] ? null : $moc[0]['tu']->utc()->format('Y-m-d H:i:s'),
        );
    }

    private static function vietNam(string $utc): CarbonImmutable
    {
        return CarbonImmutable::parse($utc, 'UTC')->setTimezone(WorkDate::timezone());
    }

    private static function gio(CarbonImmutable $t): string
    {
        return $t->format('H:i');
    }

    private static function phutGiua(CarbonImmutable $tu, CarbonImmutable $den): int
    {
        return (int) floor($tu->diffInMinutes($den));
    }

    /** `HH:MM` thành số phút tính từ nửa đêm. */
    private static function phutTrongNgay(string $gio): int
    {
        [$h, $p] = array_map(intval(...), explode(':', $gio));

        return $h * 60 + $p;
    }
}
