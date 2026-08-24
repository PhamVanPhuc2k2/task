<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Task\Models\TaskActivity;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Một dòng trong nhật ký thay đổi của task.
 *
 * Đây là bảng con, chỉ đọc được qua đúng một task mà người dùng đã thấy được,
 * và không có đường `/activities/{id}` để dò tuần tự — nên lộ khoá chính ở đây
 * không mở ra thứ gì. Xem README mục 1.7, "Quyết định phát sinh".
 *
 * @mixin TaskActivity
 */
final class TaskActivityResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'event' => $this->event,
            'old_values' => $this->old_values,
            'new_values' => $this->new_values,
            'created_at' => $this->created_at?->toIso8601String(),

            'causer' => $this->whenLoaded('causer', fn (): ?array => $this->causer === null ? null : [
                'id' => $this->causer->uuid,
                'name' => $this->causer->name,
            ]),
        ];
    }
}
