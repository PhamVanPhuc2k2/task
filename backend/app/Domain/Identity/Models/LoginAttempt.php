<?php

declare(strict_types=1);

namespace App\Domain\Identity\Models;

use Carbon\CarbonImmutable;
use Database\Factories\Identity\LoginAttemptFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Một lần thử đăng nhập, thành công hoặc thất bại.
 *
 * Chỉ ghi thêm, không sửa, không xoá.
 *
 * `@property` là bắt buộc theo quy ước dự án — xem README, "Khối @property
 * trên model". Không có nó thì Larastan phải suy kiểu từ migration, và khi
 * bộ quét migration hỏng thì mọi chỗ đọc thuộc tính đều báo lỗi.
 *
 * @property int $id
 * @property int|null $user_id
 * @property string $email
 * @property bool $successful
 * @property string|null $failure_reason
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['user_id', 'email', 'successful', 'failure_reason', 'ip_address', 'user_agent'])]
final class LoginAttempt extends Model
{
    /** @use HasFactory<LoginAttemptFactory> */
    use HasFactory;

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
            'successful' => 'boolean',
        ];
    }
}
