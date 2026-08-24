<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Identity\Models\User;
use App\Domain\Payroll\Models\SalaryRecord;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Một dòng trong bảng lương: nhân viên và mức đang hiệu lực của họ.
 *
 * Ghép hai model nhưng **không** dùng `UserResource`: bộ đó trả cả vai trò,
 * quyền, email, số điện thoại — không cái nào cần cho bảng lương, và mỗi trường
 * thừa lộ ra là một trường có thể lọt sang chỗ không nên tới. Ở đây chỉ đúng
 * bốn thông tin đủ để nhận ra người.
 *
 * `salary` là `null` khi nhân viên chưa được đặt mức lương nào — đó là trạng
 * thái hợp lệ và giao diện phải hiện được nó, không phải lỗi.
 *
 * @mixin User
 */
final class PayrollRowResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $muc = $this->getSalary();

        return [
            'user' => [
                'id' => $this->uuid,
                'name' => $this->name,
                'employee_code' => $this->employee_code,
                'department' => $this->department?->name,
            ],

            'salary' => $muc === null ? null : [
                'base_salary' => $muc->base_salary,
                'allowance' => $muc->allowance,
                'total' => $muc->total(),
                'currency' => $muc->currency,
                'effective_from' => $muc->effective_from->toDateString(),
            ],
        ];
    }

    /**
     * Mức lương mà controller gắn kèm bằng `setRelation`.
     *
     * Gắn từ ngoài chứ không khai quan hệ trên model `User`: `User` thuộc miền
     * Identity, và Identity không được tham chiếu tới Payroll — deptrac chặn,
     * và luật đó đúng. Tầng Http là nơi duy nhất được biết cả hai miền.
     */
    private function getSalary(): ?SalaryRecord
    {
        if (! $this->resource instanceof User || ! $this->resource->relationLoaded('currentSalary')) {
            return null;
        }

        $muc = $this->resource->getRelation('currentSalary');

        return $muc instanceof SalaryRecord ? $muc : null;
    }
}
