<?php

declare(strict_types=1);

namespace App\Domain\Leave\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Leave\Data\AnnualLeaveBalance;
use App\Domain\Leave\Data\AnnualLeavePolicy;
use App\Domain\Leave\Enums\LeaveType;
use App\Domain\Leave\Models\LeaveBalance;
use App\Domain\Leave\Models\LeaveRequest;
use App\Support\Contracts\WorkCalendar;
use Illuminate\Support\Collection;

/**
 * Tính quỹ phép năm của một người: được bao nhiêu, đã dùng bao nhiêu.
 *
 * ## Đã dùng thì đếm NGÀY CÔNG, không đếm ngày lịch
 *
 * Đây là điểm khác biệt lớn nhất so với hạn mức nghỉ không lương, và là lý do
 * `WorkCalendar` tồn tại. Một đơn nghỉ từ thứ sáu tới thứ hai phủ 4 ngày lịch
 * nhưng chỉ tiêu **2,5 ngày phép** — thứ bảy nửa buổi, chủ nhật không tính.
 *
 * Đếm ngày lịch thì một tuần nghỉ ăn 7 ngày trong quỹ 12 ngày, và quỹ phép năm
 * thành một con số vô nghĩa. Nghỉ trùng ngày lễ cũng vậy: không tiêu ngày phép
 * nào, đúng như luật.
 *
 * `LeaveRequest::dayCount()` vẫn đếm ngày lịch và vẫn đúng cho việc nó làm —
 * hiển thị *"nghỉ 4 ngày"*. Hai con số khác nhau vì trả lời hai câu khác nhau.
 *
 * ## Đếm cả đơn ĐANG CHỜ DUYỆT
 *
 * Cùng lý do đã ghi ở `LeaveQuota`: chỉ đếm đơn đã duyệt thì nộp năm đơn nhỏ
 * cùng lúc là lách được, mỗi đơn nhìn riêng đều nằm trong quỹ.
 *
 * ## Đơn vắt qua giao thừa chia phần cho đúng năm
 *
 * Đơn từ 28/12 sang 03/01 tiêu quỹ của cả hai năm, mỗi năm phần của nó. Dồn cả
 * vào năm bắt đầu nghĩa là nghỉ cuối năm bị tính nặng hơn nghỉ giữa năm.
 */
final class ResolveLeaveBalanceAction
{
    public function __construct(
        private readonly WorkCalendar $ngayCong,
    ) {}

    /**
     * @param  bool  $khoaDong  Khoá các đơn đọc được. Bật khi đang ở trong giao
     *                          dịch nộp đơn — xem `soNgayDaDung()`.
     */
    public function execute(User $nhanVien, int $nam, bool $khoaDong = false): AnnualLeaveBalance
    {
        $dong = LeaveBalance::query()
            ->where('user_id', $nhanVien->id)
            ->where('year', $nam)
            ->first();

        return $this->dung(
            $nhanVien,
            $nam,
            $dong,
            $this->soNgayDaDung($nhanVien->id, $nam, khoaDong: $khoaDong),
        );
    }

    /**
     * Quỹ phép của nhiều người cùng lúc, khoá theo `user_id`.
     *
     * Ba truy vấn cố định cho cả phòng, không phải ba lần mỗi người: bảng quỹ
     * phép của một phòng ba mươi người là đúng chỗ dễ thành N+1 nhất của tính
     * năng này. Cùng lý do đã ghi ở `SummariseAttendanceAction`.
     *
     * @param  Collection<int, User>  $nhanVien
     * @return array<int, AnnualLeaveBalance>
     */
    public function forUsers(Collection $nhanVien, int $nam): array
    {
        if ($nhanVien->isEmpty()) {
            return [];
        }

        /** @var list<int> $ids */
        $ids = $nhanVien->pluck('id')->all();

        $dongs = LeaveBalance::query()
            ->whereIn('user_id', $ids)
            ->where('year', $nam)
            ->get()
            ->keyBy('user_id');

        $daDung = $this->soNgayDaDungNhieuNguoi($ids, $nam);

        $ketQua = [];

        foreach ($nhanVien as $u) {
            $ketQua[$u->id] = $this->dung(
                $u,
                $nam,
                $dongs->get($u->id),
                $daDung[$u->id] ?? 0.0,
            );
        }

        return $ketQua;
    }

