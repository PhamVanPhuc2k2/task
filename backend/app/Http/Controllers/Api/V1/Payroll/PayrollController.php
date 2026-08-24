<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Payroll;

use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Models\Department;
use App\Domain\Identity\Models\User;
use App\Domain\Payroll\Actions\RecordPayrollAuditAction;
use App\Domain\Payroll\Actions\SetSalaryAction;
use App\Domain\Payroll\Data\SetSalaryData;
use App\Domain\Payroll\Enums\PayrollAuditEvent;
use App\Domain\Payroll\Models\SalaryRecord;
use App\Http\Requests\Payroll\SetSalaryRequest;
use App\Http\Resources\PayrollRowResource;
use App\Http\Resources\SalaryRecordResource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Routing\Attributes\Controllers\Authorize;

/**
 * Đặt và xem mức lương.
 *
 * **Tách hẳn khỏi màn quản trị nhân sự, có chủ ý.** Hộp thoại sửa nhân viên
 * dùng bởi người có `user.manage`, mà người đó chưa chắc có quyền xem lương —
 * nếu nhét thêm tab "Lương" vào đó thì component phải ẩn/hiện trường theo
 * quyền, và đó đúng là cách rò rỉ xảy ra. Đường dẫn riêng nghĩa là guard riêng,
 * không có nhánh `if` nào quyết định lương có hiện hay không.
 *
 * **Mọi lượt đọc đều được ghi nhật ký**, khác với các miền khác. "Ai đã xem
 * bảng lương phòng Kinh doanh" là câu hỏi có thật và sẽ có người hỏi.
 */
final class PayrollController
{
    /**
     * Mức lương hiện hành của những người trong phạm vi.
     *
     * Chỉ trả mức ĐANG hiệu lực, không trả lịch sử: bảng này để nhìn tổng thể,
     * còn lịch sử đọc ở màn chi tiết từng người.
     */
    public function index(
        Request $request,
        RecordPayrollAuditAction $ghiNhatKy,
    ): AnonymousResourceCollection {
        /** @var User $actor */
        $actor = $request->user();

        abort_unless($actor->can(Permission::ViewAllSalary->value), Response::HTTP_FORBIDDEN);

        $ghiNhatKy->execute(
            event: PayrollAuditEvent::ViewedList,
            actor: $actor,
            context: ['department_id' => $request->input('department_id')],
        );

        $nhanSu = User::query()
            ->where('is_active', true)
            ->when(
                $request->filled('search'),
                function (Builder $q) use ($request): void {
                    $tuKhoa = '%'.$request->string('search').'%';

                    $q->where(fn (Builder $s) => $s
                        ->where('name', 'like', $tuKhoa)
                        ->orWhere('employee_code', 'like', $tuKhoa));
                },
            )
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
            ->paginate(perPage: min((int) $request->integer('per_page', 25), 100))
            ->withQueryString();

        // Một truy vấn cho mọi mức lương hiện hành, không phải một truy vấn mỗi
        // người. Đây là chỗ dễ thành N+1 nhất của màn này.
        $mucLuong = SalaryRecord::query()
            ->whereIn('user_id', $nhanSu->pluck('id')->all())
            ->current()
            ->get()
            ->keyBy('user_id');

        // Gắn mức lương vào từng người từ ngoài. `User` thuộc miền Identity nên
        // không được khai quan hệ tới Payroll — deptrac chặn, và luật đó đúng.
        // Tầng Http là nơi duy nhất được biết cả hai miền.
        $nhanSu->getCollection()->each(
            fn (User $u) => $u->setRelation('currentSalary', $mucLuong->get($u->id)),
        );

        return PayrollRowResource::collection($nhanSu);
    }

    /**
     * Toàn bộ lịch sử mức lương của một người.
     *
     * Người dùng xem được của chính mình mà không cần quyền gì thêm — lương của
     * mình thì mình có quyền biết, và biết cả lịch sử điều chỉnh.
     */
    public function show(
        Request $request,
        User $user,
        RecordPayrollAuditAction $ghiNhatKy,
    ): AnonymousResourceCollection {
        /** @var User $actor */
        $actor = $request->user();

        abort_unless($this->duocXem($actor, $user), Response::HTTP_FORBIDDEN);

        // Xem của chính mình thì không ghi nhật ký: nhật ký này để trả lời "ai
        // đã xem lương của người khác", còn tự xem lương mình thì không có gì
        // để kiểm toán, mà ghi thì bảng đầy rác trong một tuần.
        if (! $actor->is($user)) {
            $ghiNhatKy->execute(
                event: PayrollAuditEvent::ViewedPerson,
                actor: $actor,
                subject: $user,
            );
        }

        $lichSu = SalaryRecord::query()
            ->where('user_id', $user->id)
            ->with('author')
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->get();

        return SalaryRecordResource::collection($lichSu);
    }

    #[Authorize('setSalary', 'user')]
    public function store(
        SetSalaryRequest $request,
        User $user,
        SetSalaryAction $action,
    ): JsonResponse {
        /** @var User $actor */
        $actor = $request->user();

        $moi = $action->execute($user, new SetSalaryData(
            baseSalary: (string) $request->string('base_salary'),
            allowance: $request->filled('allowance')
                ? (string) $request->string('allowance')
                : '0',
            effectiveFrom: (string) $request->string('effective_from'),
            reason: (string) $request->string('reason'),
        ), $actor);

        return SalaryRecordResource::make($moi->load('author'))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    private function duocXem(User $actor, User $target): bool
    {
        if ($actor->is($target)) {
            return $actor->can(Permission::ViewOwnSalary->value);
        }

        return $actor->can(Permission::ViewAllSalary->value);
    }
}
