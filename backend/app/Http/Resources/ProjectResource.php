<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Task\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Project
 */
final class ProjectResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            // Lộ uuid, không lộ id tuần tự — xem README "Quy ước dữ liệu".
            'id' => $this->uuid,
            'name' => $this->name,
            'code' => $this->code,
            'description' => $this->description,

            'status' => [
                'value' => $this->status->value,
                'label' => $this->status->label(),
                'is_open' => $this->status->isOpen(),
            ],

            // Ngày thuần, không kèm giờ: mốc dự án là ngày làm việc, không phải
            // một thời điểm — xem README "Quy ước dữ liệu, thời gian & tiền tệ".
            'start_date' => $this->start_date?->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),

            'owner' => $this->whenLoaded('owner', fn (): ?array => $this->owner === null ? null : [
                'id' => $this->owner->uuid,
                'name' => $this->owner->name,
                'email' => $this->owner->email,
            ]),

            'department' => $this->whenLoaded('department', fn (): ?array => $this->department === null ? null : [
                'id' => $this->department->uuid,
                'name' => $this->department->name,
            ]),

            'task_count' => $this->whenCounted('tasks'),
            'member_count' => $this->whenCounted('members'),
        ];
    }
}
