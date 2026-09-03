<?php

declare(strict_types=1);

namespace App\Domain\Attendance\Data;

use App\Support\Time\WorkDate;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Config;

/**
 * Lịch làm việc trong tuần: ngày nào làm cả ngày, ngày nào nửa buổi, ngày nào nghỉ.
 *
 * ## Vì sao lớp này ra đời
 *
 * Trước đây cả công ty chung đúng một ca, áp cho **mọi ngày trong tuần**. Khi
 * công ty chuyển sang làm sáng thứ bảy, mô hình đó hỏng theo một cách rất khó
 * chịu: `lateMinutes()` không hề kiểm hôm đó là thứ mấy, nên người làm đúng
 * lịch được phân vào chiều thứ bảy bị tính **muộn hơn năm tiếng**. Dấu đi muộn
 * thì không tự biến mất — nó chỉ đổi màu khi có đơn được duyệt. Vài tuần như
 * vậy là bảng công đầy dấu đỏ cho những người làm đúng giờ, và không ai còn đọc
 * cột đó nữa.
 *
 * `WorkShift` vẫn là ca của **một ngày**. Lớp này trả lời câu hỏi đứng trước
 * nó: ngày hôm đó có ca không, và là ca nào.
 *
 * ## Ngày nghỉ KHÔNG cắt giờ của ai
 *
 * Đây là ranh giới quan trọng nhất, và nó giữ nguyên từ trước. Người làm chủ
 * nhật vẫn được tính đủ số phút — công ty làm remote với giờ giấc linh hoạt,
 * nên "làm cuối tuần" là chuyện bình thường.
 *
 * Lịch tuần chỉ quyết định ba thứ: hôm đó có tính đi muộn không, có nhắc nộp
 * báo cáo không, và ngày lễ trùng ngày nghỉ thì nghỉ bù vào đâu.
 *
 * ## Ngày nửa buổi không có nghỉ trưa
 *
 * Ca nửa buổi dựng bằng `WorkShift::halfDay()`: vào lúc `morning_start`, tan
 * lúc `half_end`, và ba mốc còn lại đều bằng giờ tan. Nhờ vậy `expectedMinutes()`
 * ra đúng 225 phút mà không cần thêm một nhánh `if` nào trong `WorkShift`.
 */
final readonly class WorkWeek
{
    /**
     * @param  list<int>  $full  Thứ làm cả ngày, theo cách đánh số của Carbon (0 = CN).
     * @param  list<int>  $half  Thứ làm nửa buổi.
     */
    private function __construct(
        public array $full,
        public array $half,
        private WorkShift $caCaNgay,
        private string $gioTanNuaBuoi,
        private int $tranCaNgay,
        private int $tranNuaBuoi,
    ) {}

    public static function fromConfig(): self
    {
        return new self(
            full: self::doc(Config::string('attendance.work_days_full')),
            half: self::doc(Config::string('attendance.work_days_half')),
            caCaNgay: WorkShift::fromConfig(),
            gioTanNuaBuoi: Config::string('attendance.shift.half_end'),
            tranCaNgay: Config::integer('attendance.max_daily_minutes'),
            tranNuaBuoi: Config::integer('attendance.max_daily_minutes_half'),
        );
    }

    /**
     * Ca của một ngày công, hoặc `null` nếu hôm đó là ngày nghỉ.
     *
     * Trả `null` chứ không trả một ca rỗng: chỗ gọi buộc phải nghĩ tới trường
     * hợp ngày nghỉ thay vì vô tình tính đi muộn so với một ca không tồn tại.
     * Đó đúng là lỗi mà lớp này sinh ra để chặn.
     */
    public function shiftFor(string $workDate): ?WorkShift
    {
        $thu = $this->thu($workDate);

        if (in_array($thu, $this->full, true)) {
            return $this->caCaNgay;
        }

        if (in_array($thu, $this->half, true)) {
            return WorkShift::halfDay(
                $this->caCaNgay->morningStart,
                $this->gioTanNuaBuoi,
                $this->caCaNgay->graceMinutes,
            );
        }

        return null;
    }

    public function isWorkingDay(string $workDate): bool
    {
        return $this->shiftFor($workDate) !== null;
    }

    /**
     * Trần giờ công tự động của ngày đó.
     *
     * Ngày nghỉ dùng trần của ngày nửa buổi, không phải trần ngày thường. Lý do
     * thực dụng: ngày nghỉ không có ca nào để suy ra một con số, mà bỏ trần hẳn
     * thì cái tab quên đóng tối thứ bảy chạy suốt chủ nhật ghi thẳng hai mươi
     * bốn tiếng — đúng loại con số làm người ta hết tin bảng công.
     */
    public function maxDailyMinutesFor(string $workDate): int
    {
        return in_array($this->thu($workDate), $this->full, true)
            ? $this->tranCaNgay
            : $this->tranNuaBuoi;
    }

    /**
     * Những thứ trong tuần không có ca nào.
     *
     * Dùng để tính nghỉ bù theo khoản 3 Điều 112: ngày lễ trùng ngày nghỉ hằng
     * tuần thì nghỉ bù vào ngày làm việc kế tiếp.
     *
     * **Ngày nửa buổi KHÔNG phải ngày nghỉ.** Lễ rơi vào thứ bảy làm việc thì
     * không sinh nghỉ bù — người ta đã được nghỉ đúng một buổi đáng lẽ phải làm.
     *
     * @return list<int>
     */
    public function restDays(): array
    {
        return array_values(array_filter(
            range(0, 6),
            fn (int $thu): bool => ! in_array($thu, $this->full, true)
                && ! in_array($thu, $this->half, true),
        ));
    }

    /**
     * Đọc chuỗi `"1,2,3"` thành `[1, 2, 3]`.
     *
     * Bỏ qua giá trị rác thay vì ném lỗi. Cấu hình này giám đốc sửa được trên
     * giao diện, và một ký tự thừa lọt qua validate không nên làm sập cả trang
     * chấm công của công ty — nó chỉ nên làm ngày đó thành ngày nghỉ. Lớp
     * validate ở `UpdateSettingsRequest` mới là chỗ chặn.
     *
     * @return list<int>
     */
    private static function doc(string $tho): array
    {
        $ra = [];

        foreach (explode(',', $tho) as $phan) {
            $phan = trim($phan);

            if ($phan === '' || preg_match('/^[0-6]$/', $phan) !== 1) {
                continue;
            }

            $thu = (int) $phan;

            if (! in_array($thu, $ra, true)) {
                $ra[] = $thu;
            }
        }

        return $ra;
    }

    /**
     * Thứ trong tuần của một ngày công, theo cách đánh số của Carbon (0 = CN).
     *
     * Dựng ngày ở múi giờ Việt Nam. `WorkDate` vốn đã là ngày theo giờ Việt
     * Nam, nhưng phân tích nó ở UTC rồi lấy `dayOfWeek` thì với ngày sinh ra từ
     * một mốc gần nửa đêm sẽ ra lệch một ngày — đúng loại lỗi chỉ nổ vào cuối
     * tuần và không ai dựng lại được.
     */
    private function thu(string $workDate): int
    {
        return CarbonImmutable::parse($workDate, WorkDate::timezone())->dayOfWeek;
    }
}
