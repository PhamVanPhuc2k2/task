<?php

declare(strict_types=1);

namespace App\Http\Support;

use App\Domain\Attendance\Actions\SummariseAttendanceAction;
use App\Domain\Attendance\Data\DailyAttendance;
use App\Domain\Attendance\Enums\RequestStatus;
use App\Domain\Attendance\Models\OvertimeRequest;
use App\Domain\Identity\Models\User;
use App\Domain\Leave\Enums\LeaveStatus;
use App\Domain\Leave\Models\LeaveRequest;
use App\Domain\Payroll\Actions\BuildPayslipAction;
use App\Domain\Payroll\Data\Payslip;
use App\Domain\Payroll\Data\PayslipInput;
use App\Domain\Payroll\Models\SalaryRecord;
use App\Support\Contracts\WorkCalendar;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;

/**
 * Đi gom số liệu của bốn miền rồi dựng phiếu lương.
 *
 * ## Vì sao ở tầng Http
 *
 * Phiếu lương cần giờ công (Attendance), đơn nghỉ (Leave), làm thêm giờ
 * (Attendance) và mức lương (Payroll). Luật tầng cấm Payroll gọi sang ba miền
 * kia, còn Http là một trong hai tầng được phép biết nhiều miền — cùng lý do đã
 * ghi ở `GuardsClosedPeriods` và `GuardsPendingWork`.
 *
 * Phép TÍNH nằm ở `BuildPayslipAction`, thuần và không đọc database. Lớp này
 * chỉ đi lấy số.
 *
 * ## Là một lớp, không phải một trait
 *
 * Nó cần ba thứ được ghép sẵn — lịch công ty, phép tổng hợp giờ công, và phép
 * dựng phiếu. Trait thì mỗi controller phải tự nhận đủ ba tham số rồi truyền
 * lại, và cái danh sách đó sẽ dài thêm ở mỗi lần bổ sung.
 *
 * ## Số phút thiếu tính THEO TỪNG NGÀY
 *
 * Không tính được từ hiệu của hai tổng: ân hạn áp cho từng ngày, nên năm phút
 * lẻ mỗi ngày phải biến mất mỗi ngày chứ không cộng dồn thành gần hai tiếng
 * cuối tháng. Đây là lý do phải duyệt qua từng ngày thay vì cộng một câu SQL.
 *
 * ## Ba truy vấn cố định cho cả phòng
 *
 * Giờ công, đơn nghỉ, làm thêm giờ, và mức lương — mỗi thứ MỘT câu cho toàn bộ
 * danh sách người, không phải một câu mỗi người. Bảng lương của một phòng ba
 * mươi người là đúng chỗ dễ thành N+1 nhất, và vòng lặp ngày ở đây che mất dấu
 * hiệu: mỗi câu đều nhanh, chỉ là có chín trăm câu.
 */
