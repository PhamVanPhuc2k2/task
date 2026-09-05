<?php

declare(strict_types=1);

namespace App\Domain\Payroll\Data;

/**
 * Một phiếu lương đã tính xong.
 *
 * ## Giữ CẢ ĐƯỜNG ĐI, không chỉ con số cuối
 *
 * Câu hỏi thật của người nhận lương không phải *"tôi được bao nhiêu"* — con số
 * đó nằm trên tài khoản ngân hàng — mà là ***"vì sao tháng này ít hơn tháng
 * trước"***. Trả mỗi tổng cuối thì mọi thắc mắc đều dồn về kế toán, và kế toán
 * cũng phải mở database ra dò.
 *
 * Nên phiếu lương mang theo cả số phút chuẩn, số phút thiếu, số phút nghỉ không
 * lương, lương giờ, và từng dòng làm thêm theo hệ số. Cộng tay lại phải ra đúng
 * tổng — đó là phép kiểm mà bất kỳ ai cũng làm được.
 *
 * ## Mọi số tiền là chuỗi số nguyên đồng
 *
 * Tiền Việt Nam không có đơn vị nhỏ hơn đồng. Dùng chuỗi chứ không dùng float —
 * xem `App\Support\Money`.
 */
final readonly class Payslip
{
    /**
     * @param  list<OvertimeLine>  $overtimeLines
     * @param  numeric-string  $baseSalary
     * @param  numeric-string  $allowance
     * @param  numeric-string  $hourlyRate  Lương một giờ, đã làm tròn để hiển
     *                                      thị. Phép tính bên trong dùng bản
     *                                      chưa làm tròn — làm tròn lương giờ
     *                                      trước rồi mới nhân với số giờ là
     *                                      nhân sai số lên gấp bội.
     * @param  numeric-string  $shortfallDeduction
     * @param  numeric-string  $unpaidLeaveDeduction
     * @param  numeric-string  $overtimePay
     * @param  numeric-string  $netTotal
     */
    public function __construct(
        public string $period,
        public int $standardMinutes,
        public int $requiredMinutes,
        public int $workedMinutes,
        public int $paidLeaveMinutes,
        public int $unpaidLeaveMinutes,
        public int $shortfallMinutes,
        public int $overtimeMinutes,
        public string $baseSalary,
        public string $allowance,
        public string $hourlyRate,
        public string $shortfallDeduction,
        public string $unpaidLeaveDeduction,
        public array $overtimeLines,
        public string $overtimePay,
        public string $netTotal,
    ) {}
}
