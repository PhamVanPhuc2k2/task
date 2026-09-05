<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Attendance;

use App\Domain\Attendance\Actions\ReopenPeriodAction;
use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Models\User;
use App\Domain\Payroll\Actions\RecordPayrollAuditAction;
use App\Domain\Payroll\Enums\PayrollAuditEvent;
use App\Http\Concerns\PresentsPeriods;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Mở khoá một kỳ công đã chốt.
 *
 * Quyền `attendance.period.reopen` — CHỈ giám đốc. Admin chốt sổ được nhưng
 * không mở khoá được: chốt là việc hành chính cuối kỳ, mở khoá là việc đụng vào
 * số liệu đã dùng để trả lương, và admin thường là IT chứ không phải người chịu
 * trách nhiệm về con số lương. Xem ngoại lệ tương ứng trong
 * `Role::defaultPermissions()`.
 *
 * Lý do là bắt buộc và có mức sàn độ dài — không có nó thì ba tháng sau không
 * ai trả lời được vì sao giờ công tháng 9 khác con số trên phiếu lương tháng 9.
 */
final class ReopenPeriodController
{
    use PresentsPeriods;

    public function __invoke(
        Request $request,
        ReopenPeriodAction $action,
        RecordPayrollAuditAction $ghiNhatKy,
    ): JsonResponse {
        /** @var User $actor */
        $actor = $request->user();

        abort_unless($actor->can(Permission::ReopenPeriod->value), Response::HTTP_FORBIDDEN);

        $request->validate(
            [
                'period' => ['required', 'string', 'regex:/^\d{4}-\d{2}$/'],
                // Tối thiểu 10 ký tự, cùng lý do với lý do đơn nghỉ: không có
                // mức sàn thì trường này đầy những dòng "sửa" và "nhầm".
                'reason' => ['required', 'string', 'min:10', 'max:1000'],
            ],
            [
                'period.regex' => 'Kỳ công phải có dạng YYYY-MM, ví dụ 2026-09.',
                'reason.min' => 'Ghi rõ vì sao phải mở khoá — đây là số liệu đã dùng để trả lương.',
            ],
        );

        $ky = (string) $request->string('period');
        $lyDo = (string) $request->string('reason');

        $kyCong = $action->execute($ky, $lyDo, $actor);

        $ghiNhatKy->execute(
            event: PayrollAuditEvent::PeriodReopened,
            actor: $actor,
            context: ['period' => $ky, 'reason' => $lyDo],
        );

        return new JsonResponse([
            'data' => $this->presentPeriod($kyCong->load(['closedBy', 'reopenedBy'])),
        ]);
    }
}
