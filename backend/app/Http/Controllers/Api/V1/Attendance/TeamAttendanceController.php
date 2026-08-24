<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Attendance;

use App\Domain\Attendance\Actions\SummariseAttendanceAction;
use App\Domain\Attendance\Data\DailyAttendance;
use App\Domain\Attendance\Data\WorkShift;
use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Models\Department;
use App\Domain\Identity\Models\User;
use App\Http\Concerns\ReconcilesWithDailyReports;
use App\Http\Concerns\ResolvesApprovedLateArrivals;
use App\Http\Concerns\ResolvesApprovedLeave;
use App\Http\Concerns\ResolvesAttendanceMonth;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Bảng công tháng của cả phòng, hoặc cả công ty.
 *
 * Đây là màn hình thay thế cho cách "treo web đếm giờ" đang dùng. Khác biệt
 * không nằm ở con số giờ, mà ở chỗ nó **đứng cạnh** số phiên làm việc và số
 * lần đụng vào công việc — treo máy tám tiếng mà không có việc nào nhúc nhích
 * thì nhìn phát ra ngay.
 *
 * **Phạm vi bám theo mô hình đã có ở Task**: `attendance.view.team` thấy phòng
 * mình và mọi phòng trực thuộc bên dưới; `attendance.view.all` thấy toàn công
 * ty. Không dùng chung quyền với task vì có người cần xem giờ mà không cần xem
 * nội dung công việc, và ngược lại.
 *
 * Số truy vấn cố định, không tăng theo số nhân sự: một câu lấy danh sách người,
 * một câu gom phiên bằng `GROUP BY`, một câu lấy quyết định, một câu lấy ngày
 * lễ. Có test khoá lại.
 */
final class TeamAttendanceController
{
    use ReconcilesWithDailyReports;
    use ResolvesApprovedLateArrivals;
    use ResolvesApprovedLeave;
    use ResolvesAttendanceMonth;

    public function __invoke(Request $request, SummariseAttendanceAction $tongHop): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        $toanCongTy = $actor->can(Permission::ViewAllAttendance->value);

        abort_unless(
            $toanCongTy || $actor->can(Permission::ViewTeamAttendance->value),
            Response::HTTP_FORBIDDEN,
        );

        [$thang, $tuNgay, $denNgay] = $this->resolveMonth($request);

        $nhanSu = $this->nhanSuTrongPhamVi($actor, $request, $toanCongTy);

        /** @var list<int> $ids */
        $ids = $nhanSu->pluck('id')->values()->all();

        $ngay = $tongHop->execute($ids, $tuNgay, $denNgay);

        /*
        | Ngày nào đã có báo cáo — mảnh còn thiếu từ đợt 3, nay đã có.
        |
        | Ghép ở tầng Http chứ không trong Action: `SummariseAttendanceAction`
        | thuộc miền Attendance, mà deptrac chỉ cho `Attendance → Identity,
        | Support`. Đây là chỗ duy nhất biết cả hai miền.
        */
        $daBaoCao = $this->submittedReportKeys($ids, $tuNgay, $denNgay);
        $ngayNghi = $this->approvedLeaveDays($ids, $tuNgay, $denNgay);
        $ngayLe = $tongHop->holidays($tuNgay, $denNgay);

        $rows = [];

        // Dựng một lần cho cả bảng, không dựng lại trong vòng lặp: bảng ba mươi
        // người một tháng là chín trăm ô, và mỗi lần dựng là năm lượt đọc config.
        $ca = WorkShift::fromConfig();
        $diMuon = $this->approvedLateArrivals($ids, $tuNgay, $denNgay);

