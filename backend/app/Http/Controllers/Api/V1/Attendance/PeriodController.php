<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Attendance;

use App\Domain\Attendance\Models\AttendancePeriod;
use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Models\User;
use App\Http\Concerns\GuardsPendingWork;
use App\Http\Concerns\PresentsPeriods;
use App\Support\Time\WorkDate;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Danh sách kỳ công đã từng bị chốt.
 *
 * Nền móng của mọi phép tính tiền ở đợt 4: trả lương từ những con số còn sửa
 * được nghĩa là không bao giờ trả lời được câu *"phiếu lương này tính từ đâu
 * ra"*.
 *
 * ## Hai quyền, không phải một
 *
 * `attendance.period.close` cho giám đốc và admin; `attendance.period.reopen`
 * **chỉ** cho giám đốc. Chốt là việc hành chính cuối kỳ, mở khoá là việc đụng
 * vào số liệu đã dùng để trả lương — hai mức trách nhiệm khác nhau, và admin
 * thường là IT chứ không phải người chịu trách nhiệm về con số lương.
 *
 * ## Mọi lần chốt và mở đều vào nhật ký kiểm toán
 *
 * `payroll_audits` — cùng chỗ với việc đổi mức lương, vì cùng một họ. Bảng đó
 * chỉ ghi thêm, không sửa, không xoá. Bảng `attendance_periods` chỉ giữ trạng
 * thái hiện tại; lịch sử đóng mở nằm ở nhật ký.
 */
final class PeriodController
{
    use GuardsPendingWork;
    use PresentsPeriods;

    /** Số kỳ gần nhất trả về. Mười hai tháng đủ cho mọi câu hỏi thực tế. */
    private const int TRAN = 12;

    public function index(Request $request): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        abort_unless(
            $actor->can(Permission::ViewAllAttendance->value)
                || $actor->can(Permission::ClosePeriod->value),
            Response::HTTP_FORBIDDEN,
        );

        $ds = AttendancePeriod::query()
            ->with(['closedBy', 'reopenedBy'])
            ->orderByDesc('period')
            ->limit(self::TRAN)
            ->get();

        return new JsonResponse([
            'data' => [
                'periods' => $ds->map(fn (AttendancePeriod $k): array => $this->presentPeriod($k))->all(),
                // Giao diện cần biết hiện nút nào, và hỏi server thay vì tự suy
                // từ danh sách quyền — thêm một quyền mới thì giao diện tự đúng.
                'can_close' => $actor->can(Permission::ClosePeriod->value),
                'can_reopen' => $actor->can(Permission::ReopenPeriod->value),

                // Kỳ mà nút "Chốt sổ" sẽ nhắm tới, kèm lý do nếu chưa bấm được.
                'closable' => $this->kyChotDuoc(),
            ],
        ]);
    }

    /**
     * Kỳ gần nhất đã kết thúc mà chưa chốt, kèm số đơn còn treo.
     *
     * Giao diện KHÔNG được tự tính kỳ này. Nó phải biết hôm nay là ngày mấy
     * theo giờ Việt Nam — đồng hồ máy người dùng có thể lệch — và phải biết
     * những kỳ nào đã chốt, thứ trình duyệt không có cách nào suy ra.
     *
     * Trả kèm `pending` để màn hình nói được VÌ SAO nút đang mờ và còn bao
     * nhiêu đơn phải xử lý. Một nút mờ không lời giải thích là thứ người ta bấm
     * ba lần rồi đi hỏi người khác.
     *
     * `null` nghĩa là không còn gì để chốt — mọi kỳ đã kết thúc đều đã chốt.
     *
     * @return array{period: string, pending: array<string, int>, ready: bool}|null
     */
    private function kyChotDuoc(): ?array
    {
        /** @var list<string> $daChot */
        $daChot = AttendancePeriod::query()->locked()->pluck('period')->all();

        // Lùi dần từ kỳ vừa kết thúc. Trần 12 bước: xa hơn thế thì đây không
        // còn là việc "chốt sổ định kỳ" mà là dọn dữ liệu cũ, và một vòng lặp
        // không trần sẽ chạy tới tận 1970 trên hệ thống mới cài.
        $moc = CarbonImmutable::parse(WorkDate::from(now()), WorkDate::timezone())
            ->startOfMonth();

        for ($i = 1; $i <= self::TRAN; $i++) {
            $ky = $moc->subMonths($i)->format('Y-m');

            if (! in_array($ky, $daChot, true)) {
                $conTreo = $this->demDonConTreo($ky);

                return [
                    'period' => $ky,
                    'pending' => $conTreo,
                    'ready' => array_sum($conTreo) === 0,
                ];
            }
        }

        return null;
    }
}
