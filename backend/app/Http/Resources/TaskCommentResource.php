<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Identity\Models\User;
use App\Domain\Task\Data\AttachmentRules;
use App\Domain\Task\Models\TaskComment;
use App\Support\Media\MediaUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * @mixin TaskComment
 */
final class TaskCommentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            // Nội dung giữ nguyên dạng thô kèm dấu nhắc `@[Tên](uuid)`.
            // Frontend tự dựng thành chip — server không sinh HTML, để không
            // có đường nào biến nội dung người dùng thành thẻ chạy được.
            'body' => $this->body,
            'created_at' => $this->created_at?->toIso8601String(),
            'edited_at' => $this->edited_at?->toIso8601String(),
            'parent_id' => $this->whenLoaded('parent', fn (): ?string => $this->parent?->uuid),

            'author' => $this->whenLoaded('author', fn (): ?array => $this->author === null ? null : [
                'id' => $this->author->uuid,
                'name' => $this->author->name,
                'email' => $this->author->email,
            ]),

            'mentions' => $this->whenLoaded('mentions', fn (): array => $this->mentions
                ->map(fn (User $u): array => ['id' => $u->uuid, 'name' => $u->name])
                ->all()),

            'attachments' => $this->when(
                $this->relationLoaded('media'),
                fn (): array => $this->getMedia(TaskComment::DINH_KEM)
                    ->map(fn (Media $media): array => self::dinhKem($media))
                    ->all(),
            ),

            'reply_count' => $this->whenCounted('replies'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function dinhKem(Media $media): array
    {
        $laAnh = AttachmentRules::isImage($media->mime_type ?? '');

        return [
            'id' => $media->uuid,
            // Tên gốc để người dùng nhận ra tệp; tên lưu trên đĩa đã bị đổi.
            'name' => $media->name,
            'file_name' => $media->file_name,
            'mime_type' => $media->mime_type,
            'size' => $media->size,
            'is_image' => $laAnh,
            'url' => MediaUrl::for($media),
            // Thumbnail sinh ở hàng đợi nền nên có thể chưa sẵn sàng ngay sau
            // khi tải lên. Trả null thay vì một đường dẫn 404.
            'thumb_url' => $laAnh && $media->hasGeneratedConversion('thumb')
                ? MediaUrl::for($media, 'thumb')
                : null,
        ];
    }
}
