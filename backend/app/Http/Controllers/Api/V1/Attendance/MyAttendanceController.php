<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Attendance;

use App\Domain\Attendance\Actions\SummariseAttendanceAction;
use App\Domain\Attendance\Data\DailyAttendance;
use App\Domain\Attendance\Data\WorkShift;
use App\Domain\Attendance\Data\WorkWeek;
use App\Domain\Identity\Models\User;
use App\Http\Concerns\ReconcilesWithDailyReports;
use App\Http\Concerns\ResolvesApprovedLateArrivals;
use App\Http\Concerns\ResolvesApprovedLeave;
use App\Http\Concerns\ResolvesAttendanceMonth;
use App\Support\Enums\ReportMatch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Giờ làm của chính người đang đăng nhập.
 *
 * Không cần quyền gì thêm: ai cũng được xem số của mình. Đây là điều kiện để
 * việc đo giờ là **tự theo dõi** chứ không phải bị theo dõi lén — nhân viên
 * thấy đúng con số mà quản lý thấy, không phải xin mới biết.
 *
 * Cùng lý do đó, cột đối chiếu với báo cáo ngày hiện ở đây **trước** khi hiện
 * trên bảng của quản lý: người ta phải tự thấy mình quên nộp báo cáo hôm thứ
 * Ba, chứ không phải đợi bị hỏi mới biết.
 */
final class MyAttendanceController
{
    use ReconcilesWithDailyReports;
    use ResolvesApprovedLateArrivals;
    use ResolvesApprovedLeave;
    use ResolvesAttendanceMonth;

    public function __invoke(Request $request, SummariseAttendanceAction $tongHop): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        [$thang, $tuNgay, $denNgay] = $this->resolveMonth($request);

        $ngay = $tongHop->execute([$actor->id], $tuNgay, $denNgay);
        $ngayLe = $tongHop->holidays($tuNgay, $denNgay);
        $daBaoCao = $this->submittedReportKeys([$actor->id], $tuNgay, $denNgay);
        $ngayNghi = $this->approvedLeaveDays([$actor->id], $tuNgay, $denNgay);
        $diMuon = $this->approvedLateArrivals([$actor->id], $tuNgay, $denNgay);

        // Ngày có phiên làm việc, cộng thêm ngày chỉ có báo cáo mà không có
        // phiên nào — xem reportOnlyDays(). Giữ nguyên kiểu Collection để hai
        // phép tổng ở cuối vẫn chạy trên đủ dữ liệu.
        $coO = fn (): array => $ngay->mapWithKeys(
            fn (DailyAttendance $d): array => [$d->workDate => true],
        )->all();

        $ngay = $ngay->values()->concat($this->reportOnlyDays($daBaoCao, $actor->id, $coO()));

        // Ngày nghỉ đã duyệt cũng không có phiên nào — cùng lý do, xem
        // leaveOnlyDays(). Chạy SAU reportOnlyDays để không sinh ô trùng.
        $ngay = $ngay->values()->concat($this->leaveOnlyDays($ngayNghi, $actor->id, $coO()));

        $tuan = WorkWeek::fromConfig();
        $o = [];
        $thieuBaoCao = 0;

        foreach ($ngay as $d) {
            $doiChieu = $this->reconcile(
                $actor->id,
                $d->workDate,
                $d->effectiveMinutes(),
                $daBaoCao,
                $ngayLe,
                $ngayNghi,
            );

            if ($doiChieu->needsAttention() && $d->decision === null) {
                $thieuBaoCao++;
            }

            $o[$d->workDate] = $this->veO(
                $d,
                $this->hasSubmittedReport($daBaoCao, $actor->id, $d->workDate),
                $doiChieu,
                $tuan->shiftFor($d->workDate),
                $this->isLateExcused($diMuon, $tuan->shiftFor($d->workDate), $actor->id, $d->workDate, $d->firstSeenAt),
            );
        }

        return new JsonResponse([
            'data' => [
                'month' => $thang,
                'days' => $this->daysOfMonth($tuNgay, $denNgay),
                'holidays' => (object) $ngayLe,
                // Ép sang object — xem chú thích cùng chỗ ở
                // TeamAttendanceController.
                'cells' => (object) $o,
                'total_minutes' => $ngay->sum(
                    fn (DailyAttendance $d): int => $d->effectiveMinutes(),
                ),
                'days_worked' => $ngay->filter(
                    fn (DailyAttendance $d): bool => $d->effectiveMinutes() > 0,
                )->count(),
                'missing_report_days' => $thieuBaoCao,
            ],
        ]);
    }

    /**
     * @param  WorkShift|null  $ca  Ca của đúng ngày đó. `null` = ngày nghỉ, và
     *                              ngày nghỉ thì KHÔNG tính đi muộn: người làm
     *                              chủ nhật vẫn được tính đủ giờ, nhưng so giờ
     *                              vào của họ với một ca không tồn tại thì ra
     *                              "muộn mấy tiếng" — vô nghĩa và gây hiểu lầm.
     * @return array<string, mixed>
     */
    private function veO(DailyAttendance $d, bool $coBaoCao, ReportMatch $doiChieu, ?WorkShift $ca, bool $duocMien): array
    {
        return [
            'minutes' => $d->effectiveMinutes(),
            // Trả cả số hệ thống đo được lẫn số người quản lý ấn định. Chỉ trả
            // một con số là mất khả năng trả lời "ai đã sửa nó thành thế này".
            'measured_minutes' => $d->measuredMinutes,
            'session_count' => $d->sessionCount,
            'first_seen_at' => $d->firstSeenAt,
            'last_seen_at' => $d->lastSeenAt,
            'decision' => $d->decision?->value,
            'decision_label' => $d->decision?->label(),
            'reason' => $d->reason,
            'has_report' => $coBaoCao,
            'report_match' => $doiChieu->value,
            'report_match_label' => $doiChieu->label(),
            'late_minutes' => $ca?->lateMinutes($d->firstSeenAt) ?? 0,
            'late_excused' => $duocMien,
        ];
    }
}
