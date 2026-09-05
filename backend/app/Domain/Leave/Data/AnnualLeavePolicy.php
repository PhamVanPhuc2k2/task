<?php

declare(strict_types=1);

namespace App\Domain\Leave\Data;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Config;

/**
 * Chính sách phép năm: được bao nhiêu ngày, và vì sao.
 *
 * Mặc định bám **mức sàn** Bộ luật Lao động 2019:
 *
 *   - **Điều 113**: 12 ngày phép/năm với điều kiện làm việc bình thường, cho
 *     người làm đủ 12 tháng. Chưa đủ thì theo tỷ lệ số tháng làm việc thực tế
 *     (Nghị định 145/2020, Điều 66).
 *   - **Điều 114**: cứ đủ 5 năm làm việc cho cùng một người sử dụng lao động
 *     thì được thêm 1 ngày.
 *
 * Ba con số đều nằm trong cấu hình và đổi được ở màn Cài đặt. Công ty hào phóng
 * hơn luật là chuyện bình thường; viết cứng vào mã thì lần đầu công ty đổi
 * chính sách là một lần phải deploy.
 *
 * ## Vì sao phép tính nằm ở đây chứ không ở model
 *
 * Nó không đọc database. Tách ra thì kiểm được bằng test thuần, không cần dựng
 * người dùng và không cần bảng nào — và đây đúng là loại logic mà một con số
 * lệch sẽ chỉ lộ ra sau vài tháng.
 */
final readonly class AnnualLeavePolicy
{
    private function __construct(
        /** Số ngày phép cơ bản mỗi năm khi làm đủ 12 tháng. */
        public int $baseDays,
        /** Cứ đủ bao nhiêu năm thâm niên thì được thêm phép. 0 = tắt. */
        public int $seniorityStepYears,
        /** Số ngày thêm cho mỗi mốc thâm niên. */
        public int $seniorityExtraDays,
        /** Trần phép tồn chuyển sang năm sau. 0 = cấm chuyển tiếp. */
        public int $carryOverMaxDays,
    ) {}

    public static function fromConfig(): self
    {
        return new self(
            baseDays: Config::integer('leave.annual_base_days'),
            seniorityStepYears: Config::integer('leave.annual_seniority_step_years'),
            seniorityExtraDays: Config::integer('leave.annual_seniority_extra_days'),
            carryOverMaxDays: Config::integer('leave.annual_carry_over_max_days'),
        );
    }

    /**
     * Số ngày phép được hưởng trong một năm.
     *
     * Trả về bội của 0,5 — cùng đơn vị với phép đếm ngày công, vì ngày thứ bảy
     * nửa buổi tiêu nửa ngày phép.
     *
     * `$vaoLam === null` thì coi như làm cả năm: không có ngày vào làm thì
     * không có gì để chia tỷ lệ, và đoán bừa một ngày là cách chắc chắn để ra
     * một con số sai mà không ai truy được. Trả về full là hướng có lợi cho
     * người lao động, và nhân sự nhìn màn quỹ phép sẽ thấy ngay ai thiếu ngày
     * vào làm.
     */
    public function entitledFor(
        ?CarbonImmutable $vaoLam,
        ?CarbonImmutable $nghiViec,
        int $nam,
    ): float {
        $dayDu = $this->baseDays + $this->soNgayThamNien($vaoLam, $nam);

        if ($vaoLam === null) {
            return self::lamTron((float) $dayDu);
        }

        $soThang = $this->soThangLamTrongNam($vaoLam, $nghiViec, $nam);

        if ($soThang === 0) {
            return 0.0;
        }

        return self::lamTron($dayDu * $soThang / 12);
    }

    /** Làm tròn về bội của 0,5 — đơn vị nhỏ nhất của một ngày phép. */
    public static function lamTron(float $ngay): float
    {
        return round($ngay * 2) / 2;
    }

    /**
     * Một ngày cụ thể, chắc chắn không null.
     *
     * `CarbonImmutable::create()` khai kiểu trả về là `static|null` vì nó nhận
     * cả những tổ hợp không tồn tại (ngày 31 tháng 2). Mọi lời gọi ở đây đều là
     * ngày có thật, nên bọc lại một lần thay vì rải `?->` khắp nơi — `?->` sẽ
     * biến một lỗi lập trình thành một con số 0 im lặng.
     */
    private static function ngay(int $nam, int $thang, int $ngay): CarbonImmutable
    {
        return CarbonImmutable::parse(sprintf('%04d-%02d-%02d', $nam, $thang, $ngay));
    }

    /**
     * Ngày thêm theo thâm niên, tính tới cuối năm đang hỏi.
     *
     * "Đủ 5 năm" tính bằng số năm TRÒN từ ngày vào làm tới 31/12 của năm đó.
     * Tính tới ngày hôm nay thì người vào làm tháng 12 sẽ được cộng ngày thâm
     * niên muộn hơn một năm so với người vào làm tháng 1 cùng năm — trong khi
     * quỹ phép là con số của cả năm, không phải của hôm nay.
     */
    private function soNgayThamNien(?CarbonImmutable $vaoLam, int $nam): int
    {
        if ($vaoLam === null || $this->seniorityStepYears <= 0) {
            return 0;
        }

        $cuoiNam = self::ngay($nam, 12, 31);

        if ($vaoLam->greaterThan($cuoiNam)) {
            return 0;
        }

        $namTron = (int) $vaoLam->diffInYears($cuoiNam);

        return intdiv($namTron, $this->seniorityStepYears) * $this->seniorityExtraDays;
    }

    /**
     * Số tháng làm việc thực tế trong năm, từ 0 tới 12.
     *
     * Một tháng được tính khi người đó đi làm **ít nhất nửa số ngày** của tháng
     * ấy. Đây là chỗ dễ cãi nhau nhất của cả phép tính, nên nói rõ vì sao chọn
     * quy tắc này:
     *
     *   - Đếm tháng tròn (`diffInMonths`) thì người vào làm ngày 02/03 và người
     *     vào làm 30/03 ra cùng một con số, dù chênh nhau gần một tháng.
     *   - Đếm mọi tháng có chạm tới thì vào làm ngày 31/03 cũng được trọn tháng
     *     3 — công ty trả cho một tháng không hề có.
     *
     * Ngưỡng "nửa tháng" gần với quy tắc 14 ngày công của bảo hiểm xã hội, và
     * quan trọng hơn: nó giải thích được cho người lao động bằng một câu.
     */
    private function soThangLamTrongNam(
        CarbonImmutable $vaoLam,
        ?CarbonImmutable $nghiViec,
        int $nam,
    ): int {
        $cuoiNam = self::ngay($nam, 12, 31);

        $batDau = $vaoLam->max(self::ngay($nam, 1, 1));
        $ketThuc = ($nghiViec ?? $cuoiNam)->min($cuoiNam);

        if ($batDau->greaterThan($ketThuc)) {
            return 0;
        }

        $dem = 0;

        for ($thang = 1; $thang <= 12; $thang++) {
            $dauThang = self::ngay($nam, $thang, 1);
            $cuoiThang = $dauThang->endOfMonth();

            $tu = $batDau->max($dauThang);
            $den = $ketThuc->min($cuoiThang);

            if ($tu->greaterThan($den)) {
                continue;
            }

            $soNgayLam = (int) $tu->diffInDays($den) + 1;

            if ($soNgayLam * 2 >= $dauThang->daysInMonth) {
                $dem++;
            }
        }

        return $dem;
    }
}
