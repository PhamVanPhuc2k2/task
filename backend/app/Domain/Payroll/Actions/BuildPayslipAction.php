<?php

declare(strict_types=1);

namespace App\Domain\Payroll\Actions;

use App\Domain\Payroll\Data\OvertimeLine;
use App\Domain\Payroll\Data\Payslip;
use App\Domain\Payroll\Data\PayslipInput;
use App\Support\Money;

/**
 * Dựng một phiếu lương từ số liệu đã gom sẵn.
 *
 * ## Phép tính thuần, không đọc database
 *
 * Cùng đầu vào thì luôn ra cùng kết quả. Với mã tính tiền thì đó không phải sự
 * ngăn nắp mà là điều kiện để kiểm được: mọi trường hợp biên — kỳ toàn ngày lễ,
 * người nghỉ không lương cả tháng, lương giờ chia cho 0 — đều dựng được bằng
 * một dòng test, không cần người dùng và không cần bảng nào.
 *
 * Việc đi gom số liệu từ bốn miền nằm ở tầng Http; xem `PayslipInput`.
 *
 * ## Công thức
 *
 * ```
 * lương giờ  = lương tháng ÷ (số phút chuẩn của kỳ ÷ 60)
 * trừ thiếu giờ     = số phút thiếu      ÷ 60 × lương giờ
 * trừ nghỉ không lương = số phút nghỉ KL ÷ 60 × lương giờ
 * tiền làm thêm     = Σ (số phút ÷ 60 × lương giờ × hệ số%)
 *
 * thực nhận = lương tháng + phụ cấp − trừ thiếu giờ − trừ nghỉ KL + làm thêm
 * ```
 *
 * **Số phút chuẩn tính theo lịch thực tế từng kỳ**, không phải một con số 26
 * ngày cố định — công ty đã chốt. Hệ quả: lương giờ đổi theo tháng, nên phiếu
 * lương nói ra số phút chuẩn ngay cạnh lương giờ.
 *
 * ## Làm tròn ở đâu
 *
 * Lương giờ giữ nguyên độ chính xác trung gian suốt phép tính; chỉ TỪNG DÒNG
 * TIỀN mới làm tròn về đồng. Làm tròn lương giờ trước rồi nhân với số giờ là
 * nhân sai số lên gấp bội — với một tháng 176 giờ thì lệch tới cả trăm đồng.
 *
 * Cộng tay các dòng trên phiếu phải ra đúng tổng. Đó là phép kiểm mà bất kỳ ai
 * cũng làm được, nên tổng được cộng TỪ CÁC DÒNG ĐÃ LÀM TRÒN chứ không tính lại
 * từ đầu ở độ chính xác cao.
 *
 * ## Không phải bảng lương đầy đủ
 *
 * Chưa có thuế thu nhập cá nhân, bảo hiểm xã hội, công đoàn phí. Ba khoản đó
 * cần biểu thuế luỹ tiến, mức đóng theo vùng và trần đóng — mỗi thứ là một
 * chính sách riêng có kỳ hiệu lực riêng. Phiếu này trả lời câu hẹp hơn: *"tiền
 * công theo giờ làm việc thực tế của kỳ này là bao nhiêu"*.
 *
 * Quy ước đặt tên theo README: không có `fine` hay `penalty` ở đâu trong mã —
 * Điều 127 Bộ luật Lao động 2019 cấm phạt tiền thay xử lý kỷ luật, còn trả theo
 * giờ công thực tế thì hợp lệ. Khác biệt nằm ở cách đặt tên và cách ghi trên
 * phiếu lương.
 */
final class BuildPayslipAction
{
    public function execute(PayslipInput $vao): Payslip
    {
        $luongGio = Money::luongGio($vao->baseSalary, $vao->standardMinutes);

        $truThieuGio = Money::theoPhut($luongGio, $vao->shortfallMinutes);
        $truNghiKhongLuong = Money::theoPhut($luongGio, $vao->unpaidLeaveMinutes);

        $dongLamThem = $this->dongLamThem($luongGio, $vao->overtime);

        $tienLamThem = Money::tong(
            array_map(static fn (OvertimeLine $d): string => $d->amount, $dongLamThem),
        );

        $phutLamThem = array_sum(
            array_map(static fn (OvertimeLine $d): int => $d->minutes, $dongLamThem),
        );

        /*
        | Cộng từ các dòng ĐÃ LÀM TRÒN, không tính lại ở độ chính xác cao.
        |
        | Người nhận lương sẽ cộng tay các dòng trên phiếu để đối chiếu. Nếu tổng
        | được tính theo đường khác thì nó lệch vài đồng so với phép cộng ấy, và
        | vài đồng lệch trên một phiếu lương là đủ để mất niềm tin vào cả bảng.
        */
        $thucNhan = Money::tong([
            Money::lamTron($vao->baseSalary),
            Money::lamTron($vao->allowance),
            bcmul($truThieuGio, '-1', 0),
            bcmul($truNghiKhongLuong, '-1', 0),
            $tienLamThem,
        ]);

        return new Payslip(
            period: $vao->period,
            standardMinutes: $vao->standardMinutes,
            requiredMinutes: $vao->requiredMinutes(),
            workedMinutes: $vao->workedMinutes,
            paidLeaveMinutes: $vao->paidLeaveMinutes,
            unpaidLeaveMinutes: $vao->unpaidLeaveMinutes,
            shortfallMinutes: $vao->shortfallMinutes,
            overtimeMinutes: $phutLamThem,
            baseSalary: Money::lamTron($vao->baseSalary),
            allowance: Money::lamTron($vao->allowance),
            hourlyRate: Money::lamTron($luongGio),
            shortfallDeduction: $truThieuGio,
            unpaidLeaveDeduction: $truNghiKhongLuong,
            overtimeLines: $dongLamThem,
            overtimePay: $tienLamThem,
            netTotal: $thucNhan,
        );
    }

    /**
     * Gom làm thêm giờ thành từng dòng theo hệ số, sắp từ thấp lên cao.
     *
     * Sắp xếp để phiếu lương của hai tháng đọc giống nhau: thứ tự chạy theo thứ
     * tự đơn được duyệt thì cùng một người, cùng một loại giờ, hai tháng lại
     * nằm ở hai vị trí khác nhau trên phiếu.
     *
     * @param  numeric-string  $luongGio
     * @param  list<array{minutes: int, percent: int}>  $lamThem
     * @return list<OvertimeLine>
     */
    private function dongLamThem(string $luongGio, array $lamThem): array
    {
        /** @var array<int, int> $gom hệ số => tổng phút */
        $gom = [];

        foreach ($lamThem as $d) {
            if ($d['minutes'] <= 0) {
                continue;
            }

            $gom[$d['percent']] = ($gom[$d['percent']] ?? 0) + $d['minutes'];
        }

        ksort($gom);

        $dong = [];

        foreach ($gom as $phanTram => $soPhut) {
            $dong[] = new OvertimeLine(
                percent: $phanTram,
                minutes: $soPhut,
                amount: Money::theoPhut($luongGio, $soPhut, $phanTram),
            );
        }

        return $dong;
    }
}
