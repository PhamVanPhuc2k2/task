<?php

declare(strict_types=1);

namespace App\Domain\Leave\Data;

use App\Domain\Leave\Enums\LeaveType;
use App\Domain\Leave\Models\LateArrivalRequest;
use App\Domain\Leave\Models\LeaveRequest;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Config;

/**
 * Hạn mức nghỉ không lương và số lần xin đi muộn.
 *
 * ## Hai chu kỳ khác nhau, có lý do
 *
 * Nghỉ không lương đếm theo **năm**: nó hiếm, dài ngày, và hợp với cách nghĩ
 * của luật lao động. Xin đi muộn đếm theo **tháng**: đó là chuyện lặt vặt lặp
 * lại, mà hạn mức năm thì người ta dùng hết từ tháng ba rồi cả năm còn lại
 * không xin được nữa — trong khi mục đích của con số này là điều chỉnh thói
 * quen, không phải trừng phạt.
 *
 * ## Đếm cả đơn ĐANG CHỜ DUYỆT
 *
 * Chỗ dễ sai nhất. Chỉ đếm đơn đã duyệt thì nộp năm đơn nhỏ cùng lúc là lách
 * được — mỗi đơn nhìn riêng đều nằm trong hạn mức, và người duyệt phải tự cộng
 * nhẩm. Scope `blocking()` gom đúng hai trạng thái còn hiệu lực: chờ duyệt và
 * đã duyệt. Đơn bị từ chối hoặc đã rút thì trả lại hạn mức.
 *
 * ## Một đơn vắt qua hai năm được chia phần cho đúng năm
 *
 * Đơn từ 28/12 sang 03/01 tính 4 ngày cho năm cũ và 3 ngày cho năm mới, chứ
 * không dồn cả 7 ngày vào năm bắt đầu. Dồn hết một bên nghĩa là nghỉ cuối năm
 * bị tính nặng hơn nghỉ giữa năm, mà không có lý do nào để như vậy.
 *
 * Hệ quả: một đơn vắt năm phải lọt hạn mức của **cả hai** năm.
 */
final readonly class LeaveQuota
{
    private function __construct(
        /** Số ngày nghỉ không lương tối đa mỗi năm. 0 = không giới hạn. */
        public int $unpaidMaxDaysPerYear,
        /** Số lần xin đi muộn tối đa mỗi tháng. 0 = không giới hạn. */
        public int $lateArrivalMaxPerMonth,
    ) {}

    public static function fromConfig(): self
    {
        return new self(
            unpaidMaxDaysPerYear: Config::integer('leave.unpaid_max_days_per_year'),
            lateArrivalMaxPerMonth: Config::integer('leave.late_arrival_max_per_month'),
        );
    }

    /**
     * Số ngày nghỉ không lương người này đã dùng trong một năm.
     *
     * @param  int|null  $boQuaDonId  Đơn cần loại khỏi phép đếm — dùng khi kiểm
     *                                lại một đơn đã tồn tại, để nó không tự đếm
     *                                chính mình.
     * @param  bool  $khoaDong  Khoá các dòng đọc được. Bật khi đang ở trong giao
     *                          dịch nộp đơn: hai request gửi gần như cùng lúc —
     *                          bấm đúp nút Nộp, hoặc mở hai tab — đều đếm ra
     *                          "còn chỗ" rồi cùng ghi, và hạn mức bị vượt mà
     *                          không có gì báo. Tắt khi chỉ đọc để hiển thị.
     */
    public function unpaidDaysUsed(int $userId, int $nam, ?int $boQuaDonId = null, bool $khoaDong = false): int
    {
        $dauNam = sprintf('%04d-01-01', $nam);
        $cuoiNam = sprintf('%04d-12-31', $nam);

        $don = LeaveRequest::query()
            ->where('user_id', $userId)
            ->where('type', LeaveType::Unpaid)
            ->blocking()
            ->where('start_date', '<=', $cuoiNam)
            ->where('end_date', '>=', $dauNam)
            ->when(
                $boQuaDonId !== null,
                fn ($q) => $q->where('id', '!=', $boQuaDonId),
            )
            ->when($khoaDong, fn ($q) => $q->lockForUpdate())
            ->get(['start_date', 'end_date']);

        $tong = 0;

        foreach ($don as $d) {
            $tong += self::soNgayTrongNam($d->start_date, $d->end_date, $nam);
        }

        return $tong;
    }

    /** Số lần xin đi muộn đã dùng trong tháng chứa ngày này. */
    public function lateArrivalsUsed(int $userId, string $ngay, ?int $boQuaDonId = null, bool $khoaDong = false): int
    {
        $moc = CarbonImmutable::parse($ngay);

        return LateArrivalRequest::query()
            ->where('user_id', $userId)
            ->blocking()
            ->whereBetween('date', [
                $moc->startOfMonth()->toDateString(),
                $moc->endOfMonth()->toDateString(),
            ])
            ->when(
                $boQuaDonId !== null,
                fn ($q) => $q->where('id', '!=', $boQuaDonId),
            )
            ->when($khoaDong, fn ($q) => $q->lockForUpdate())
            ->count();
    }

    /**
     * Số ngày của một khoảng rơi vào một năm cụ thể.
     *
     * Trả 0 nếu khoảng đó không chạm tới năm đang hỏi — chứ không trả số âm,
     * vốn là thứ sẽ âm thầm làm tổng nhỏ đi và nới hạn mức ra.
     */
    public static function soNgayTrongNam(string $tuNgay, string $denNgay, int $nam): int
    {
        $dauNam = CarbonImmutable::create($nam, 1, 1);
        $cuoiNam = CarbonImmutable::create($nam, 12, 31);

        $tu = CarbonImmutable::parse($tuNgay)->max($dauNam);
        $den = CarbonImmutable::parse($denNgay)->min($cuoiNam);

        if ($tu->greaterThan($den)) {
            return 0;
        }

        // +1 vì cả hai đầu đều là ngày nghỉ: 12/08 đến 12/08 là một ngày.
        return (int) $tu->diffInDays($den) + 1;
    }

    /**
     * Những năm mà một khoảng ngày chạm tới.
     *
     * Đơn vắt qua giao thừa phải lọt hạn mức của cả hai năm, nên chỗ gọi cần
     * biết danh sách này chứ không chỉ năm bắt đầu.
     *
     * @return list<int>
     */
    public static function cacNamCham(string $tuNgay, string $denNgay): array
    {
        $tu = (int) CarbonImmutable::parse($tuNgay)->year;
        $den = (int) CarbonImmutable::parse($denNgay)->year;

        return range($tu, max($tu, $den));
    }
}
