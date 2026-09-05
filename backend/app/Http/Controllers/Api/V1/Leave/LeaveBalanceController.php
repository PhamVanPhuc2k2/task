<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Leave;

use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Models\User;
use App\Domain\Leave\Actions\ResolveLeaveBalanceAction;
use App\Domain\Leave\Data\AnnualLeavePolicy;
use App\Http\Concerns\PresentsLeaveBalances;
use App\Http\Concerns\ResolvesLeaveYear;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Bảng quỹ phép năm của nhân sự trong phạm vi quản lý.
 *
 * Phạm vi giống hộp duyệt đơn nghỉ: `leave.view.all` thấy cả công ty, trưởng
 * phòng chỉ thấy cây phòng ban của mình. Xem được không có nghĩa là sửa được —
 * `can_manage` nói riêng chuyện đó.
 *
 * ## Bốn truy vấn cố định cho cả phòng
 *
 * Danh sách người, quỹ phép năm nay, quỹ phép năm trước, và số ngày đã dùng.
 * Không phải bốn lần mỗi người: bảng của một phòng ba mươi người là đúng chỗ dễ
 * thành N+1 nhất của tính năng này, và `Model::preventLazyLoading()` chỉ bắt
 * được quan hệ chưa nạp chứ không bắt được một vòng lặp gọi action.
 */
final class LeaveBalanceController
{
    use PresentsLeaveBalances;
    use ResolvesLeaveYear;

    /** Trần số dòng. Luôn trả kèm tổng — quy ước chung của cả dự án. */
    private const int TRAN = 200;

    public function index(
        Request $request,
        ResolveLeaveBalanceAction $action,
    ): JsonResponse {
        /** @var User $actor */
        $actor = $request->user();

        $toanBo = $actor->can(Permission::ViewAllLeave->value);

        abort_unless(
            $toanBo || $actor->can(Permission::ViewTeamLeave->value),
            Response::HTTP_FORBIDDEN,
        );

        $nam = $this->namQuyPhep($request);

        $truyVan = User::query()->with('department')->orderBy('name');

        if (! $toanBo) {
            $phamVi = $actor->department?->subtreeIds() ?? [];
            $truyVan->whereIn('department_id', $phamVi);
        }

        $tong = (clone $truyVan)->count();

        $nhanVien = $truyVan->limit(self::TRAN)->get();

        $namNay = $action->forUsers($nhanVien, $nam);
        $namTruoc = $action->forUsers($nhanVien, $nam - 1);

        $chinhSach = AnnualLeavePolicy::fromConfig();

        return new JsonResponse([
            'data' => [
                'year' => $nam,

                'balances' => $nhanVien->map(
                    fn (User $u): array => $this->presentBalance(
                        $namNay[$u->id],
                        nhanVien: $u,
                        conLaiNamTruoc: $namTruoc[$u->id]->remainingDays(),
                    ),
                )->values()->all(),

                // Trả tổng kèm trần: cắt im lặng thì công ty 250 người tưởng
                // mình chỉ có 200. Quy ước chung của cả dự án.
                'total' => $tong,
                'limit' => self::TRAN,

                // Giao diện hỏi server thay vì tự suy từ danh sách quyền — thêm
                // một quyền mới thì màn hình tự đúng.
                'can_manage' => $actor->can(Permission::ManageLeaveBalance->value),

                /*
                | Chính sách hiện hành, để màn hình giải thích được con số.
                |
                | Nhân sự nhìn "12 ngày" mà không biết nó đến từ đâu thì mỗi lần
                | có người thắc mắc lại phải đi tra. Trần phép tồn cũng ở đây,
                | vì ô nhập cần nó để chặn ngay thay vì để API từ chối.
                */
                'policy' => [
                    'base_days' => $chinhSach->baseDays,
                    'seniority_step_years' => $chinhSach->seniorityStepYears,
                    'seniority_extra_days' => $chinhSach->seniorityExtraDays,
                    'carry_over_max_days' => $chinhSach->carryOverMaxDays,
                ],
            ],
        ]);
    }
}
