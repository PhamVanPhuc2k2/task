<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Attendance;

use App\Domain\Attendance\Actions\ClosePeriodAction;
use App\Domain\Attendance\Models\AttendancePeriod;
use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Models\User;
use App\Domain\Payroll\Actions\RecordPayrollAuditAction;
use App\Domain\Payroll\Enums\PayrollAuditEvent;
use App\Http\Concerns\GuardsPendingWork;
use App\Http\Concerns\PresentsPeriods;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Chốt sổ một kỳ công.
 *
 * Sau khi chốt, không ai sửa được số liệu của kỳ đó — kể cả admin. Đây là nền
 * móng của mọi phép tính tiền: trả lương từ những con số còn sửa được nghĩa là
 * không bao giờ trả lời được câu "phiếu lương này tính từ đâu ra".
 *
 * Quyền `attendance.period.close` — giám đốc và admin. Mở khoá là quyền KHÁC và
 * hẹp hơn; xem ReopenPeriodController.
 */
final class ClosePeriodController
{
    use GuardsPendingWork;
    use PresentsPeriods;

    public function __invoke(
        Request $request,
        ClosePeriodAction $action,
        RecordPayrollAuditAction $ghiNhatKy,
    ): JsonResponse {
        /** @var User $actor */
        $actor = $request->user();

        abort_unless($actor->can(Permission::ClosePeriod->value), Response::HTTP_FORBIDDEN);

        $request->validate(
            ['period' => ['required', 'string', 'regex:/^\d{4}-\d{2}$/']],
            ['period.regex' => 'Kỳ công phải có dạng YYYY-MM, ví dụ 2026-09.'],
        );

        $ky = (string) $request->string('period');

        /*
        | Còn đơn chờ duyệt thì chưa chốt được.
        |
        | Chốt sổ khoá cả đơn từ, nên một đơn treo qua ngày chốt là đơn KHÔNG AI
        | DUYỆT ĐƯỢC NỮA. Cách hỏng điển hình: giám đốc chốt tháng 8 vào ngày
        | 02/09, ba đơn giải trình nộp hôm 31/08 chết theo, và ba người đó phát
        | hiện ra khi bảng lương về.
        |
        | Chỉ kiểm khi kỳ ĐÃ KẾT THÚC. Kỳ đang chạy thì còn đơn treo là chuyện
        | đương nhiên, và câu đúng để nói lúc đó là "kỳ chưa kết thúc" — lỗi ấy
        | do `ClosePeriodAction` ném ra ngay bên dưới.
        */
        if (AttendancePeriod::daKetThuc($ky)) {
            $this->guardNoPendingWork($ky);
        }

        $kyCong = $action->execute($ky, $actor);

        // Ghi SAU khi chốt thành công. Ghi trước thì một lần chốt bị từ chối vì
        // kỳ chưa kết thúc vẫn để lại một dòng "đã chốt" trong nhật ký.
        $ghiNhatKy->execute(
            event: PayrollAuditEvent::PeriodClosed,
            actor: $actor,
            context: ['period' => $ky],
        );

        return new JsonResponse([
            'data' => $this->presentPeriod($kyCong->load(['closedBy', 'reopenedBy'])),
        ]);
    }
}
