<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Attendance;

use App\Domain\Attendance\Data\DayTimeline;
use App\Domain\Attendance\Data\WorkShift;
use App\Domain\Attendance\Data\WorkWeek;
use App\Domain\Attendance\Models\WorkSession;
use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Models\User;
use App\Http\Concerns\ResolvesApprovedLateArrivals;
use App\Http\Concerns\ResolvesApprovedLeave;
use App\Support\Time\WorkDate;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Date;

/**
 * Dòng thời gian một ngày của cả đội — màn hình mở mỗi sáng.
 *
 * ## Vì sao cần thêm một màn nữa
 *
 * Lưới tháng trả lời *"tháng này ai làm bao nhiêu giờ"*. Màn này trả lời câu
 * khác hẳn: **"hôm nay ai đang làm, và khoảng nào ngồi không"**.
 *
 * Tổng số giờ không nói được nhịp làm việc — làm 5 tiếng liền một mạch và làm
 * 5 tiếng rải rác từ sáng tới tối cho ra cùng một con số. Mà nhịp mới là thứ
 * người quản lý nhìn vào để thấy hôm nay có gì bất thường.
 *
 * ## Khung giờ do DỮ LIỆU quyết, không cắt cứng
 *
 * Mặc định phủ ca làm, nhưng nới ra khi có người làm ngoài ca. Công ty làm
 * remote nên làm buổi tối là chuyện bình thường; cắt cứng 08h–18h thì phiên
 * lúc 21h **biến mất khỏi màn hình** mà không có gì báo — đúng loại hỏng im
 * lặng dự án này liên tục phải trả giá.
 *
 * ## Người không có phiên nào vẫn hiện
 *
 * Vắng mặt cũng là thông tin, và là thông tin người mở màn này cần nhất. Lọc
 * họ ra thì màn hình toàn người đang làm.
 */
final class AttendanceTimelineController
{
    use ResolvesApprovedLateArrivals;
    use ResolvesApprovedLeave;

    /** Mốc mặc định của khung giờ, nới ra theo dữ liệu thật. */
    private const GIO_SOM_NHAT = 8;

    private const GIO_MUON_NHAT = 18;

    public function __invoke(Request $request): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        $toanCongTy = $actor->can(Permission::ViewAllAttendance->value);

        abort_unless(
            $toanCongTy || $actor->can(Permission::ViewTeamAttendance->value),
            Response::HTTP_FORBIDDEN,
        );

        $ngay = $this->ngay($request);

        $nhanSu = $this->nhanSu($actor, $toanCongTy);

        /** @var list<int> $ids */
        $ids = $nhanSu->pluck('id')->values()->all();

        $theoNguoi = $this->phienTrongNgay($ids, $ngay);

        /*
        | Hai biến, và tách chúng ra là có lý do.
        |
        | `$caHomDo` là ca THẬT của ngày đang xem — `null` nếu hôm đó nghỉ. Nó
        | quyết định có tính đi muộn hay không.
        |
        | `$ca` chỉ để VẼ: lưới giờ và dải nghỉ trưa vẫn cần một khung tham
        | chiếu kể cả ngày chủ nhật, nếu không thì mở dòng thời gian một ngày
        | nghỉ ra sẽ thấy một khung trống không đọc được. Giao diện biết đó
        | không phải ngày làm việc nhờ cờ `is_working_day`.
        */
        $tuan = WorkWeek::fromConfig();
        $caHomDo = $tuan->shiftFor($ngay);
        $ca = $caHomDo ?? WorkShift::fromConfig();
        $ngayNghi = $this->approvedLeaveDays($ids, $ngay, $ngay);
        $diMuon = $this->approvedLateArrivals($ids, $ngay, $ngay);

        $hang = [];
        $somNhat = self::GIO_SOM_NHAT;
        $muonNhat = self::GIO_MUON_NHAT;

