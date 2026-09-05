<?php

declare(strict_types=1);

namespace App\Domain\Attendance\Actions;

use App\Domain\Attendance\Data\OvertimePolicy;
use App\Domain\Attendance\Data\WorkWeek;
use App\Domain\Attendance\Enums\RequestStatus;
use App\Domain\Attendance\Models\OvertimeRequest;
use App\Domain\Identity\Models\User;
use App\Support\Contracts\WorkCalendar;
use App\Support\Enums\DayKind;
use App\Support\Exceptions\OvertimeCapExceededException;
use App\Support\Exceptions\OvertimeInsideShiftException;
use App\Support\Exceptions\OvertimeOverlapsException;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Nhân viên đăng ký làm thêm giờ.
 *
 * Bốn ràng buộc, và cả bốn có hiệu lực Ở ĐÂY chứ không chỉ ở FormRequest —
 * chúng là chính sách nghiệp vụ, không phải luật định dạng dữ liệu. Chặn ở tầng
 * nhận request thì bất kỳ đường nào khác gọi tới Action sau này đều đi vòng qua
 * được mà không ai nhận ra.
 *
 *   1. Giờ làm thêm phải nằm **ngoài ca** — chỉ với ngày có ca
 *   2. Không chồng lấn với đơn còn hiệu lực khác trong ngày
 *   3. Không vượt ba trần của Điều 107: ngày, tháng, năm
 *   4. (ở FormRequest) mốc giờ hợp lệ và khoảng giờ dương
 *
 * ## Ba phép kiểm cuối nằm TRONG giao dịch, đọc có khoá dòng
 *
 * Hai request gửi gần như cùng lúc — bấm đúp nút Gửi, hoặc mở hai tab — đều
 * thấy "chưa có đơn nào trùng" và "còn dưới trần" rồi cùng ghi. Kết quả là hai
 * khoảng giờ chồng nhau, hoặc trần bị vượt mà không có gì báo.
 */
final class SubmitOvertimeAction
{
    public function __construct(
        private readonly WorkCalendar $lich,
    ) {}

    public function execute(
        User $nguoiNop,
        string $ngay,
        string $tuGio,
        string $denGio,
        string $lyDo,
    ): OvertimeRequest {
        $soPhut = self::phutGiua($tuGio, $denGio);

        $this->kiemNgoaiCa($ngay, $tuGio, $denGio);

        return DB::transaction(function () use ($nguoiNop, $ngay, $tuGio, $denGio, $soPhut, $lyDo): OvertimeRequest {
            $this->kiemChongLan($nguoiNop->id, $ngay, $tuGio, $denGio);
            $this->kiemBaTran($nguoiNop->id, $ngay, $soPhut);

            return OvertimeRequest::query()->create([
                'user_id' => $nguoiNop->id,
                'work_date' => $ngay,
                'start_time' => $tuGio,
                'end_time' => $denGio,
                'minutes' => $soPhut,
                'reason' => $lyDo,
                'status' => RequestStatus::Pending,
            ]);
        });
    }

    /**
     * Số phút đã đăng ký trong một khoảng ngày.
     *
     * Cộng `approved_minutes` khi có, vì đó mới là con số công ty cam kết trả;
     * đơn chờ duyệt thì cộng số đã đăng ký, để năm đơn nhỏ nộp cùng lúc không
     * lách được trần.
     *
     * Cộng ở database chứ không nạp về PHP: ba cái trần đều là phép SUM, và
     * trần theo NĂM sẽ kéo về cả trăm dòng chỉ để cộng một con số.
     */
    public function daDangKy(
        int $userId,
        string $tuNgay,
        string $denNgay,
        ?int $boQuaDonId = null,
        bool $khoaDong = false,
    ): int {
        return (int) OvertimeRequest::query()
            ->where('user_id', $userId)
            ->blocking()
            ->whereBetween('work_date', [$tuNgay, $denNgay])
            ->when($boQuaDonId !== null, fn ($q) => $q->where('id', '!=', $boQuaDonId))
            ->when($khoaDong, fn ($q) => $q->lockForUpdate())
            ->sum(DB::raw('COALESCE(approved_minutes, minutes)'));
    }

    /** Số phút giữa hai mốc `HH:MM` trong cùng một ngày. */
    public static function phutGiua(string $tuGio, string $denGio): int
    {
        return self::phutTrongNgay($denGio) - self::phutTrongNgay($tuGio);
    }

    /**
     * `HH:MM` hoặc `HH:MM:SS` thành số phút tính từ nửa đêm.
     *
     * Cộng trừ trên chuỗi giờ chứ không dựng `CarbonImmutable`: ghép "20:00"
     * thành một mốc thời gian phải kèm ngày và múi giờ, và đó là chỗ sinh ra
     * lệch bảy tiếng. Ở đây chỉ cần hiệu hai mốc trong cùng một ngày.
     */
    private static function phutTrongNgay(string $gio): int
    {
        [$h, $p] = array_pad(array_map('intval', explode(':', $gio)), 2, 0);

        return $h * 60 + $p;
    }