        foreach ($nhanSu as $u) {
            $cua = $ngay->filter(
                fn (DailyAttendance $d): bool => $d->userId === $u->id,
            );

            // Thêm ngày chỉ có báo cáo mà không có phiên làm việc nào. Không
            // thêm thì người họp cả ngày rồi vẫn ngồi viết báo cáo sẽ để lại
            // một ô trắng, và quản lý đọc ra "hôm đó không làm gì".
            $coO = fn (): array => $cua->mapWithKeys(
                fn (DailyAttendance $d): array => [$d->workDate => true],
            )->all();

            $cua = $cua->values()->concat($this->reportOnlyDays($daBaoCao, $u->id, $coO()));

            // Ngày nghỉ đã duyệt: người nghỉ thì không có phiên nào, nên nếu
            // không thêm ô ở đây thì ngày nghỉ vẫn hiện y hệt ngày vắng mặt
            // không lý do — và quản lý vẫn phải bấm tay để dọn.
            $cua = $cua->values()->concat($this->leaveOnlyDays($ngayNghi, $u->id, $coO()));

            $o = [];
            $canXem = 0;

            foreach ($cua as $d) {
                // Có giờ mà không có báo cáo là tín hiệu đáng nhìn nhất trên
                // bảng công — và là thứ con số giờ đơn độc không bao giờ nói
                // được.
                $doiChieu = $this->reconcile(
                    $u->id,
                    $d->workDate,
                    $d->effectiveMinutes(),
                    $daBaoCao,
                    $ngayLe,
                    $ngayNghi,
                );

                // Ngày quản lý đã xử lý thì thôi đếm: cờ còn đó để tra lại,
                // nhưng nó đã có người quyết định rồi. Không trừ ra thì con số
                // "cần xem" không bao giờ về 0 và người ta ngừng nhìn nó.
                if ($doiChieu->needsAttention() && $d->decision === null) {
                    $canXem++;
                }

                $o[$d->workDate] = [
                    'minutes' => $d->effectiveMinutes(),
                    'measured_minutes' => $d->measuredMinutes,
                    'session_count' => $d->sessionCount,
                    'decision' => $d->decision?->value,
                    'decision_label' => $d->decision?->label(),
                    'reason' => $d->reason,
                    'has_report' => $this->hasSubmittedReport($daBaoCao, $u->id, $d->workDate),
                    'report_match' => $doiChieu->value,
                    'report_match_label' => $doiChieu->label(),
                    'late_minutes' => $ca->lateMinutes($d->firstSeenAt),
                    'late_excused' => $this->isLateExcused($diMuon, $ca, $u->id, $d->workDate, $d->firstSeenAt),
                ];
            }

            $rows[] = [
                'user' => [
                    'id' => $u->uuid,
                    'name' => $u->name,
                    'department' => $u->department?->name,
                ],
                // Ép sang object: mảng PHP rỗng được json_encode thành `[]`,
                // nên người chưa có ngày công nào sẽ trả về một MẢNG trong khi
                // mọi người khác trả về object. Client tra `cells["2026-08-01"]`
                // trên mảng thì im lặng ra undefined — không lỗi, chỉ sai.
                'cells' => (object) $o,
                'total_minutes' => $cua->sum(
                    fn (DailyAttendance $d): int => $d->effectiveMinutes(),
                ),
                'days_worked' => $cua->filter(
                    fn (DailyAttendance $d): bool => $d->effectiveMinutes() > 0,
                )->count(),
                // Số đưa lên đầu hàng để quản lý không phải rà ba mươi ô.
                'missing_report_days' => $canXem,
            ];
        }

        return new JsonResponse([
            'data' => [
                'month' => $thang,
                'days' => $this->daysOfMonth($tuNgay, $denNgay),
                'holidays' => (object) $ngayLe,
                'rows' => $rows,
                'can_review' => $actor->can(Permission::ReviewAttendance->value),
            ],
        ]);
    }

    /**
     * Những người mà `$actor` được phép xem giờ làm.
     *
     * @return Collection<int, User>
     */
    private function nhanSuTrongPhamVi(
        User $actor,
        Request $request,
        bool $toanCongTy,
    ): Collection {
        $phamVi = $toanCongTy ? null : ($actor->department?->subtreeIds() ?? []);

        return User::query()
            ->where('is_active', true)
            ->when(
                $phamVi !== null,
                fn (Builder $q) => $q->whereIn('department_id', $phamVi ?? []),
            )
            // Lọc thêm theo phòng — chỉ thu hẹp trong phạm vi đã được phép,
            // không mở rộng ra ngoài.
            ->when(
                $request->filled('department_id'),
                fn (Builder $q) => $q->where(
                    'department_id',
                    Department::query()
                        ->where('uuid', $request->string('department_id'))
                        ->value('id'),
                ),
            )
            ->with('department')
            ->orderBy('name')
            ->get();
    }
}