        foreach ($nhanSu as $u) {
            $dong = DayTimeline::build(
                $u->id,
                $theoNguoi[$u->id] ?? [],
                $ca->lunchStart,
                $ca->lunchEnd,
            );

            foreach ($dong->sessions as $p) {
                $somNhat = min($somNhat, (int) substr($p['start'], 0, 2));
                // Cộng 1 để phiên kết thúc lúc 21:40 không bị cắt mất đuôi.
                $muonNhat = max($muonNhat, (int) substr($p['end'], 0, 2) + 1);
            }

            $phutMuon = $caHomDo?->lateMinutes($dong->firstSeenUtc) ?? 0;

            $hang[] = [
                'user' => [
                    'id' => $u->uuid,
                    'name' => $u->name,
                    'department' => $u->department?->name,
                ],
                'sessions' => $dong->sessions,
                'gaps' => $dong->gaps,
                'worked_minutes' => $dong->workedMinutes,
                'idle_minutes' => $dong->idleMinutes,
                'lunch_minutes' => $dong->lunchMinutes,
                'first_seen' => $dong->firstSeen,
                'last_seen' => $dong->lastSeen,
                'late_minutes' => $phutMuon,
                'late_excused' => $this->isLateExcused(
                    $diMuon,
                    // Ca THẬT, không phải ca dùng để vẽ: ngày nghỉ không tính
                    // đi muộn nên cũng không có gì để miễn.
                    $caHomDo,
                    $u->id,
                    $ngay,
                    $dong->firstSeenUtc,
                ),
                'on_leave' => $this->isOnApprovedLeave($ngayNghi, $u->id, $ngay),
            ];
        }

        return new JsonResponse([
            'data' => [
                'date' => $ngay,
                'range' => [
                    'start' => sprintf('%02d:00', min($somNhat, 23)),
                    'end' => sprintf('%02d:00', min($muonNhat, 24)),
                ],
                'is_working_day' => $caHomDo !== null,
                'shift' => [
                    'morning_start' => $ca->morningStart,
                    'lunch_start' => $ca->lunchStart,
                    'lunch_end' => $ca->lunchEnd,
                    'end' => $ca->end,
                ],
                'rows' => $hang,
            ],
        ]);
    }

    /**
     * Phiên làm việc trong ngày, gom theo người.
     *
     * Một truy vấn cho cả đội rồi gom trong PHP. Hỏi từng người là mười câu SQL
     * cho một màn hình mở mỗi sáng.
     *
     * @param  list<int>  $ids
     * @return array<int, list<array{started_at: string, ended_at: string}>>
     */
    private function phienTrongNgay(array $ids, string $ngay): array
    {
        if ($ids === []) {
            return [];
        }

        $gom = [];

        $ds = WorkSession::query()
            ->whereIn('user_id', $ids)
            // Lọc theo `work_date` chứ không theo khoảng `started_at`: cột đó
            // đã được tính sẵn theo giờ Việt Nam, còn `started_at` là UTC nên
            // so khoảng sẽ lệch mất phần đầu và cuối ngày.
            ->where('work_date', $ngay)
            ->orderBy('started_at')
            ->get(['user_id', 'started_at', 'ended_at']);

        foreach ($ds as $p) {
            $gom[$p->user_id][] = [
                'started_at' => (string) $p->getRawOriginal('started_at'),
                'ended_at' => (string) $p->getRawOriginal('ended_at'),
            ];
        }

        return $gom;
    }

    /** @return Collection<int, User> */
    private function nhanSu(User $actor, bool $toanCongTy)
    {
        $q = User::query()->where('is_active', true)->with('department');

        if (! $toanCongTy) {
            $q->whereIn('department_id', $actor->department?->subtreeIds() ?? []);
        }

        return $q->orderBy('name')->get();
    }

    private function ngay(Request $request): string
    {
        $ngay = (string) $request->string('date');

        // Không tin chuỗi từ client: một giá trị rác sẽ lọt xuống truy vấn và
        // trả về bảng rỗng mà không ai biết vì sao.
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $ngay) !== 1) {
            return WorkDate::from(Date::now());
        }

        return CarbonImmutable::parse($ngay)->toDateString();
    }
}
