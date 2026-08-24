<?php

declare(strict_types=1);

namespace App\Support\Enums;

/**
 * Kết quả đối chiếu **giờ công đo được** với **báo cáo ngày** của một người
 * trong một ngày.
 *
 * Đây là mảnh còn thiếu của đợt 3. Cột `has_report` đã có sẵn từ trước, nhưng
 * một ô đúng/sai trên lưới ba mươi ngày × ba mươi người là hai mươi bảy nghìn
 * thứ để mắt tự lọc — tức là không ai lọc. Enum này biến nó thành **bốn tình
 * huống có tên**, và chỉ một trong bốn là thứ cần người quản lý nhìn.
 *
 * Nằm ở `Support` vì nó bắc qua hai miền: Attendance đo giờ, Report giữ báo
 * cáo, và hai miền đó **không được gọi nhau** (xem README, "Quy tắc phụ
 * thuộc"). Lớp này chỉ nhận số nguyên và boolean nên không phụ thuộc miền nào
 * — cũng nhờ vậy mà kiểm thử được thẳng, không cần database.
 *
 * **Cố ý không có case nào mang nghĩa "vi phạm".** Chính sách của dự án là
 * *nhìn cho biết, duyệt tuỳ hoàn cảnh* — hệ thống gắn cờ, con người kết luận.
 */
enum ReportMatch: string
{
    /** Có giờ làm và có báo cáo — không cần ai nhìn tới. */
    case Ok = 'ok';

    /**
     * Có giờ làm đáng kể nhưng **không có báo cáo**.
     *
     * Tình huống duy nhất cần người quản lý để mắt. Thường chỉ là quên nộp.
     */
    case MissingReport = 'missing_report';

    /**
     * Có báo cáo nhưng hệ thống gần như **không đo được giờ nào**.
     *
     * Không phải dấu hiệu gian dối: họp cả ngày, đi gặp khách, làm trên Word
     * hay điện thoại đều rơi vào đây. Hiện ra để người quản lý biết con số giờ
     * của ngày đó không phản ánh đủ, chứ không phải để chất vấn.
     */
    case ReportOnly = 'report_only';

    /** Không giờ, không báo cáo — cuối tuần, nghỉ phép, hoặc đơn giản là ngày trống. */
    case Idle = 'idle';

    /** Ngày lễ theo bảng `holidays` — không đối chiếu, không kỳ vọng gì. */
    case Holiday = 'holiday';

    /**
     * Ngày nghỉ có đơn ĐÃ DUYỆT.
     *
     * Đây là mảnh gỡ được việc bấm tay hằng ngày của trưởng phòng. Trước khi có
     * nó, ngày nghỉ phép để lại một ô trống không rõ nguyên nhân, và cách duy
     * nhất để dọn là quản lý bấm "Bỏ qua" kèm lý do tối thiểu 5 ký tự — cho
     * MỖI ngày nghỉ của MỖI người. Vài chục lần mỗi tháng, để ghi lại một
     * thông tin mà chính nhân viên đã khai từ đầu.
     */
    case OnLeave = 'on_leave';

    /**
     * @param  int  $minWorkedMinutes  Mốc coi là "có làm". Truyền vào chứ không tự
     *                                 đọc `config()`: một enum tự móc vào container
     *                                 là một enum không kiểm thử được nếu chưa dựng
     *                                 cả ứng dụng, và chỗ gọi cũng hết đường ghi đè.
     */
    public static function for(
        int $minutes,
        bool $hasReport,
        int $minWorkedMinutes,
        bool $isHoliday = false,
        bool $onLeave = false,
    ): self {
        /*
        | Nghỉ phép đứng TRƯỚC ngày lễ trong thứ tự ưu tiên.
        |
        | Hai thứ có thể trùng nhau (xin nghỉ cả tuần có Tết ở giữa), và khi đó
        | thông tin đáng hiện là "người này đang nghỉ phép" — nó giải thích cả
        | những ngày còn lại của đơn. Hiện "ngày lễ" thì ô đó tách khỏi chuỗi
        | ngày nghỉ và người xem phải tự ghép lại.
        */
        if ($onLeave) {
            return self::OnLeave;
        }

        if ($isHoliday) {
            return self::Holiday;
        }

        $coLam = $minutes >= $minWorkedMinutes;

        return match (true) {
            $coLam && $hasReport => self::Ok,
            $coLam => self::MissingReport,
            $hasReport => self::ReportOnly,
            default => self::Idle,
        };
    }

    /**
     * Có phải thứ đáng để người quản lý nhìn không.
     *
     * Chỉ `MissingReport`. `ReportOnly` cố ý **không** tính: đếm nó vào đây là
     * biến "hôm nay tôi họp cả ngày" thành một con số đỏ trên bảng, và người ta
     * sẽ mở sẵn ứng dụng cho đủ giờ — đúng cái thói quen mà cả tính năng chấm
     * công này sinh ra để bỏ.
     */
    public function needsAttention(): bool
    {
        return $this === self::MissingReport;
    }

    public function label(): string
    {
        return match ($this) {
            self::Ok => 'Có giờ làm và đã báo cáo',
            self::MissingReport => 'Có giờ làm nhưng chưa báo cáo',
            self::ReportOnly => 'Đã báo cáo, hệ thống ghi được rất ít giờ',
            self::Idle => 'Không có hoạt động',
            self::Holiday => 'Ngày lễ',
            self::OnLeave => 'Nghỉ phép đã duyệt',
        };
    }
}