final readonly class PayslipAssembler
{
    public function __construct(
        private WorkCalendar $lich,
        private SummariseAttendanceAction $gioCong,
        private BuildPayslipAction $dungPhieu,
    ) {}

    /**
     * Phiếu lương của nhiều người trong một kỳ, khoá theo `user_id`.
     *
     * @param  Collection<int, User>  $nhanVien
     * @param  string  $ky  dạng `YYYY-MM`
     * @return array<int, Payslip>
     */
    public function forUsers(Collection $nhanVien, string $ky): array
    {
        if ($nhanVien->isEmpty()) {
            return [];
        }

        [$tuNgay, $denNgay] = self::khoangKy($ky);

        /** @var list<int> $ids */
        $ids = $nhanVien->pluck('id')->all();

        $ngayTrongKy = self::cacNgay($tuNgay, $denNgay);

        // Số phút chuẩn giống nhau cho mọi người: nó là của LỊCH, không phải
        // của người. Tính một lần cho cả phòng.
        $phutMoiNgay = [];
        $phutChuan = 0;

        foreach ($ngayTrongKy as $ngay) {
            $phut = $this->lich->expectedMinutesOn($ngay);
            $phutMoiNgay[$ngay] = $phut;
            $phutChuan += $phut;
        }

        $tomTat = $this->gioCong->execute($ids, $tuNgay, $denNgay);
        $nghi = $this->nghiPhepTheoNgay($ids, $tuNgay, $denNgay);
        $lamThem = $this->lamThemTheoNguoi($ids, $tuNgay, $denNgay);
        $mucLuong = $this->mucLuongTrongKy($ids, $denNgay);

        $ketQua = [];

        foreach ($nhanVien as $u) {
            $luong = $mucLuong[$u->id] ?? null;
            $cong = $this->congTheoNgay($u->id, $phutMoiNgay, $tomTat, $nghi[$u->id] ?? []);

            $ketQua[$u->id] = $this->dungPhieu->execute(new PayslipInput(
                period: $ky,
                standardMinutes: $phutChuan,
                paidLeaveMinutes: $cong['paidLeaveMinutes'],
                unpaidLeaveMinutes: $cong['unpaidLeaveMinutes'],
                workedMinutes: $cong['workedMinutes'],
                shortfallMinutes: $cong['shortfallMinutes'],
                overtime: $lamThem[$u->id] ?? [],
                // Người chưa từng được đặt lương thì mọi con số tiền ra 0, và
                // phiếu vẫn hiện đủ phần giờ công. Trả `null` hoặc bỏ qua người
                // đó là làm họ biến mất khỏi bảng lương mà không ai biết vì sao.
                baseSalary: self::soTien($luong === null ? null : $luong->base_salary),
                allowance: self::soTien($luong === null ? null : $luong->allowance),
            ));
        }

        return $ketQua;
    }

    /**
     * Cộng dồn theo từng ngày: nghỉ có lương, nghỉ không lương, giờ làm, giờ thiếu.
     *
     * @param  array<string, int>  $phutMoiNgay
     * @param  Collection<string, DailyAttendance>  $tomTat
     * @param  array<string, bool>  $nghi  ngày => có hưởng lương không
     * @return array{paidLeaveMinutes: int, unpaidLeaveMinutes: int, workedMinutes: int, shortfallMinutes: int}
     */
    private function congTheoNgay(int $userId, array $phutMoiNgay, Collection $tomTat, array $nghi): array
    {
        $anHan = Config::integer('payroll.shortfall_grace_minutes');
        $lamTron = Config::integer('payroll.shortfall_round_to_minutes');

        $nghiCoLuong = 0;
        $nghiKhongLuong = 0;
        $daLam = 0;
        $thieu = 0;

        foreach ($phutMoiNgay as $ngay => $phutCa) {
            $phutLam = $tomTat->get($userId.':'.$ngay)?->effectiveMinutes() ?? 0;

            /*
            | Giờ làm được cộng cho MỌI ngày, kể cả ngày nghỉ và ngày lễ.
            |
            | Người làm chủ nhật vẫn được ghi nhận số phút — cùng nguyên tắc với
            | bảng công. Nó không sinh ra tiền ở đây (tiền làm thêm phải qua đơn
            | đã duyệt), nhưng giấu nó đi thì tổng giờ trên phiếu lệch với tổng
            | giờ trên bảng công, và không ai đối chiếu được hai màn hình.
            */
            $daLam += $phutLam;

            if ($phutCa === 0) {
                continue;
            }

            if (array_key_exists($ngay, $nghi)) {
                if ($nghi[$ngay]) {
                    $nghiCoLuong += $phutCa;
                } else {
                    $nghiKhongLuong += $phutCa;
                }

                continue;
            }

            $thieuNgay = $phutCa - $phutLam;

            // Ân hạn DỜI NGƯỠNG, không trừ vào số phút: thiếu 20 phút thì tính
            // đủ 20, không phải 15. Cùng quy ước với ân hạn đi muộn và về sớm.
            if ($thieuNgay <= $anHan) {
                continue;
            }

            $thieu += $lamTron > 0 ? intdiv($thieuNgay, $lamTron) * $lamTron : $thieuNgay;
        }

        return [
            'paidLeaveMinutes' => $nghiCoLuong,
            'unpaidLeaveMinutes' => $nghiKhongLuong,
            'workedMinutes' => $daLam,
            'shortfallMinutes' => $thieu,
        ];
    }

    /**
     * Ngày nghỉ ĐÃ DUYỆT của từng người, và ngày đó có hưởng lương không.
     *
     * Chỉ đếm đơn đã duyệt — khác hẳn phép kiểm hạn mức, nơi đơn đang chờ cũng
     * chặn chỗ. Ở đây là tiền: một đơn chưa ai duyệt thì chưa miễn cho ai cái
     * gì, và trừ lương theo nó là trả tiền dựa trên một quyết định chưa xảy ra.
     *
     * @param  list<int>  $ids
     * @return array<int, array<string, bool>>
     */
    private function nghiPhepTheoNgay(array $ids, string $tuNgay, string $denNgay): array
    {
        $don = LeaveRequest::query()
            ->whereIn('user_id', $ids)
            ->where('status', LeaveStatus::Approved->value)
            ->where('start_date', '<=', $denNgay)
            ->where('end_date', '>=', $tuNgay)
            ->get(['user_id', 'type', 'start_date', 'end_date']);

        $ketQua = [];

        foreach ($don as $d) {
            $tu = max($d->start_date, $tuNgay);
            $den = min($d->end_date, $denNgay);

            foreach (self::cacNgay($tu, $den) as $ngay) {
                /*
                | Đơn chồng lấn không xảy ra được — `SubmitLeaveRequestAction`
                | chặn từ lúc nộp. Nhưng nếu có, ngày KHÔNG hưởng lương thắng:
                | trả nhầm cho công ty là một lỗi kế toán, trả nhầm cho người
                | lao động rồi đòi lại là một cuộc trò chuyện rất khó.
                */
                $coLuong = $d->type->isPaidLeave();

                $ketQua[$d->user_id][$ngay] = ($ketQua[$d->user_id][$ngay] ?? true) && $coLuong;
            }
        }

        return $ketQua;
    }

    /**
     * Làm thêm giờ ĐÃ DUYỆT của từng người, kèm hệ số đã đóng băng.
     *
     * Đọc `approved_minutes` chứ không đọc `minutes`: đó là con số người duyệt
     * chốt, và cũng là con số công ty cam kết trả. `rate_percent` cũng vậy — hệ
     * số đóng băng lúc duyệt, nên lịch đổi sau đó không làm bảng lương đã trả
     * đổi nghĩa.
     *
     * @param  list<int>  $ids
     * @return array<int, list<array{minutes: int, percent: int}>>
     */
    private function lamThemTheoNguoi(array $ids, string $tuNgay, string $denNgay): array
    {
        $don = OvertimeRequest::query()
            ->whereIn('user_id', $ids)
            ->where('status', RequestStatus::Approved->value)
            ->whereBetween('work_date', [$tuNgay, $denNgay])
            ->get(['user_id', 'minutes', 'approved_minutes', 'rate_percent']);

        $ketQua = [];

        foreach ($don as $d) {
            $ketQua[$d->user_id][] = [
                'minutes' => $d->effectiveMinutes(),
                // Đơn đã duyệt luôn có hệ số; `?? 0` là lưới an toàn cho dữ liệu
                // nhập tay, và hệ số 0 ra tiền 0 chứ không ra một lỗi khó hiểu.
                'percent' => $d->rate_percent ?? 0,
            ];
        }

        return $ketQua;
    }

    /**
     * Mức lương hiệu lực vào NGÀY CUỐI KỲ, cho từng người.
     *
     * Chọn ngày cuối kỳ chứ không phải ngày đầu: tăng lương giữa tháng thì mức
     * mới áp cho cả kỳ đó. Đây là cách phần lớn công ty Việt Nam làm, và nó có
     * lợi cho người lao động — nhưng nó là một quyết định, nên ghi ra đây.
     *
     * Chia theo tỷ lệ số ngày ở mỗi mức thì đúng hơn về lý thuyết, và cũng là
     * chỗ để sửa nếu công ty muốn: `salary_records` đã lưu đủ khoảng hiệu lực.
     *
     * @param  list<int>  $ids
     * @return array<int, SalaryRecord>
     */
    private function mucLuongTrongKy(array $ids, string $denNgay): array
    {
        return SalaryRecord::query()
            ->whereIn('user_id', $ids)
            ->where('effective_from', '<=', $denNgay)
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>=', $denNgay))
            ->orderBy('effective_from')
            ->get()
            // Người có hai dòng cùng phủ ngày cuối kỳ thì lấy dòng mới nhất —
            // `keyBy` giữ bản ghi cuối cùng của mỗi khoá.
            ->keyBy('user_id')
            ->all();
    }

    /**
     * Mọi ngày trong khoảng, dạng `Y-m-d`.
     *
     * @return list<string>
     */
    private static function cacNgay(string $tuNgay, string $denNgay): array
    {
        $ngay = CarbonImmutable::parse($tuNgay);
        $het = CarbonImmutable::parse($denNgay);

        $ds = [];

        while ($ngay->lessThanOrEqualTo($het)) {
            $ds[] = $ngay->toDateString();
            $ngay = $ngay->addDay();
        }

        return $ds;
    }

    /**
     * Ép một cột `decimal:2` về `numeric-string` cho phân tích tĩnh.
     *
     * Cast `decimal:2` của Eloquent luôn trả chuỗi số, nhưng kiểu khai báo của
     * nó chỉ là `string` — Larastan không biết điều đó, và mọi phép `bcmath`
     * đằng sau đều đòi `numeric-string`. Cùng cách đã dùng ở `SalaryRecord::total()`.
     *
     * `null` là người chưa từng được đặt lương.
     *
     * @return numeric-string
     */
    private static function soTien(?string $tho): string
    {
        if ($tho === null || ! is_numeric($tho)) {
            return '0';
        }

        return $tho;
    }

    /** @return array{string, string} */
    private static function khoangKy(string $ky): array
    {
        $dau = CarbonImmutable::parse($ky.'-01');

        return [$dau->toDateString(), $dau->endOfMonth()->toDateString()];
    }
}
