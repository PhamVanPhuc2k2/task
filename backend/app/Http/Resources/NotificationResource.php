<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Notifications\DatabaseNotification;

/**
 * @mixin DatabaseNotification
 */
final class NotificationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var array<string, mixed> $data */
        $data = $this->data;

        return [
            // Khoá chính của bảng này vốn đã là uuid, không phải số tuần tự.
            'id' => $this->id,
            'kind' => $data['type'] ?? null,
            'title' => $data['title'] ?? '',
            'message' => $data['message'] ?? '',
            // Đường dẫn tương đối trong ứng dụng, frontend tự ghép tiền tố.
            'url' => $data['url'] ?? null,
            'task_id' => $data['task_id'] ?? null,
            'actor_name' => $data['actor_name'] ?? null,
            'read_at' => $this->read_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
