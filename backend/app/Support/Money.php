<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Phép tính trên tiền, bằng `bcmath` chứ không bằng số thực.
 *
 * ## Vì sao không dùng float
 *
 * `12500000.10 + 2000000.20` trong PHP ra `14500000.299999999` — đúng cái sai
 * số mà kiểu DECIMAL sinh ra để tránh. Dự án đã dùng `bcadd`/`bccomp` rải rác
 * ở miền Payroll; lớp này gom lại vì bảng lương cần cả NHÂN và CHIA, mà hai
 * phép đó có thêm một cái bẫy riêng.
 *
 * ## Cái bẫy: `bcmath` CẮT, không làm tròn
 *
 * `bcdiv('10', '3', 2)` ra `3.33`, và `bcdiv('20', '3', 2)` ra `6.66` chứ không
 * phải `6.67`. Với một dòng lương thì lệch một đồng; với ba mươi người mười hai
 * tháng thì lệch một khoản có người sẽ hỏi. `lamTron()` cộng nửa đơn vị trước
 * khi cắt — cách làm tròn nửa lên chuẩn.
 *
 * ## Đơn vị là ĐỒNG, không có hào
 *
 * Mọi số tiền trả ra ở đây làm tròn tới **đồng** (0 chữ số thập phân). Tiền
 * Việt Nam không có đơn vị nhỏ hơn, và phiếu lương nào cũng ghi số nguyên —
 * giữ hai chữ số thập phân chỉ tạo ra những dòng `.00` và một câu hỏi "sao lại
 * có hào ở đây".
 *
 * Phép tính TRUNG GIAN thì giữ nhiều chữ số (`SCALE_TRUNG_GIAN`): làm tròn
 * lương giờ trước rồi mới nhân với số giờ là cách nhân sai số lên gấp bội.
 */
final class Money
{
    /**
     * Số chữ số giữ lại ở bước trung gian.
     *
     * Sáu là dư sức cho lương giờ: lương tháng 100 triệu chia cho 176 giờ ra
     * ~568.181,818181 đồng/giờ, và sai số ở chữ số thứ sáu không leo lên nổi
     * một đồng khi nhân lại với số giờ của một tháng.
     */
    private const int SCALE_TRUNG_GIAN = 6;

    /**
     * Lương một giờ, từ lương tháng và số phút chuẩn của kỳ.
     *
     * Trả về ở độ chính xác TRUNG GIAN, chưa làm tròn — chỗ gọi sẽ nhân với số
     * giờ rồi mới làm tròn một lần.
     *
     * `$phutChuan <= 0` thì trả `'0'`: một kỳ không có ngày công nào (toàn ngày
     * lễ, hoặc người vào làm sau khi kỳ kết thúc) thì lương giờ không định
     * nghĩa được, và chia cho 0 sẽ ném lỗi giữa lúc dựng bảng lương.
     *
     * @param  numeric-string  $luongThang
     * @return numeric-string
     */
    public static function luongGio(string $luongThang, int $phutChuan): string
    {
        if ($phutChuan <= 0) {
            return '0';
        }

        $gioChuan = bcdiv((string) $phutChuan, '60', self::SCALE_TRUNG_GIAN);

        /** @var numeric-string $ket */
        $ket = bcdiv($luongThang, $gioChuan, self::SCALE_TRUNG_GIAN);

        return $ket;
    }

    /**
     * Tiền của một số phút, theo lương giờ và hệ số phần trăm.
     *
     * `$phanTram = 100` cho phép tính bình thường; `150`, `200`, `300` cho tiền
     * làm thêm giờ (Điều 98 Bộ luật Lao động 2019).
     *
     * @param  numeric-string  $luongGio
     * @return numeric-string số nguyên đồng
     */
    public static function theoPhut(string $luongGio, int $soPhut, int $phanTram = 100): string
    {
        $gio = bcdiv((string) $soPhut, '60', self::SCALE_TRUNG_GIAN);

        $tho = bcmul(
            bcmul($luongGio, $gio, self::SCALE_TRUNG_GIAN),
            bcdiv((string) $phanTram, '100', self::SCALE_TRUNG_GIAN),
            self::SCALE_TRUNG_GIAN,
        );

        return self::lamTron($tho);
    }

    /**
     * Làm tròn nửa lên, về số nguyên đồng.
     *
     * `bcmath` CẮT chứ không làm tròn, nên phải cộng nửa đơn vị trước khi cắt.
     * Xử lý cả số âm — một dòng điều chỉnh giảm vẫn phải tròn về đúng hướng.
     *
     * @param  numeric-string  $so
     * @return numeric-string
     */
    public static function lamTron(string $so): string
    {
        $nua = bccomp($so, '0', self::SCALE_TRUNG_GIAN) < 0 ? '-0.5' : '0.5';

        /** @var numeric-string $ket */
        $ket = bcadd($so, $nua, 0);

        return $ket;
    }

    /**
     * Cộng dồn một danh sách số tiền.
     *
     * `array_sum()` cộng bằng toán tử số học của PHP, tức là ép cả danh sách
     * sang float — đúng thứ lớp này sinh ra để tránh.
     *
     * @param  list<numeric-string>  $cac
     * @return numeric-string
     */
    public static function tong(array $cac): string
    {
        $tong = '0';

        foreach ($cac as $so) {
            $tong = bcadd($tong, $so, 0);
        }

        /** @var numeric-string $tong */
        return $tong;
    }
}
