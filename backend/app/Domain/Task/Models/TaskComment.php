<?php

declare(strict_types=1);

namespace App\Domain\Task\Models;

use App\Domain\Identity\Models\User;
use App\Domain\Task\Data\AttachmentRules;
use App\Support\Concerns\HasUuid;
use Carbon\CarbonImmutable;
use Database\Factories\Task\TaskCommentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Bình luận trên một task.
 *
 * @property int $id
 * @property string $uuid
 * @property int $task_id
 * @property int|null $parent_id
 * @property int|null $user_id
 * @property string $body
 * @property CarbonImmutable|null $edited_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property CarbonImmutable|null $deleted_at
 *
 * Quan hệ `task` khai riêng vì `task_id` là cột NOT NULL: bình luận không thể
 * tồn tại mà không thuộc task nào. Không khai thì phân tích tĩnh coi nó có thể
 * null và bắt kiểm `?->` ở mọi chỗ dùng, che mất những chỗ null thật sự có thể
 * xảy ra. `author` thì để nullable — người viết nghỉ việc là bình luận vẫn còn.
 * @property-read Task $task
 * @property-read User|null $author
 */
#[Fillable(['task_id', 'parent_id', 'user_id', 'body', 'edited_at'])]
final class TaskComment extends Model implements HasMedia
{
    /** @use HasFactory<TaskCommentFactory> */
    use HasFactory;

    use HasUuid;
    use InteractsWithMedia;
    use SoftDeletes;

    /** Tên collection đính kèm. Dùng ở cả Action lẫn Resource nên đặt hằng. */
    public const string DINH_KEM = 'attachments';

    /** @return BelongsTo<Task, $this> */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** @return BelongsTo<self, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** @return HasMany<self, $this> */
    public function replies(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /**
     * Những người được nhắc tên trong bình luận này.
     *
     * Lưu thành bảng riêng chứ không dò lại từ nội dung mỗi lần đọc: nội dung
     * sửa được, và một lần dò sai sau khi sửa sẽ làm mất dấu ai đã được nhắc.
     *
     * @return BelongsToMany<User, $this>
     */
    public function mentions(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'task_comment_mentions')
            ->withTimestamps();
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(self::DINH_KEM)
            // Danh sách trắng kiểm ở tầng lưu trữ, không chỉ ở Form Request:
            // đây là lớp chặn cuối cùng nếu về sau có đường ghi khác quên kiểm.
            ->acceptsMimeTypes(AttachmentRules::mimeTypes())
            ->useDisk(config()->string('media-library.disk_name'));
    }

    /**
     * Ảnh xem trước.
     *
     * Chỉ sinh cho ảnh — gọi trên PDF hay .docx sẽ ném lỗi trong job nền. Chạy
     * ở hàng đợi riêng 'media' để một lượt tải mười ảnh không chiếm hết worker
     * của các job khác.
     */
    public function registerMediaConversions(?Media $media = null): void
    {
        if ($media === null || ! AttachmentRules::isImage($media->mime_type ?? '')) {
            return;
        }

        // Thứ tự quan trọng: các phương thức của chính Conversion (`queued`,
        // `nonOptimized`) phải đứng TRƯỚC lời gọi biến đổi ảnh. `fit()` uỷ
        // quyền xuống driver ảnh và trả về driver, nên gọi tiếp `queued()` sau
        // nó là gọi vào một đối tượng không có phương thức đó.
        $this->addMediaConversion('thumb')
            ->queued()
            ->nonOptimized()
            ->fit(Fit::Crop, 320, 320);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'edited_at' => 'datetime',
        ];
    }
}
