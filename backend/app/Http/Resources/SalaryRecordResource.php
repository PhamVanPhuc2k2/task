<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Payroll\Models\SalaryRecord;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Một mức lương trong lịch sử.
 *
 * Số tiền trả về dạng **chuỗi**, đúng như DECIMAL lưu. Trả về số trong JSON là
 * giao cho JavaScript, nơi mọi số đều là float 64 bit — `12345678.90` vẫn an
 * toàn, nhưng phép cộng dồn trên nhiều dòng thì bắt đầu lệch xu. Chuỗi buộc
 * frontend phải xử lý tiền một cách có ý thức.
 *
 * @mixin SalaryRecord
 */
final class SalaryRecordResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'effective_from' => $this->effective_from->toDateString(),
            'effective_to' => $this->effective_to?->toDateString(),
            'is_current' => $this->effective_to === null,
            'base_salary' => $this->base_salary,
            'allowance' => $this->allowance,
            'total' => $this->total(),
            'currency' => $this->currency,
            'reason' => $this->reason,

            'author' => $this->whenLoaded('author', fn (): ?array => $this->author === null ? null : [
                'id' => $this->author->uuid,
                'name' => $this->author->name,
            ]),

            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
