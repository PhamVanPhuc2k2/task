<?php

declare(strict_types=1);

namespace App\Domain\Identity\Models;

use App\Domain\Identity\Enums\NotificationType;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tuỳ chọn nhận thông báo của một người, cho một loại.
 *
 * Chỉ tồn tại dòng cho những loại người dùng đã tự chỉnh; không có dòng nghĩa
 * là dùng mặc định của `NotificationType`. Nhờ vậy thêm loại thông báo mới về
 * sau không phải chèn thêm dòng cho toàn bộ nhân sự.
 *
 * @property int $id
 * @property int $user_id
 * @property NotificationType $type
 * @property bool $in_app
 * @property bool $email
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['user_id', 'type', 'in_app', 'email'])]
final class UserNotificationSetting extends Model
{
    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => NotificationType::class,
            'in_app' => 'boolean',
            'email' => 'boolean',
        ];
    }
}