    /**
     * Giờ làm thêm phải nằm ngoài ca của hôm đó.
     *
     * "Làm thêm từ 9h tới 11h" vào một ngày làm việc là hai tiếng đã được trả
     * lương bình thường rồi — trả thêm 150% cho nó là trả hai lần cho cùng một
     * giờ làm.
     *
     * Ngày nghỉ hằng tuần và NGÀY LỄ thì không ai đi làm, nên mọi mốc giờ đều
     * là làm thêm.
     *
     * Phải hỏi `WorkCalendar` chứ không hỏi mỗi `WorkWeek`: `shiftFor()` chỉ
     * biết hôm đó là thứ mấy, nên một ngày lễ rơi vào thứ hai vẫn trả về ca
     * ngày thường. Hậu quả là đăng ký làm thêm 9h–11h ngày Quốc khánh bị từ
     * chối oan, với câu lỗi nói về một ca không hề tồn tại hôm đó.
     */
    private function kiemNgoaiCa(string $ngay, string $tuGio, string $denGio): void
    {
        if ($this->lich->kindOf($ngay) !== DayKind::Working) {
            return;
        }

        $ca = WorkWeek::fromConfig()->shiftFor($ngay);

        if ($ca === null) {
            return;
        }

        // Hai khoảng giao nhau khi mỗi khoảng bắt đầu trước khi khoảng kia kết
        // thúc. Chạm đúng biên thì KHÔNG tính là giao: làm thêm từ đúng giờ tan
        // ca là trường hợp thường gặp nhất.
        $giao = self::phutTrongNgay($tuGio) < self::phutTrongNgay($ca->end)
            && self::phutTrongNgay($denGio) > self::phutTrongNgay($ca->morningStart);

        if ($giao) {
            throw new OvertimeInsideShiftException($ca->morningStart, $ca->end);
        }
    }

    private function kiemChongLan(int $userId, string $ngay, string $tuGio, string $denGio): void
    {
        $trung = OvertimeRequest::query()
            ->where('user_id', $userId)
            ->where('work_date', $ngay)
            ->blocking()
            ->where('start_time', '<', $denGio)
            ->where('end_time', '>', $tuGio)
            ->lockForUpdate()
            ->first();

        if ($trung instanceof OvertimeRequest) {
            throw new OvertimeOverlapsException(
                $ngay,
                $trung->startLabel(),
                $trung->endLabel(),
            );
        }
    }

    /**
     * Ba trần của Điều 107: 50% giờ làm bình thường mỗi ngày, 40 giờ mỗi tháng,
     * 200 giờ mỗi năm.
     *
     * Kiểm theo thứ tự hẹp dần ra rộng — ngày, rồi tháng, rồi năm. Người nộp
     * gặp trần gần nhất trước, và đó là trần họ có thể làm gì đó với nó: rút
     * ngắn hôm nay dễ hơn là dời sang năm sau.
     */
    private function kiemBaTran(int $userId, string $ngay, int $canThem): void
    {
        $chinhSach = OvertimePolicy::fromConfig();
        $moc = CarbonImmutable::parse($ngay);

        if ($chinhSach->maxMinutesPerDay > 0) {
            $daDung = $this->daDangKy($userId, $ngay, $ngay, khoaDong: true);

            if ($daDung + $canThem > $chinhSach->maxMinutesPerDay) {
                throw OvertimeCapExceededException::trongNgay(
                    $ngay, $daDung, $chinhSach->maxMinutesPerDay, $canThem,
                );
            }
        }

        if ($chinhSach->maxMinutesPerMonth > 0) {
            $daDung = $this->daDangKy(
                $userId,
                $moc->startOfMonth()->toDateString(),
                $moc->endOfMonth()->toDateString(),
                khoaDong: true,
            );

            if ($daDung + $canThem > $chinhSach->maxMinutesPerMonth) {
                throw OvertimeCapExceededException::trongThang(
                    substr($ngay, 0, 7), $daDung, $chinhSach->maxMinutesPerMonth, $canThem,
                );
            }
        }

        if ($chinhSach->maxMinutesPerYear > 0) {
            $nam = (int) substr($ngay, 0, 4);

            $daDung = $this->daDangKy(
                $userId,
                sprintf('%04d-01-01', $nam),
                sprintf('%04d-12-31', $nam),
                khoaDong: true,
            );

            if ($daDung + $canThem > $chinhSach->maxMinutesPerYear) {
                throw OvertimeCapExceededException::trongNam(
                    $nam, $daDung, $chinhSach->maxMinutesPerYear, $canThem,
                );
            }
        }
    }
}
