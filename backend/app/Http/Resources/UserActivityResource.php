<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Identity\Models\UserActivity;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Một dòng trong nhật ký nhân sự.
 *
 * Trả kèm `event_label` tiếng Việt lấy từ enum, không để frontend tự tra bảng:
 * thêm loại biến cố mới ở đợt sau thì giao diện hiện được ngay mà không phải
 * sửa và triển khai lại. Vẫn trả cả `event` dạng máy đọc cho chỗ nào cần lọc.
 *
 * @mixin UserActivity
 */
final class UserActivityResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'event' => $this->event->value,
            'event_label' => $this->event->label(),
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
