<?php

declare(strict_types=1);

namespace App\Domain\Identity\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Một mã OTP dùng một lần đã gửi qua email.
 *
 * `code_hash` là bản băm — mã gốc chỉ tồn tại trong bộ nhớ đúng một lần lúc
 * gửi email, không lưu và không ghi log. Kể cả test cũng phải đọc mã từ email
 * đã gửi chứ không đọc từ database.
 *
 * @property int $id
 * @property int $user_id
 * @property string $code_hash
 * @property string $sent_to
 * @property string|null $ip_address
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property CarbonImmutable $expires_at
 * @property CarbonImmutable|null $used_at
 * @property CarbonImmutable|null $invalidated_at
 */
#[Fillable(['user_id', 'code_hash', 'sent_to', 'expires_at', 'ip_address'])]
final class TwoFactorCode extends Model
{
    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Còn dùng được: chưa dùng, chưa bị thay thế, chưa hết hạn. */
    public function isUsable(): bool
    {
        return $this->used_at === null
            && $this->invalidated_at === null
            && $this->expires_at->isFuture();
    }

    /**
     * @param  Builder<$this>  $query
     */
    public function scopeUsable(Builder $query): void
    {
        $query->whereNull('used_at')
            ->whereNull('invalidated_at')
            ->where('expires_at', '>', now());
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
            'invalidated_at' => 'datetime',
        ];
    }
}
