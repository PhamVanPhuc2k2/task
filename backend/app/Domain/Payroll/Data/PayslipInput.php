<?php

declare(strict_types=1);

namespace App\Domain\Payroll\Data;

/**
 * Đầu vào để dựng một phiếu lương — **toàn số liệu thô, không có model nào**.
 *
 * ## Vì sao là một DTO chứ không phải để Action tự đi lấy
 *
 * Phiếu lương cần giờ công (miền Attendance), đơn nghỉ (miền Leave), làm thêm
 * giờ (Attendance) và mức lương (Payroll). Luật tầng cấm Payroll gọi sang ba
 * miền kia.
 *
 * Nên tầng Http đi gom — nó là một trong hai tầng được phép biết nhiều miền —
 * rồi đưa vào đây những con số đã cộng xong. `BuildPayslipAction` chỉ còn là
 * một phép tính thuần: cùng đầu vào thì luôn ra cùng kết quả, kiểm được bằng
 * test không cần database, và đó đúng là thứ nên như vậy với mã tính tiền.
 *
 * ## Số phút, không phải số giờ
 *
 * Mọi đại lượng thời gian ở đây là PHÚT. Đổi sang giờ ở giữa chừng là làm tròn
 * sớm, và làm tròn sớm trên tiền là cách chắc chắn nhất để hai người cộng ra
 * hai con số.
 */
final readonly class PayslipInput
{
    /**
     * @param  string  $period  Kỳ công, dạng `YYYY-MM`.
     * @param  int  $standardMinutes  Tổng số phút ca của mọi ngày làm việc trong
     *                                kỳ, theo lịch thực tế. Đây là MẪU SỐ của
     *                                lương giờ.
     * @param  int  $paidLeaveMinutes  Số phút ca rơi vào ngày nghỉ CÓ hưởng
     *                                 lương đã duyệt. Không bị trừ; có mặt ở
     *                                 đây chỉ để phiếu lương giải thích được vì
     *                                 sao số giờ phải làm ít đi.
     * @param  int  $unpaidLeaveMinutes  Số phút ca rơi vào ngày nghỉ KHÔNG hưởng
     *                                   lương đã duyệt. Bị trừ nguyên.
     * @param  int  $workedMinutes  Giờ công thực tế, đã áp quyết định của quản
     *                              lý trên từng ngày.
     * @param  int  $shortfallMinutes  Số phút thiếu, đã cộng dồn THEO TỪNG NGÀY
     *                                 và đã áp ân hạn từng ngày. Không tính lại
     *                                 được từ các con số trên — ân hạn theo ngày
     *                                 nên hiệu của hai tổng ra một số khác.
     * @param  list<array{minutes: int, percent: int}>  $overtime  Làm thêm giờ ĐÃ
     *                                                             DUYỆT, gom theo
     *                                                             hệ số.
     * @param  numeric-string  $baseSalary  Lương tháng đang hiệu lực trong kỳ.
     * @param  numeric-string  $allowance  Phụ cấp. KHÔNG chia theo tỷ lệ giờ
     *                                     công — phụ cấp là khoản cố định hằng
     *                                     tháng; công ty muốn khác thì đây là
     *                                     chỗ đổi.
     */
    public function __construct(
        public string $period,
        public int $standardMinutes,
        public int $paidLeaveMinutes,
        public int $unpaidLeaveMinutes,
        public int $workedMinutes,
        public int $shortfallMinutes,
        public array $overtime,
        public string $baseSalary,
        public string $allowance,
    ) {}

    /**
     * Số phút người này thật sự phải có mặt trong kỳ.
     *
     * Ngày nghỉ đã duyệt — dù có lương hay không — đều không đòi hỏi sự có mặt.
     * Chỉ khác nhau ở chỗ ngày không lương thì bị trừ tiền.
     */
    public function requiredMinutes(): int
    {
        return max(0, $this->standardMinutes - $this->paidLeaveMinutes - $this->unpaidLeaveMinutes);
    }
}
