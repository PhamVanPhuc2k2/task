<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Payroll;

use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Models\User;
use App\Domain\Payroll\Actions\RecordPayrollAuditAction;
use App\Domain\Payroll\Data\Payslip;
use App\Domain\Payroll\Enums\PayrollAuditEvent;
use App\Http\Concerns\PresentsPayslips;
use App\Http\Concerns\ResolvesPayrollPeriod;
use App\Http\Support\PayslipAssembler;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Bảng kê lương của cả công ty trong một kỳ.
 *
 * Cần `payroll.view.all`, và **mọi lượt đọc đều vào nhật ký** — cùng nguyên tắc
 * với bảng mức lương: *"ai đã xem bảng lương tháng 9"* là câu hỏi có thật và sẽ
 * có người hỏi.
 *
 * ## Phiếu của kỳ chưa chốt là bản TẠM
 *
 * Không chặn xem — kế toán cần nhìn trước để biết tháng này rơi vào khoảng nào.
 * Nhưng `is_final` nói thẳng ra, và màn hình phải hiện điều đó: một đơn giải
 * trình được duyệt chiều nay sẽ đổi số giờ thiếu của cả tháng.
 */
final class PayslipController
{
    use PresentsPayslips;
    use ResolvesPayrollPeriod;

    /** Trần số dòng. Luôn trả kèm tổng — quy ước chung của cả dự án. */
    private const int TRAN = 200;

    public function index(
        Request $request,
        PayslipAssembler $dung,
        RecordPayrollAuditAction $ghiNhatKy,
    ): JsonResponse {
        /** @var User $actor */
        $actor = $request->user();

        abort_unless($actor->can(Permission::ViewAllSalary->value), Response::HTTP_FORBIDDEN);

        $ky = $this->kyLuong($request);

        $ghiNhatKy->execute(
            event: PayrollAuditEvent::ViewedList,
            actor: $actor,
            context: ['period' => $ky, 'screen' => 'payslips'],
        );

        /*
        | Chỉ người ĐANG LÀM VIỆC.
        |
        | Người đã nghỉ việc vẫn còn dòng lương và còn giờ công của những tháng
        | trước, nên không lọc thì bảng kê tháng này đầy những người không còn ở
        | công ty. Phiếu của họ vẫn tra được ở màn từng người khi cần quyết toán.
        */
        $truyVan = User::query()
            ->where('is_active', true)
            ->with('department')
            ->orderBy('name');

        $tong = (clone $truyVan)->count();

        $nhanVien = $truyVan->limit(self::TRAN)->get();

        $phieu = $dung->forUsers($nhanVien, $ky);
        $daChot = $this->kyDaChot($ky);

        return new JsonResponse([
            'data' => [
                'period' => $ky,
                'is_final' => $daChot,

                'payslips' => $nhanVien->map(
                    fn (User $u): array => $this->presentPayslip($phieu[$u->id], $daChot, $u),
                )->values()->all(),

                // Trả tổng kèm trần: cắt im lặng thì công ty 250 người tưởng
                // mình chỉ có 200 — và với bảng lương thì đó là 50 người không
                // được trả mà không ai nhận ra.
                'total' => $tong,
                'limit' => self::TRAN,

                // Tổng chi của kỳ, cộng từ đúng những dòng đang hiện. Kế toán
                // cần con số này trước khi mở từng phiếu.
                'net_total' => Money::tong(
                    array_map(
                        static fn (Payslip $p): string => $p->netTotal,
                        array_values($phieu),
                    ),
                ),
            ],
        ]);
    }
}