    /**
     * Số ngày phép một người đã dùng trong năm.
     *
     * @param  int|null  $boQuaDonId  Đơn cần loại khỏi phép đếm — dùng khi kiểm
     *                                lại một đơn đã tồn tại, để nó không tự đếm
     *                                chính mình.
     * @param  bool  $khoaDong  Khoá các dòng đọc được. Bật khi đang ở trong giao
     *                          dịch nộp đơn: hai request gửi gần như cùng lúc
     *                          đều đếm ra "còn chỗ" rồi cùng ghi, và quỹ bị vượt
     *                          mà không có gì báo.
     */
    public function soNgayDaDung(
        int $userId,
        int $nam,
        ?int $boQuaDonId = null,
        bool $khoaDong = false,
    ): float {
        [$dauNam, $cuoiNam] = self::khoangNam($nam);

        $don = LeaveRequest::query()
            ->where('user_id', $userId)
            ->where('type', LeaveType::Annual)
            ->blocking()
            ->where('start_date', '<=', $cuoiNam)
            ->where('end_date', '>=', $dauNam)
            ->when($boQuaDonId !== null, fn ($q) => $q->where('id', '!=', $boQuaDonId))
            ->when($khoaDong, fn ($q) => $q->lockForUpdate())
            ->get(['id', 'start_date', 'end_date']);

        $tong = 0.0;

        foreach ($don as $d) {
            $tong += $this->soNgayCongTrongNam($d->start_date, $d->end_date, $nam);
        }

        return $tong;
    }

    /**
     * Số ngày công mà một khoảng nghỉ tiêu trong một năm cụ thể.
     *
     * Công khai vì `SubmitLeaveRequestAction` cần đúng phép tính này cho đơn
     * SẮP nộp — đơn chưa tồn tại nên không đếm qua database được, và tự tính
     * lại ở đó là hai chỗ hiểu "một ngày phép" khác nhau.
     */
    public function soNgayCongTrongNam(string $tuNgay, string $denNgay, int $nam): float
    {
        [$dauNam, $cuoiNam] = self::khoangNam($nam);

        $tu = max($tuNgay, $dauNam);
        $den = min($denNgay, $cuoiNam);

        return $tu > $den ? 0.0 : $this->ngayCong->countBetween($tu, $den);
    }

    /**
     * Ghép số tính tự động với phần con người can thiệp.
     *
     * Không có dòng `leave_balances` nghĩa là chưa ai động tới: hưởng đúng số
     * hệ thống tính, không tồn, không điều chỉnh.
     */
    private function dung(
        User $nhanVien,
        int $nam,
        ?LeaveBalance $dong,
        float $daDung,
    ): AnnualLeaveBalance {
        $tuTinh = AnnualLeavePolicy::fromConfig()->entitledFor(
            $nhanVien->joined_at,
            $nhanVien->terminated_at,
            $nam,
        );

        $ghiDe = $dong?->entitled_days_override;

        return new AnnualLeaveBalance(
            year: $nam,
            entitledDays: $ghiDe ?? $tuTinh,
            computedEntitledDays: $tuTinh,
            carriedOverDays: $dong === null ? 0.0 : $dong->carried_over_days,
            adjustmentDays: $dong === null ? 0.0 : $dong->adjustment_days,
            usedDays: $daDung,
            isOverridden: $ghiDe !== null,
            note: $dong?->note,
        );
    }

    /**
     * Số ngày đã dùng của nhiều người, khoá theo `user_id`.
     *
     * @param  list<int>  $userIds
     * @return array<int, float>
     */
    private function soNgayDaDungNhieuNguoi(array $userIds, int $nam): array
    {
        [$dauNam, $cuoiNam] = self::khoangNam($nam);

        $don = LeaveRequest::query()
            ->whereIn('user_id', $userIds)
            ->where('type', LeaveType::Annual)
            ->blocking()
            ->where('start_date', '<=', $cuoiNam)
            ->where('end_date', '>=', $dauNam)
            ->get(['user_id', 'start_date', 'end_date']);

        $tong = [];

        foreach ($don as $d) {
            $tong[$d->user_id] = ($tong[$d->user_id] ?? 0.0)
                + $this->soNgayCongTrongNam($d->start_date, $d->end_date, $nam);
        }

        return $tong;
    }

    /** @return array{string, string} */
    private static function khoangNam(int $nam): array
    {
        return [sprintf('%04d-01-01', $nam), sprintf('%04d-12-31', $nam)];
    }
}
