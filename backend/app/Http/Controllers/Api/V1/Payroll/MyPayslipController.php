<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Payroll;

use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Models\User;
use App\Http\Concerns\PresentsPayslips;
use App\Http\Concerns\ResolvesPayrollPeriod;
use App\Http\Support\PayslipAssembler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;

/**
 * Phiếu lương của chính người đang đăng nhập.
 *
 * Cần `payroll.view.own` — quyền mà nhân viên có sẵn. Kiểm tường minh chứ không
 * miễn như các màn "của tôi" khác: đây là tiền, và một ngày nào đó công ty có
 * thể muốn tắt hẳn việc nhân viên tự xem phiếu lương trong hệ thống. Lúc đó chỉ
 * cần gỡ một quyền, không phải sửa mã.
 *
 * **Không ghi nhật ký kiểm toán.** `payroll_audits` tồn tại để trả lời *"ai đã
 * xem lương của NGƯỜI KHÁC"*; một người mở phiếu của chính mình không phải câu
 * hỏi đó, và ghi lại thì nhật ký đầy những dòng vô nghĩa đúng lúc cần tra cứu.
 */
final class MyPayslipController
{
    use PresentsPayslips;
    use ResolvesPayrollPeriod;

    public function __invoke(Request $request, PayslipAssembler $dung): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        abort_unless($actor->can(Permission::ViewOwnSalary->value), Response::HTTP_FORBIDDEN);

        $ky = $this->kyLuong($request);

        $phieu = $dung->forUsers(new Collection([$actor]), $ky);

        return new JsonResponse([
            'data' => $this->presentPayslip($phieu[$actor->id], $this->kyDaChot($ky)),
        ]);
    }
}
