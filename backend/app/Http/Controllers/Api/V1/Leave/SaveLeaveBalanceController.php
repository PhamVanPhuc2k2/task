<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Leave;

use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Models\User;
use App\Domain\Leave\Actions\ResolveLeaveBalanceAction;
use App\Domain\Leave\Actions\SaveLeaveBalanceAction;
use App\Domain\Payroll\Actions\RecordPayrollAuditAction;
use App\Domain\Payroll\Enums\PayrollAuditEvent;
use App\Http\Concerns\PresentsLeaveBalances;
use App\Http\Concerns\ResolvesLeaveYear;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Nhân sự sửa quỹ phép năm của một người.
 *
 * Ba việc trong cùng một đường, vì chúng luôn được cân nhắc cùng lúc: chuyển
 * phép tồn năm trước, cộng trừ điều chỉnh, và ghi đè hẳn con số. Tách thành ba
 * endpoint thì màn hình phải gửi ba request cho một lần bấm Lưu, và hai trong
 * ba có thể thành công trong khi cái thứ ba hỏng.
 *
 * ## Quyền RIÊNG, không phải `leave.approve`
 *
 * Duyệt một đơn nghỉ là quyết định về MỘT lần vắng mặt. Cộng thêm ngày phép là
 * quyết định về cả năm — và phép chưa nghỉ hết phải được thanh toán khi thôi
 * việc (Điều 113 khoản 4 Bộ luật Lao động 2019), tức là nó ra tiền. Trưởng
 * phòng duyệt đơn cho phòng mình là bình thường; trưởng phòng tự cộng ngày phép
 * cho phòng mình thì không.
 *
 * ## Ghi nhật ký kiểm toán
 *
 * Vào `payroll_audits`, cùng chỗ với đổi mức lương và chốt sổ kỳ công — cùng
 * một họ: hành vi quyết định số tiền công ty phải trả. Ghi cả giá trị cũ lẫn
 * mới, vì câu hỏi sáu tháng sau là *"ai đổi, từ bao nhiêu sang bao nhiêu"*.
 */
final class SaveLeaveBalanceController
{
    use PresentsLeaveBalances;
    use ResolvesLeaveYear;

    /** Một người không thể có quá ngần này ngày phép trong một năm. */
    private const int NGAY_TOI_DA = 60;

    public function __invoke(
        Request $request,
        User $user,
        SaveLeaveBalanceAction $action,
        ResolveLeaveBalanceAction $doc,
        RecordPayrollAuditAction $ghiNhatKy,
    ): JsonResponse {
        /** @var User $actor */
        $actor = $request->user();

        abort_unless(
            $actor->can(Permission::ManageLeaveBalance->value),
            Response::HTTP_FORBIDDEN,
        );

        $duLieu = $request->validate([
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],

            /*
            | Ba ô số đều nhận bội của 0,5 — công ty làm sáng thứ bảy, nên nửa
            | ngày phép là đơn vị có thật.
            |
            | `multiple_of` chứ không để tự do: 0,3 ngày phép là con số không
            | màn hình nào hiển thị được cho ra hồn, và nó lọt vào bằng một cú
            | gõ nhầm chứ không phải một quyết định.
            */
            'entitled_days_override' => [
                'nullable', 'numeric', 'min:0', 'max:'.self::NGAY_TOI_DA, 'multiple_of:0.5',
            ],
            'carried_over_days' => [
                'required', 'numeric', 'min:0', 'max:'.self::NGAY_TOI_DA, 'multiple_of:0.5',
            ],
            // Điều chỉnh ĐƯỢC PHÉP ÂM: trừ bớt ngày phép cũng là một quyết định
            // có thật, và bắt ghi đè cả con số chỉ để trừ một ngày là làm mất
            // luôn phần tính tự động theo thâm niên.
            'adjustment_days' => [
                'required', 'numeric', 'min:-'.self::NGAY_TOI_DA, 'max:'.self::NGAY_TOI_DA, 'multiple_of:0.5',
            ],
            'note' => ['nullable', 'string', 'max:500'],
        ], [
            'entitled_days_override.multiple_of' => 'Ngày phép tính theo nửa ngày — nhập 0,5 hoặc 1.',
            'carried_over_days.multiple_of' => 'Ngày phép tính theo nửa ngày — nhập 0,5 hoặc 1.',
            'adjustment_days.multiple_of' => 'Ngày phép tính theo nửa ngày — nhập 0,5 hoặc 1.',
        ]);

        $nam = (int) $duLieu['year'];

        // Đọc TRƯỚC khi ghi: nhật ký cần giá trị cũ, và sau khi ghi thì không
        // còn chỗ nào lấy lại được.
        $truoc = $doc->execute($user, $nam);

        $action->execute(
            nhanVien: $user,
            actor: $actor,
            nam: $nam,
            ghiDe: isset($duLieu['entitled_days_override'])
                ? (float) $duLieu['entitled_days_override']
                : null,
            phepTon: (float) $duLieu['carried_over_days'],
            dieuChinh: (float) $duLieu['adjustment_days'],
            ghiChu: isset($duLieu['note']) ? (string) $duLieu['note'] : null,
        );

        $sau = $doc->execute($user, $nam);

        // Ghi SAU khi lưu thành công. Ghi trước thì một lần bấm bị từ chối vì
        // vượt trần phép tồn vẫn để lại một dòng "đã sửa" trong nhật ký.
        $ghiNhatKy->execute(
            event: PayrollAuditEvent::LeaveBalanceChanged,
            actor: $actor,
            subject: $user,
            context: [
                'year' => $nam,
                'before' => [
                    'entitled' => $truoc->entitledDays,
                    'carried_over' => $truoc->carriedOverDays,
                    'adjustment' => $truoc->adjustmentDays,
                ],
                'after' => [
                    'entitled' => $sau->entitledDays,
                    'carried_over' => $sau->carriedOverDays,
                    'adjustment' => $sau->adjustmentDays,
                ],
                'note' => $sau->note,
            ],
        );

        return new JsonResponse([
            'data' => $this->presentBalance(
                $sau,
                nhanVien: $user->load('department'),
                conLaiNamTruoc: $doc->execute($user, $nam - 1)->remainingDays(),
            ),
        ]);
    }
}
