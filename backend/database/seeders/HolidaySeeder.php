<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Attendance\Models\Holiday;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

/**
 * Ngày nghỉ lễ theo Điều 112 Bộ luật Lao động 2019 — 11 ngày mỗi năm.
 *
 * ## Vì sao không tính được bằng công thức
 *
 * Ngày lễ Việt Nam chia làm hai loại, và chỉ một loại tính được:
 *
 *   - **Cố định theo dương lịch** — 01/01, 30/4, 01/5, 02/9. Tính cho năm nào
 *     cũng được, và đó là phần lớp này tự sinh.
 *   - **Theo âm lịch hoặc do Chính phủ công bố** — Tết Nguyên đán, Giỗ Tổ Hùng
 *     Vương, và ngày liền kề Quốc khánh. Ba thứ này **không suy ra được**.
 *
 * Ngay cả khi tính đúng ngày âm lịch cũng chưa đủ: hằng năm Chính phủ ra thông
 * báo lịch nghỉ cụ thể, và thường **hoán đổi ngày làm việc** để nối thành kỳ
 * nghỉ dài. Ngày nghỉ thật do văn bản đó quyết định, không do lịch âm.
 *
 * ⚠️ **Bảng LICH_CONG_BO bên dưới phải đối chiếu với thông báo chính thức của
 * Chính phủ trước mỗi năm.** Nó là dữ liệu, không phải chân lý — sai một ngày
 * là cả công ty bị gắn cờ "không báo cáo" đúng ngày Tết.
 *
 * ## Nghỉ bù — khoản 3 Điều 112
 *
 * Ngày lễ trùng ngày nghỉ hằng tuần thì nghỉ bù vào ngày làm việc kế tiếp. Đó
 * là lý do bảng có hai cột: `date` là ngày lễ trên giấy, `observed_date` là
 * ngày thực nghỉ. Bảng công đếm theo cột thứ hai.
 *
 * Chạy lại được nhiều lần: dùng `updateOrCreate` theo `date`.
 */
final class HolidaySeeder extends Seeder
{
    /**
     * Ngày cố định theo dương lịch — đúng cho mọi năm.
     *
     * @var array<string, string> `MM-DD` => tên
     */
    private const array CO_DINH = [
        '01-01' => 'Tết Dương lịch',
        '04-30' => 'Ngày Giải phóng miền Nam',
        '05-01' => 'Ngày Quốc tế Lao động',
        '09-02' => 'Quốc khánh',
    ];

    /**
     * Ngày phải tra thông báo của Chính phủ mới biết.
     *
     * `tet` là **mùng 1 Tết**; nghỉ 5 ngày tính từ 30 tháng Chạp, tức từ mùng 1
     * lùi lại một ngày. `gio_to` là 10/3 âm lịch. `quoc_khanh_ke` là ngày liền
     * kề 02/9 mà Chính phủ chọn (khi thì 01/9, khi thì 03/9).
     *
     * ⚠️ Đối chiếu lại từng năm trước khi dùng thật.
     *
     * @var array<int, array{tet: string, gio_to: string, quoc_khanh_ke: string}>
     */
    private const array LICH_CONG_BO = [
        2026 => ['tet' => '2026-02-17', 'gio_to' => '2026-04-26', 'quoc_khanh_ke' => '2026-09-01'],
        2027 => ['tet' => '2027-02-06', 'gio_to' => '2027-04-15', 'quoc_khanh_ke' => '2027-09-03'],
        2028 => ['tet' => '2028-01-26', 'gio_to' => '2028-04-04', 'quoc_khanh_ke' => '2028-09-01'],
    ];

    public function run(): void
    {
        foreach (array_keys(self::LICH_CONG_BO) as $nam) {
            $this->namMot($nam);
        }
    }

    private function namMot(int $nam): void
    {
        $congBo = self::LICH_CONG_BO[$nam];

        foreach (self::CO_DINH as $ngayThang => $ten) {
            $this->ghi("{$nam}-{$ngayThang}", $ten);
        }

        $this->ghi($congBo['quoc_khanh_ke'], 'Quốc khánh (ngày liền kề)');
        $this->ghi($congBo['gio_to'], 'Giỗ Tổ Hùng Vương');

        /*
         * Tết Nguyên đán: 5 ngày, từ 30 tháng Chạp tới mùng 4.
         *
         * Bắt đầu từ mùng 1 lùi một ngày. Năm thiếu (tháng Chạp chỉ 29 ngày)
         * thì ngày đó là 29 Tết — vẫn đúng vì ta đếm theo dương lịch liền kề,
         * không đếm theo số ngày âm.
         */
        $moc = CarbonImmutable::parse($congBo['tet'])->subDay();

        for ($i = 0; $i < 5; $i++) {
            $ngay = $moc->addDays($i);

            $this->ghi(
                $ngay->toDateString(),
                $i === 0 ? 'Tết Nguyên đán (Giao thừa)' : 'Tết Nguyên đán (mùng '.$i.')',
            );
        }
    }

    private function ghi(string $ngay, string $ten): void
    {
        Holiday::query()->updateOrCreate(
            ['date' => $ngay],
            [
                'observed_date' => $this->ngayNghiBu($ngay),
                'name' => $ten,
                'is_paid' => true,
            ],
        );
    }

    /**
     * Ngày thực nghỉ, sau khi áp khoản 3 Điều 112.
     *
     * Đẩy sang ngày làm việc kế tiếp, và đẩy tiếp nếu ngày đó cũng là ngày nghỉ
     * hằng tuần — hai ngày lễ liền nhau rơi vào cuối tuần thì phải nhảy qua cả
     * hai, không phải chỉ một.
     */
    private function ngayNghiBu(string $ngay): string
    {
        /** @var list<int> $ngayNghiTuan */
        $ngayNghiTuan = config()->array('attendance.weekly_rest_days');

        $moc = CarbonImmutable::parse($ngay);

        while (in_array($moc->dayOfWeek, $ngayNghiTuan, true)) {
            $moc = $moc->addDay();
        }

        return $moc->toDateString();
    }
}
