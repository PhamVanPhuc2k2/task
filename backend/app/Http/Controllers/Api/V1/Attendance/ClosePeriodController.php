<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Attendance;

use App\Domain\Attendance\Actions\ClosePeriodAction;
use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Models\User;
use App\Domain\Payroll\Actions\RecordPayrollAuditAction;
use App\Domain\Payroll\Enums\PayrollAuditEvent;
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
