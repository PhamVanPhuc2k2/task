<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Task\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Task
 */
final class TaskResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            // Lộ uuid, không lộ id tuần tự — xem README "Quy ước dữ liệu".
            'id' => $this->uuid,
            'title' => $this->title,
            'description' => $this->description,

            'status' => [
                'value' => $this->status->value,
                'label' => $this->status->label(),
                'is_closed' => $this->status->isClosed(),
            ],
            'priority' => [
                'value' => $this->priority->value,
                'label' => $this->priority->label(),
            ],

            // ISO 8601 kèm offset — frontend tự đổi sang giờ Việt Nam.
            // Xem README "Quy ước dữ liệu, thời gian & tiền tệ".
            'due_date' => $this->due_date?->toIso8601String(),
            'started_at' => $this->started_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),

            'estimate_hours' => $this->estimate_hours,
            'progress_percent' => $this->progress_percent,

            // Hiển thị công khai để việc dời hạn không diễn ra trong im lặng.
            'due_date_change_count' => $this->due_date_change_count,
            'is_overdue' => $this->due_date !== null
                && $this->due_date->isPast()
                && ! $this->status->isClosed(),

            'project' => $this->whenLoaded('project', fn (): ?array => $this->project === null ? null : [
                'id' => $this->project->uuid,
                'name' => $this->project->name,
            ]),

            'assignee' => $this->whenLoaded('assignee', fn (): ?array => self::nguoiDung($this->assignee)),
            'assigner' => $this->whenLoaded('assigner', fn (): ?array => self::nguoiDung($this->assigner)),
            'reviewer' => $this->whenLoaded('reviewer', fn (): ?array => self::nguoiDung($this->reviewer)),

            'labels' => $this->whenLoaded('labels', fn (): array => $this->labels
                ->map(fn ($label): array => [
                    'id' => $label->uuid,
                    'name' => $label->name,
                    'color' => $label->color,
                ])->all()),

            'subtask_count' => $this->whenCounted('subtasks'),
            'comment_count' => $this->whenCounted('comments'),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function nguoiDung(mixed $user): ?array
    {
        if ($user === null) {
            return null;
        }

        return [
            'id' => $user->uuid,
            'name' => $user->name,
            'email' => $user->email,
        ];
    }
}
